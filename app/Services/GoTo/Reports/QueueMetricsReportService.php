<?php

declare(strict_types=1);

namespace App\Services\GoTo\Reports;

use App\Services\GoTo\GoToAuthService;
use Generator;

/**
 * Service for Queue Metrics report (Contact Center Analytics).
 */
class QueueMetricsReportService extends BaseReportService
{
    protected string $reportName = 'queue_metrics';
    protected string $endpoint;
    private GoToAuthService $authService;

    public function __construct($apiClient, GoToAuthService $authService)
    {
        parent::__construct($apiClient);
        $this->authService = $authService;
        $this->endpoint = config('goto.endpoints.queue_metrics');
    }

    /**
     * Get queue metrics data.
     * This is a POST endpoint that requires a request body.
     */
    public function getData(array $params = []): Generator
    {
        $accountKey = $params['accountKey'] ?? $this->authService->getAccountKey();
        
        if (empty($accountKey)) {
            throw new \InvalidArgumentException('Account key is required for Contact Center Analytics reports');
        }

        $endpoint = str_replace('{accountKey}', $accountKey, $this->endpoint);

        // Build request body for POST
        $requestBody = [
            'startTime' => $params['startTime'] ?? date('Y-m-d\T00:00:00\Z', strtotime('-30 days')),
            'endTime' => $params['endTime'] ?? date('Y-m-d\T23:59:59\Z'),
        ];

        if (!empty($params['queueIds'])) {
            $requestBody['queueIds'] = is_array($params['queueIds']) 
                ? $params['queueIds'] 
                : explode(',', $params['queueIds']);
        }

        // interval and timezone must be query params, not in the POST body
        $query = [];
        if (!empty($params['interval'])) {
            $query['interval'] = $params['interval'];
        }
        if (!empty($params['timezone'])) {
            $query['timezone'] = $params['timezone'];
        }

        yield from $this->apiClient->postPaginated($endpoint, $requestBody, $query);
    }

    /**
     * Get CSV headers for queue metrics report.
     */
    public function getCsvHeaders(): array
    {
        return [
            'Queue ID',
            'Period Start',
            'Period End',
            'Total Calls',
            'Handled Calls',
            'Abandoned Calls',
            'Outflowed (Customer)',
            'Outflowed (System)',
            'Outflowed (User)',
            'Total Talk Time (s)',
            'Total Hold Time (s)',
            'Total Wrap Up Time (s)',
            'Service Level (%)',
        ];
    }

    /**
     * Transform a single data row into CSV format.
     */
    public function transformRow(array $row): array
    {
        return [
            $this->getNestedValue($row, 'queueId', ''),
            $this->formatTimestamp($this->getNestedValue($row, 'periodStart', '')),
            $this->formatTimestamp($this->getNestedValue($row, 'periodEnd', '')),
            $this->getNestedValue($row, 'totalCalls', 0),
            $this->getNestedValue($row, 'handledCalls', 0),
            $this->getNestedValue($row, 'abandonedCalls', 0),
            $this->getNestedValue($row, 'outflowedCalls.customer', 0),
            $this->getNestedValue($row, 'outflowedCalls.system', 0),
            $this->getNestedValue($row, 'outflowedCalls.user', 0),
            $this->getNestedValue($row, 'totalTalkTimeSec', 0),
            $this->getNestedValue($row, 'totalHoldTimeSec', 0),
            $this->getNestedValue($row, 'totalWrapUpTimeSec', 0),
            $this->formatPercentage($this->getNestedValue($row, 'serviceLevelPercent', 0)),
        ];
    }

    /**
     * Format a percentage value.
     */
    private function formatPercentage(mixed $value): string
    {
        if (is_numeric($value)) {
            return number_format((float) $value, 2) . '%';
        }
        return '0.00%';
    }

    /**
     * Get the filename for CSV export.
     */
    public function getFilename(array $params = []): string
    {
        $timestamp = date('Y-m-d_His');
        $interval = $params['interval'] ?? 'summary';
        return "queue_metrics_{$interval}_{$timestamp}.csv";
    }
}
