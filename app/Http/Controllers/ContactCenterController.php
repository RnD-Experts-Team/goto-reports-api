<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GoTo\Reports\QueueCallerDetailsReportService;
use App\Services\GoTo\Reports\QueueMetricsReportService;
use App\Services\GoTo\Reports\AgentStatusesReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for Contact Center Analytics endpoints.
 */
class ContactCenterController
{
    /**
     * Export queue caller details as CSV.
     */
    public function queueCallerDetails(
        Request $request,
        QueueCallerDetailsReportService $reportService
    ): StreamedResponse {
        $params = $this->extractParams($request);
        
        // Queue-specific parameters
        if ($request->has('queueIds')) {
            $params['queueIds'] = $request->query('queueIds');
        }

        return $this->streamForAccount($reportService, $params);
    }

    /**
     * Export queue metrics as CSV.
     */
    public function queueMetrics(
        Request $request,
        QueueMetricsReportService $reportService
    ): StreamedResponse {
        $params = $this->extractParams($request);
        
        // Queue-specific parameters
        if ($request->has('queueIds')) {
            $params['queueIds'] = $request->query('queueIds');
        }
        if ($request->has('interval')) {
            $params['interval'] = $request->query('interval');
        }

        return $this->streamForAccount($reportService, $params);
    }

    /**
     * Export agent statuses as CSV.
     */
    public function agentStatuses(
        Request $request,
        AgentStatusesReportService $reportService
    ): StreamedResponse {
        $params = $this->extractParams($request);
        
        // Agent-specific parameters
        if ($request->has('agentIds')) {
            $params['agentIds'] = $request->query('agentIds');
        }
        if ($request->has('queueIds')) {
            $params['queueIds'] = $request->query('queueIds');
        }

        return $this->streamForAccount($reportService, $params);
    }

    private function streamForAccount($reportService, array $params): StreamedResponse
    {
        if (($params['accountKey'] ?? null) === 'all') {
            return $reportService->streamMultiAccountCsv($params);
        }
        return $reportService->streamCsv($params);
    }

    /**
     * Extract common parameters from request.
     */
    private function extractParams(Request $request): array
    {
        $params = [
            'startTime' => $request->query('startTime', $request->query('start_date')),
            'endTime' => $request->query('endTime', $request->query('end_date')),
            'accountKey' => $request->query('accountKey'),
        ];

        if ($request->has('timezone')) {
            $params['timezone'] = $request->query('timezone');
        }

        return $params;
    }
}
