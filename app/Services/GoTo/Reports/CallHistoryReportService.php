<?php

declare(strict_types=1);

namespace App\Services\GoTo\Reports;

use Generator;
use Illuminate\Support\Facades\Log;

/**
 * Service for Call History reports.
 *
 * IMPORTANT: GoTo's /call-history/v1/calls endpoint is per-user; it returns calls
 * only for the user that owns the OAuth token unless `userKey` is supplied.
 * To get account-wide history, we enumerate all users in the account and merge.
 */
class CallHistoryReportService extends BaseReportService
{
    protected string $reportName = 'call_history';
    protected string $endpoint;

    public function __construct($apiClient)
    {
        parent::__construct($apiClient);
        $this->endpoint = config('goto.endpoints.call_history');
    }

    /**
     * Get call history data.
     *
     * - If a specific userKey is supplied, fetch only that user's calls.
     * - Otherwise, list all users in the active account and stream their calls in turn.
     */
    public function getData(array $params = []): Generator
    {
        $baseQuery = $this->buildDateRangeParams($params);
        if (!empty($params['direction'])) { $baseQuery['direction'] = $params['direction']; }
        if (!empty($params['result']))    { $baseQuery['result']    = $params['result']; }

        // Caller already filtered to a single user
        if (!empty($params['userKey'])) {
            $baseQuery['userKey'] = $params['userKey'];
            yield from $this->apiClient->getPaginated($this->endpoint, $baseQuery);
            return;
        }

        // Otherwise, enumerate all users for the (possibly overridden) account
        $userKeys = $this->listAllUserKeys($params);
        if (empty($userKeys)) {
            Log::warning('Call History: no users found for account', ['params' => $params]);
            return;
        }

        foreach ($userKeys as $userKey) {
            $q = $baseQuery;
            $q['userKey'] = $userKey;
            try {
                foreach ($this->apiClient->getPaginated($this->endpoint, $q) as $row) {
                    // Preserve the userKey on each row in case the API omits it
                    if (!isset($row['userKey'])) {
                        $row['userKey'] = $userKey;
                    }
                    yield $row;
                }
            } catch (\Throwable $e) {
                // Skip users that error out (e.g. 404), continue with the rest
                Log::warning('Call History: skipping user due to error', [
                    'userKey' => $userKey,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }
    }

    /**
     * List all userKeys in the active account by paging /users/v1/users.
     */
    private function listAllUserKeys(array $params): array
    {
        $accountKey = $params['accountKey'] ?? null;
        $query = ['pageSize' => 100];
        if ($accountKey) {
            $query['accountKey'] = $accountKey;
        }

        $userKeys = [];
        try {
            // /users/v1/users supports pagination via getPaginated (extracts items[])
            foreach ($this->apiClient->getPaginated('/users/v1/users', $query) as $user) {
                if (!empty($user['userKey'])) {
                    $userKeys[] = (string) $user['userKey'];
                }
            }
        } catch (\Throwable $e) {
            Log::error('Call History: failed to list users', ['error' => $e->getMessage()]);
        }

        return array_values(array_unique($userKeys));
    }

    /**
     * Get CSV headers for call history report.
     */
    public function getCsvHeaders(): array
    {
        return [
            'Call ID',
            'Conversation ID',
            'Direction',
            'Call Type',
            'Start Time',
            'End Time',
            'Duration (seconds)',
            'Result',
            'Caller Number',
            'Caller Name',
            'Callee Number',
            'Callee Name',
            'User ID',
            'User Name',
            'User Extension',
            'Device',
            'Line',
            'Recording Available',
            'Recording URL',
            'Voicemail Available',
            'Transfer Type',
            'Transfer Target',
            'Queue Name',
        ];
    }

    /**
     * Transform a single data row into CSV format.
     */
    public function transformRow(array $row): array
    {
        return [
            $this->getNestedValue($row, 'callId', $this->getNestedValue($row, 'id', '')),
            $this->getNestedValue($row, 'conversationId', $this->getNestedValue($row, 'legId', '')),
            $this->getNestedValue($row, 'direction', ''),
            $this->getNestedValue($row, 'callType', ''),
            $this->formatTimestamp($this->getNestedValue($row, 'startTime', '')),
            $this->formatTimestamp($this->getNestedValue($row, 'endTime', '')),
            $this->getNestedValue($row, 'duration', 0),
            $this->getNestedValue($row, 'result', $this->getNestedValue($row, 'callOutcome', '')),
            $this->getNestedValue($row, 'caller.number', $this->getNestedValue($row, 'callerNumber', '')),
            $this->getNestedValue($row, 'caller.name', $this->getNestedValue($row, 'callerName', '')),
            $this->getNestedValue($row, 'callee.number', $this->getNestedValue($row, 'calleeNumber', '')),
            $this->getNestedValue($row, 'callee.name', $this->getNestedValue($row, 'calleeName', '')),
            $this->getNestedValue($row, 'user.id', $this->getNestedValue($row, 'userKey', '')),
            $this->getNestedValue($row, 'user.name', ''),
            $this->getNestedValue($row, 'user.extension', ''),
            $this->getNestedValue($row, 'device', ''),
            $this->getNestedValue($row, 'line', ''),
            $this->getNestedValue($row, 'recording.available', false) ? 'Yes' : 'No',
            $this->getNestedValue($row, 'recording.url', ''),
            $this->getNestedValue($row, 'voicemail.available', false) ? 'Yes' : 'No',
            $this->getNestedValue($row, 'transfer.type', ''),
            $this->getNestedValue($row, 'transfer.target', ''),
            $this->getNestedValue($row, 'queue.name', ''),
        ];
    }

    /**
     * Get the filename for CSV export.
     */
    public function getFilename(array $params = []): string
    {
        $timestamp = date('Y-m-d_His');
        $direction = $params['direction'] ?? 'all';
        return "call_history_{$direction}_{$timestamp}.csv";
    }
}
