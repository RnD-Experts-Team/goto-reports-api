<?php

declare(strict_types=1);

namespace App\Services\GoTo\Reports;

use App\Services\GoTo\GoToAuthService;
use Generator;

/**
 * Service for Agent Statuses report (Contact Center Analytics).
 */
class AgentStatusesReportService extends BaseReportService
{
    protected string $reportName = 'agent_statuses';
    protected string $endpoint;
    private GoToAuthService $authService;

    public function __construct($apiClient, GoToAuthService $authService)
    {
        parent::__construct($apiClient);
        $this->authService = $authService;
        $this->endpoint = config('goto.endpoints.agent_statuses');
    }

    /**
     * Get agent statuses data.
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

        if (!empty($params['agentIds'])) {
            $requestBody['agentIds'] = is_array($params['agentIds']) 
                ? $params['agentIds'] 
                : explode(',', $params['agentIds']);
        }

        if (!empty($params['queueIds'])) {
            $requestBody['queueIds'] = is_array($params['queueIds']) 
                ? $params['queueIds'] 
                : explode(',', $params['queueIds']);
        }

        // timezone must be a query param, not in the POST body
        $query = [];
        if (!empty($params['timezone'])) {
            $query['timezone'] = $params['timezone'];
        }

        yield from $this->apiClient->postPaginated($endpoint, $requestBody, $query);
    }

    /**
     * Get CSV headers for agent statuses report.
     */
    public function getCsvHeaders(): array
    {
        return [
            'Agent ID',
            'Agent Name',
            'Agent Email',
            'Agent Extension',
            'Queue ID',
            'Queue Name',
            'Period Start',
            'Period End',
            'Status',
            'Status Duration (seconds)',
            'Logged In Duration (seconds)',
            'Available Duration (seconds)',
            'Unavailable Duration (seconds)',
            'On Call Duration (seconds)',
            'After Call Work Duration (seconds)',
            'Break Duration (seconds)',
            'Total Calls Handled',
            'Calls Answered',
            'Calls Missed',
            'Calls Transferred',
            'Average Handle Time (seconds)',
            'Average Talk Time (seconds)',
            'Average Hold Time (seconds)',
            'Average Wrap Up Time (seconds)',
            'Occupancy Rate (%)',
            'Utilization Rate (%)',
        ];
    }

    /**
     * Transform a single data row into CSV format.
     */
    public function transformRow(array $row): array
    {
        return [
            $this->getNestedValue($row, 'agentId', ''),
            $this->getNestedValue($row, 'agentName', ''),
            $this->getNestedValue($row, 'agentEmail', ''),
            $this->getNestedValue($row, 'agentExtension', ''),
            $this->getNestedValue($row, 'queueId', ''),
            $this->getNestedValue($row, 'queueName', ''),
            $this->formatTimestamp($this->getNestedValue($row, 'periodStart', '')),
            $this->formatTimestamp($this->getNestedValue($row, 'periodEnd', '')),
            $this->getNestedValue($row, 'status', ''),
            $this->getNestedValue($row, 'statusDuration', 0),
            $this->getNestedValue($row, 'loggedInDuration', 0),
            $this->getNestedValue($row, 'availableDuration', 0),
            $this->getNestedValue($row, 'unavailableDuration', 0),
            $this->getNestedValue($row, 'onCallDuration', 0),
            $this->getNestedValue($row, 'afterCallWorkDuration', 0),
            $this->getNestedValue($row, 'breakDuration', 0),
            $this->getNestedValue($row, 'totalCallsHandled', 0),
            $this->getNestedValue($row, 'callsAnswered', 0),
            $this->getNestedValue($row, 'callsMissed', 0),
            $this->getNestedValue($row, 'callsTransferred', 0),
            $this->getNestedValue($row, 'averageHandleTime', 0),
            $this->getNestedValue($row, 'averageTalkTime', 0),
            $this->getNestedValue($row, 'averageHoldTime', 0),
            $this->getNestedValue($row, 'averageWrapUpTime', 0),
            $this->formatPercentage($this->getNestedValue($row, 'occupancyRate', 0)),
            $this->formatPercentage($this->getNestedValue($row, 'utilizationRate', 0)),
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
        return "agent_statuses_{$timestamp}.csv";
    }
}
