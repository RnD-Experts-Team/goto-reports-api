<?php

declare(strict_types=1);

namespace App\Services\GoTo\Reports;

use App\Models\CallEventSummary;
use App\Models\GotoAccount;
use App\Services\GoTo\AccountNameResolver;
use App\Services\GoTo\GoToApiClient;
use App\Services\GoTo\GoToAuthService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Conversations report — a slim, persisted view of GoTo Call Events Report
 * Summaries.
 *
 * Workflow on each request:
 *   1. Pull conversation summaries from GoTo for the requested account(s)
 *      and date range.
 *   2. Upsert each row into `call_event_summaries` keyed by
 *      `conversation_space_id` (existing rows are left untouched, new rows
 *      are inserted).
 *   3. Query the local table with the user-supplied filters and return rows
 *      shaped for the API/CSV consumer.
 *
 * Output columns:
 *   Account Key | Organization ID | Account Name | Date | Duration |
 *   Call Result | From | Participants
 */
class ConversationsReportService
{
    public function __construct(
        private readonly GoToApiClient $apiClient,
        private readonly GoToAuthService $authService,
        private readonly AccountNameResolver $nameResolver,
    ) {
    }

    /**
     * Sync GoTo data into the local DB for the given filters, then return rows.
     *
     * @return array{synced:int,rows:array<int,array<string,mixed>>}
     */
    public function syncAndQuery(array $params): array
    {
        $synced = $this->syncFromGoTo($params);
        $rows   = $this->query($params);
        return ['synced' => $synced, 'rows' => $rows];
    }

    /**
     * Backfill ALL accounts available on the current token for a wide
     * historical window. Long-running: callers should bump max_execution_time.
     *
     * @return array{synced:int,accounts:int,startTime:string,endTime:string,days:int,durationSec:float}
     */
    public function backfillAll(int $days = 365): array
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $end   = Carbon::now('UTC');
        $start = $end->copy()->subDays(max(1, $days));
        $started = microtime(true);

        $accounts = $this->authService->getAccountsWithOrganizations();

        $synced = $this->syncFromGoTo([
            'accountKey' => 'all',
            'startTime'  => $start->toIso8601ZuluString(),
            'endTime'    => $end->toIso8601ZuluString(),
        ]);

