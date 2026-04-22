<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GoTo\Reports\PhoneNumberActivityReportService;
use App\Services\GoTo\Reports\CallerActivityReportService;
use App\Services\GoTo\Reports\UserActivityReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for Call Reports (Aggregated Activity) endpoints.
 */
class CallReportsController
{
    /**
     * Export phone number activity summary as CSV.
     */
    public function phoneNumberActivitySummary(
        Request $request,
        PhoneNumberActivityReportService $reportService
    ): StreamedResponse {
        $params = $this->extractReportParams($request);
        return $this->streamForAccount($reportService, $params);
    }

    /**
     * Export phone number activity details as CSV.
     */
    public function phoneNumberActivityDetails(
        Request $request,
        PhoneNumberActivityReportService $reportService,
        string $phoneNumberId
    ): StreamedResponse {
        $params = $this->extractReportParams($request);
        $params['phoneNumberId'] = $phoneNumberId;
        return $this->streamForAccount($reportService, $params);
    }

    /**
     * Export caller activity summary as CSV.
     */
    public function callerActivitySummary(
        Request $request,
        CallerActivityReportService $reportService
    ): StreamedResponse {
        $params = $this->extractReportParams($request);
        return $this->streamForAccount($reportService, $params);
    }

    /**
     * Export caller activity details as CSV.
     */
    public function callerActivityDetails(
        Request $request,
        CallerActivityReportService $reportService,
        string $callerNumber
    ): StreamedResponse {
        $params = $this->extractReportParams($request);
        $params['callerNumber'] = $callerNumber;
        return $this->streamForAccount($reportService, $params);
    }

    /**
     * Export user activity summary as CSV.
     */
    public function userActivitySummary(
        Request $request,
        UserActivityReportService $reportService
    ): StreamedResponse {
        $params = $this->extractReportParams($request);
        return $this->streamForAccount($reportService, $params);
    }

    /**
     * Export user activity details as CSV.
     */
    public function userActivityDetails(
        Request $request,
        UserActivityReportService $reportService,
        string $userId
    ): StreamedResponse {
        $params = $this->extractReportParams($request);
        $params['userId'] = $userId;
        return $this->streamForAccount($reportService, $params);
    }

    /**
     * Extract common report parameters from request.
     */
    private function extractReportParams(Request $request): array
    {
        return [
            'startTime' => $request->query('startTime', $request->query('start_date')),
            'endTime' => $request->query('endTime', $request->query('end_date')),
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'accountKey' => $request->query('accountKey'),
        ];
    }

    private function streamForAccount($reportService, array $params): StreamedResponse
    {
        if (($params['accountKey'] ?? null) === 'all') {
            return $reportService->streamMultiAccountCsv($params);
        }
        return $reportService->streamCsv($params);
    }
}
