<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GoTo\Reports\CallEventsSummaryReportService;
use App\Services\GoTo\Reports\CallEventsDetailReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for Call Events Report endpoints.
 */
class CallEventsController
{
    /**
     * Export call events summaries as CSV.
     */
    public function summaries(
        Request $request,
        CallEventsSummaryReportService $reportService
    ): StreamedResponse {
        $params = [
            'startTime' => $request->query('startTime', $request->query('start_date')),
            'endTime' => $request->query('endTime', $request->query('end_date')),
            'accountKey' => $request->query('accountKey'),
        ];

        if (($params['accountKey'] ?? null) === 'all') {
            return $reportService->streamMultiAccountCsv($params);
        }
        return $reportService->streamCsv($params);
    }

    /**
     * Export call events details for a specific conversation as CSV.
     */
    public function details(
        Request $request,
        CallEventsDetailReportService $reportService,
        string $conversationSpaceId
    ): StreamedResponse {
        $params = [
            'conversationSpaceId' => $conversationSpaceId,
            'accountKey' => $request->query('accountKey'),
        ];

        if (($params['accountKey'] ?? null) === 'all') {
            return $reportService->streamMultiAccountCsv($params);
        }
        return $reportService->streamCsv($params);
    }
}