        return [
            'synced'      => $synced,
            'accounts'    => count($accounts),
            'startTime'   => $start->toIso8601ZuluString(),
            'endTime'     => $end->toIso8601ZuluString(),
            'days'        => $days,
            'durationSec' => round(microtime(true) - $started, 2),
        ];
    }

    /**
     * Pull from GoTo and upsert into local DB.
     * Returns the number of rows newly inserted.
     */
    public function syncFromGoTo(array $params): int
    {
        $accounts = $this->resolveAccountsForSync($params);
        if (empty($accounts)) {
            return 0;
        }

        $startTime = $this->normalizeIso($params['startTime'] ?? null);
        $endTime   = $this->normalizeIso($params['endTime']   ?? null);
        $endpoint  = config('goto.endpoints.call_events_summaries');

        $insertedTotal = 0;

        // GoTo /call-events-report/v1/report-summaries caps the date range at 31 days.
        // Split the requested window into <=30 day chunks to be safe.
        $chunks = $this->buildDateChunks($startTime, $endTime, 30);

        foreach ($accounts as $account) {
            $accountKey     = (string) $account['accountKey'];
            $organizationId = $account['organizationId'] ?? null;
            $accountName    = $this->nameResolver->resolve($accountKey, $organizationId);

            foreach ($chunks as $chunk) {
                try {
                    $this->apiClient->setAccountOverride($accountKey, $organizationId);

                    $query = array_filter([
                        'startTime' => $chunk['start'],
                        'endTime'   => $chunk['end'],
                    ], fn ($v) => $v !== null && $v !== '');

                    $batch = [];
                    foreach ($this->apiClient->getPaginated($endpoint, $query) as $row) {
                        $mapped = $this->mapApiRow($row, $accountKey, $organizationId, $accountName);
                        if ($mapped === null) {
                            continue;
                        }
                        $batch[] = $mapped;

                        if (count($batch) >= 500) {
                            $insertedTotal += $this->upsertBatch($batch);
                            $batch = [];
                        }
                    }
                    if (!empty($batch)) {
                        $insertedTotal += $this->upsertBatch($batch);
                    }
                } catch (Throwable $e) {
                    Log::warning('ConversationsReport: sync failed for account chunk', [
                        'accountKey' => $accountKey,
                        'startTime'  => $chunk['start'],
                        'endTime'    => $chunk['end'],
                        'error'      => $e->getMessage(),
                    ]);
                } finally {
                    $this->apiClient->clearAccountOverride();
                }
            }
        }

        return $insertedTotal;
    }

    /**
     * Split [start, end] into consecutive chunks no larger than $maxDays.
     * If either bound is missing the original (single, unbounded) range is returned.
     *
     * @return array<int,array{start:?string,end:?string}>
     */
    private function buildDateChunks(?string $startIso, ?string $endIso, int $maxDays): array
    {
        if (!$startIso || !$endIso) {
            return [['start' => $startIso, 'end' => $endIso]];
        }

        try {
            $start = Carbon::parse($startIso)->utc();
            $end   = Carbon::parse($endIso)->utc();
        } catch (Throwable) {
            return [['start' => $startIso, 'end' => $endIso]];
        }

        if ($end->lessThanOrEqualTo($start)) {
            return [['start' => $startIso, 'end' => $endIso]];
        }

        $chunks = [];
        $cursor = $start->copy();
        while ($cursor->lessThan($end)) {
            $next = $cursor->copy()->addDays($maxDays);
            if ($next->greaterThan($end)) {
                $next = $end->copy();
            }
            $chunks[] = [
                'start' => $cursor->toIso8601ZuluString(),
                'end'   => $next->toIso8601ZuluString(),
            ];
            $cursor = $next;
        }
        return $chunks;
    }

    /**
     * Read from local DB with filters applied.
     *
     * @return array<int,array<string,mixed>>
     */
    public function query(array $params): array
    {
        $q = CallEventSummary::query();

        if (!empty($params['accountKey']) && $params['accountKey'] !== 'all') {
            $q->where('account_key', (string) $params['accountKey']);
        }
        if (!empty($params['organizationId'])) {
            $q->where('organization_id', (string) $params['organizationId']);
        }
        if (!empty($params['accountName'])) {
            $q->where('account_name', (string) $params['accountName']);
        }
        if (!empty($params['startTime'])) {
            $q->where('call_created', '>=', Carbon::parse($params['startTime']));
        }
        if (!empty($params['endTime'])) {
            $q->where('call_created', '<=', Carbon::parse($params['endTime']));
        }

        $q->orderByDesc('call_created');

        if (!empty($params['limit'])) {
            $q->limit((int) $params['limit']);
        }

        return $q->get()->map(fn (CallEventSummary $r) => [
            'Account Key'     => $r->account_key,
            'Organization ID' => $r->organization_id,
            'Account Name'    => $r->account_name,
            'Date'            => $r->call_created?->toIso8601String(),
            'Duration'        => $this->formatDuration($r->duration_ms),
            'Call Result'     => $this->mapCallerOutcome((string) $r->caller_outcome),
            'From'            => $r->caller_number,
            'Participants'    => $r->participants,
        ])->all();
    }

    /**
     * List account names available for filtering.
     *
     * Source of truth is the `goto_accounts` table (one row per account on the
     * authenticated token, populated by AccountNameResolver). This guarantees the
     * dropdown shows every account the user has access to in GoTo, not just the
     * subset that happens to have call data synced into `call_event_summaries`.
     * Any orphan names that exist only in summaries are merged in defensively.
     */
    public function listAccountNames(): array
    {
        $primary = GotoAccount::query()
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $fromSummaries = CallEventSummary::query()
            ->whereNotNull('account_name')
            ->where('account_name', '!=', '')
            ->distinct()
            ->pluck('account_name')
            ->all();

        $merged = array_values(array_unique(array_merge($primary, $fromSummaries)));
        sort($merged, SORT_NATURAL | SORT_FLAG_CASE);
        return $merged;
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    /**
     * Decide which accounts to pull from GoTo for the sync step.
     * If accountKey is provided (and != 'all'), sync only that one;
     * otherwise sync everything available to the token.
     */
    private function resolveAccountsForSync(array $params): array
    {
        $explicit = $params['accountKey'] ?? null;
        if (!empty($explicit) && $explicit !== 'all') {
            $orgId = $params['organizationId'] ?? null;
            if (empty($orgId)) {
                $orgId = $this->authService->resolveOrganizationId((string) $explicit);
            }
            return [[
                'accountKey'     => (string) $explicit,
                'organizationId' => $orgId,
            ]];
        }

        return $this->authService->getAccountsWithOrganizations();
    }

    /**
     * Translate one GoTo summary item into a row for the call_event_summaries table.
     */
    private function mapApiRow(array $row, string $accountKey, ?string $organizationId, ?string $accountName): ?array
    {
        $convId = $row['conversationSpaceId'] ?? null;
        if (!$convId) {
            return null;
        }

        $created  = $row['callCreated']  ?? null;
        $answered = $row['callAnswered'] ?? null;
        $ended    = $row['callEnded']    ?? null;

        return [
            'conversation_space_id' => (string) $convId,
            'account_key'           => $accountKey,
            'organization_id'       => $organizationId,
            'account_name'          => $accountName,
            'call_created'          => $created  ? Carbon::parse($created)  : null,
            'call_answered'         => $answered ? Carbon::parse($answered) : null,
            'call_ended'            => $ended    ? Carbon::parse($ended)    : null,
            'duration_ms'           => $this->msBetween($created, $ended),
            'direction'             => $row['direction'] ?? null,
            'caller_outcome'        => $row['callerOutcome'] ?? null,
            'call_initiator'        => $row['callInitiator'] ?? null,
            'caller_number'         => $row['caller']['number'] ?? null,
            'caller_name'           => $row['caller']['name'] ?? null,
            'call_provider'         => $row['caller']['type']['callProvider'] ?? null,
            'participants'          => $this->formatParticipants($row),
            'raw'                   => json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at'            => Carbon::now(),
            'updated_at'            => Carbon::now(),
        ];
    }

    /**
     * Insert-only upsert: existing conversation_space_id rows are kept as-is
     * (no overwrite), new rows are added. Returns number of new rows.
     */
    private function upsertBatch(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $before = (int) DB::table('call_event_summaries')->count();

        // Postgres ON CONFLICT DO NOTHING via insertOrIgnore — keeps history
        // immutable; new rows are added without touching existing ones.
        DB::table('call_event_summaries')->insertOrIgnore($rows);

        $after = (int) DB::table('call_event_summaries')->count();
        return max(0, $after - $before);
    }

    /**
     * Build the combined Participants column to mirror GoTo's native CSV.
     * (Same logic as CallEventsSummaryReportService::formatParticipants.)
     */
    private function formatParticipants(array $row): string
    {
        $entries = [];

        foreach ($row['participants'] ?? [] as $p) {
            $entries[] = $this->participantEntry(
                (string) ($p['name'] ?? ''),
                (string) ($p['number'] ?? ''),
                (string) ($p['type']['extensionNumber'] ?? '')
            );
        }

        $callerType = $row['caller']['type'] ?? [];
        $entries[] = $this->participantEntry(
            (string) ($callerType['name'] ?? ''),
            (string) ($callerType['number'] ?? ''),
            (string) ($callerType['extensionNumber'] ?? '')
        );

        $entries[] = $this->participantEntry(
            '',
            (string) ($row['caller']['number'] ?? ''),
            ''
        );

        $seen = [];
        $out  = [];
        foreach ($entries as $e) {
            if ($e === '' || isset($seen[$e])) {
                continue;
            }
            $seen[$e] = true;
            $out[]    = $e;
        }
        return implode(';', $out);
    }

    private function participantEntry(string $name, string $number, string $extension): string
    {
        $identifier = $extension !== '' ? $extension : $number;
        $name = trim($name);
        if ($identifier === '' && $name === '') {
            return '';
        }
        if ($identifier !== '' && $name !== '') {
            return $identifier . ': ' . $name;
        }
        return $identifier !== '' ? $identifier : $name;
    }

    private function msBetween(?string $startIso, ?string $endIso): ?int
    {
        if (!$startIso || !$endIso) {
            return null;
        }
        try {
            $start = Carbon::parse($startIso);
            $end   = Carbon::parse($endIso);
            return max(0, (int) round(($end->getPreciseTimestamp(3) - $start->getPreciseTimestamp(3))));
        } catch (Throwable) {
            return null;
        }
    }

    private function formatDuration(?int $ms): string
    {
        if ($ms === null) {
            return '';
        }
        $secs = (int) floor($ms / 1000);
        $h = (int) floor($secs / 3600);
        $m = (int) floor(($secs % 3600) / 60);
        $s = $secs % 60;

        return $h > 0
            ? sprintf('%02d:%02d:%02d', $h, $m, $s)
            : sprintf('%02d:%02d', $m, $s);
    }

    private function mapCallerOutcome(string $outcome): string
    {
        return match (strtoupper($outcome)) {
            'NORMAL'             => 'Normal',
            'MISSED'             => 'Missed',
            'VOICEMAIL'          => 'Voicemail',
            'LEFT_QUEUE'         => 'Left queue',
            'LEFT_PARKING_SPOT'  => 'Left parking spot',
            'LEFT_ON_HOLD'       => 'Left on hold',
            'LEFT_DIAL_PLAN'     => 'Dial plan call ended',
            'TRUNCATED'          => 'Truncated',
            'UNKNOWN', ''        => '',
            default              => $outcome,
        };
    }

    private function normalizeIso(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Carbon::parse($value)->utc()->toIso8601ZuluString();
        } catch (Throwable) {
            return $value;
        }
    }
}
