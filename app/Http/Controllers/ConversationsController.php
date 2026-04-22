<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GoTo\Reports\ConversationsReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * /api/reports/conversations — DB-backed conversations report.
 *
 * Pulls fresh data from GoTo, upserts into Postgres (insert-only on
 * conversation_space_id), then serves the requested rows from the local DB.
 */
class ConversationsController
{
    /**
     * GET /api/reports/conversations
     *
     * Query params:
     *   startTime, endTime    ISO-8601, optional
     *   accountKey            specific account key, or 'all' (default)
     *   accountName           e.g. 'LCF 3795-0027' — filters local rows only
     *   sync                  '1' (default) to refresh from GoTo, '0' to read DB only
     *   format                'json' (default) or 'csv'
     *   limit                 optional integer cap
     */
    public function index(Request $request, ConversationsReportService $service): JsonResponse|StreamedResponse
    {
        $shouldSync = $request->query('sync', '1') !== '0';

        // Default to the last 30 days when caller omits both bounds.
        [$startTime, $endTime] = $this->resolveDateRange(
            $request->query('startTime', $request->query('start_date')),
            $request->query('endTime',   $request->query('end_date')),
            30
        );

        $params = [
            'startTime'      => $startTime,
            'endTime'        => $endTime,
            'accountKey'     => $request->query('accountKey', 'all'),
            'organizationId' => $request->query('organizationId'),
            'accountName'    => $request->query('accountName'),
            'limit'          => $request->query('limit'),
        ];

        if ($shouldSync) {
            // Sync may need to fan out across many accounts × 30-day chunks.
            @set_time_limit(0);
            @ignore_user_abort(true);
        }

        $synced = $shouldSync ? $service->syncFromGoTo($params) : 0;
        $rows   = $service->query($params);

        if (strtolower((string) $request->query('format', 'json')) === 'csv') {
            return $this->streamCsv($rows, $params);
        }

        return response()->json([
            'count'   => count($rows),
            'synced'  => $synced,
            'filters' => array_filter($params, fn ($v) => $v !== null && $v !== ''),
            'data'    => $rows,
        ]);
    }

    /**
     * POST /api/reports/conversations/backfill
     *
     * Body / query params:
     *   days   integer, default 365 — how far back to pull
     *
     * Pulls every account on the current token for the requested historical
     * window (chunked into <=30 day requests internally). Long-running.
     */
    public function backfill(Request $request, ConversationsReportService $service): JsonResponse
    {
        $days = (int) $request->input('days', $request->query('days', 365));
        $days = max(1, min($days, 1825)); // clamp 1d..5y

        $result = $service->backfillAll($days);

        return response()->json([
            'ok'     => true,
            'result' => $result,
        ]);
    }

    /**
     * Resolve date bounds: if both omitted, default to last $defaultDays.
     * If only one provided, leave the other null (callers can decide).
     *
     * @return array{0:?string,1:?string}
     */
    private function resolveDateRange(?string $start, ?string $end, int $defaultDays): array
    {
        if ($start || $end) {
            return [$start, $end];
        }
        $endC   = now('UTC');
        $startC = $endC->copy()->subDays($defaultDays);
        return [$startC->toIso8601ZuluString(), $endC->toIso8601ZuluString()];
    }

    /**
     * GET /api/reports/conversations/account-names — distinct values for the
     * accountName filter dropdown.
     */
    public function accountNames(ConversationsReportService $service): JsonResponse
    {
        return response()->json(['data' => $service->listAccountNames()]);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function streamCsv(array $rows, array $params): StreamedResponse
    {
        $filename = sprintf(
            'conversations_%s.csv',
            date('Y-m-d_His')
        );

        $response = new StreamedResponse(function () use ($rows) {
            @set_time_limit(0);
            @ignore_user_abort(true);
            while (ob_get_level() > 0) { @ob_end_clean(); }

            $h = fopen('php://output', 'w');
            fputs($h, "\xEF\xBB\xBF");

            $headers = [
                'Account Key', 'Organization ID', 'Account Name',
                'Date', 'Duration', 'Call Result', 'From', 'Participants',
            ];
            fputcsv($h, $headers);

            foreach ($rows as $row) {
                fputcsv($h, [
                    $row['Account Key']     ?? '',
                    $row['Organization ID'] ?? '',
                    $row['Account Name']    ?? '',
                    $row['Date']            ?? '',
                    $row['Duration']        ?? '',
                    $row['Call Result']     ?? '',
                    $row['From']            ?? '',
                    $row['Participants']    ?? '',
                ]);
            }
            fclose($h);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        return $response;
    }
}
