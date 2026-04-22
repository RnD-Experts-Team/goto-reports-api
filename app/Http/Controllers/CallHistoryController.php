<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GoTo\Reports\CallHistoryReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for Call History endpoints.
 */
class CallHistoryController
{
    /**
     * Export call history as CSV.
     */
    public function index(
        Request $request,
        CallHistoryReportService $reportService
    ): StreamedResponse {
        $params = [
            'startTime' => $request->query('startTime', $request->query('start_date')),
            'endTime' => $request->query('endTime', $request->query('end_date')),
            'direction' => $request->query('direction'),
            'result' => $request->query('result'),
            'userKey' => $request->query('userKey'),
            'accountKey' => $request->query('accountKey'),
        ];

        if (($params['accountKey'] ?? null) === 'all') {
            return $reportService->streamMultiAccountCsv($params);
        }
        return $reportService->streamCsv($params);
    }
}
