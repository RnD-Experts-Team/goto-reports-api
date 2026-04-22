<?php

declare(strict_types=1);

namespace App\Services\GoTo\Reports;

use App\Services\GoTo\GoToAuthService;
use Generator;

/**
 * Service for Queue Caller Details report (Contact Center Analytics).
 */
class QueueCallerDetailsReportService extends BaseReportService
{
    protected string $reportName = 'queue_caller_details';
    protected string $endpoint;
    private GoToAuthService $authService;

    public function __construct($apiClient, GoToAuthService $authService)
    {
        parent::__construct($apiClient);
        $this->authService = $authService;
        $this->endpoint = config('goto.endpoints.queue_caller_details');
    }

    /**
     * Get queue caller details data.
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

        // timezone must be a query param, not in the POST body
        $query = [];
        if (!empty($params['timezone'])) {
            $query['timezone'] = $params['timezone'];
        }

        yield from $this->apiClient->postPaginated($endpoint, $requestBody, $query);
    }

    /**
     * Get CSV headers for queue caller details report.
     */
    public function getCsvHeaders(): array
    {
        return [
            'Start Time',
            'Conversation Space IDs',
            'Outcome',
            'Left Queue Reason',
            'Media Type',
            'Direction',
            'Dialed Number',
            'Dialed Number Name',
            'Caller Name',
            'Caller Number',
            'Queue ID',
            'Queue Name',
            'Agent Name',
            'Wait Time (s)',
            'Talk Duration (s)',
            'Duration (s)',
            'Agent Ring Time (s)',
            'Met Service Level',
            'Callback Offered',
            'Callback Requested',
            'SMS Sent',
            'Tags',
        ];
    }

    /**
     * Transform a single data row into CSV format.
     */
    public function transformRow(array $row): array
    {
        $conversationIds = $row['conversationSpaceIds'] ?? [];
        $tags = $row['tags'] ?? [];

        return [
            $this->formatTimestamp($this->getNestedValue($row, 'startTime', '')),
            implode(', ', $conversationIds),
            $this->getNestedValue($row, 'outcome', ''),
            $this->getNestedValue($row, 'leftQueueReason', ''),
            $this->getNestedValue($row, 'mediaType', ''),
            $this->getNestedValue($row, 'direction', ''),
            $this->getNestedValue($row, 'dialedNumber', ''),
            $this->getNestedValue($row, 'dialedNumberName', ''),
            $this->getNestedValue($row, 'caller.name', ''),
            $this->getNestedValue($row, 'caller.number', ''),
            $this->getNestedValue($row, 'queue.id', ''),
            $this->getNestedValue($row, 'queue.name', ''),
            $this->getNestedValue($row, 'agent.name', ''),
            $this->getNestedValue($row, 'waitTime', 0),
            $this->getNestedValue($row, 'talkDuration', 0),
            $this->getNestedValue($row, 'duration', 0),
            $this->getNestedValue($row, 'agentRingTime', 0),
            $this->getNestedValue($row, 'metServiceLevel', ''),
            $this->getNestedValue($row, 'callbackOffered', false) ? 'Yes' : 'No',
            $this->getNestedValue($row, 'callbackRequested', false) ? 'Yes' : 'No',
            $this->getNestedValue($row, 'smsSent', false) ? 'Yes' : 'No',
            implode(', ', $tags),
        ];
    }

    /**
     * Get the filename for CSV export.
     */
    public function getFilename(array $params = []): string
    {
        $timestamp = date('Y-m-d_His');
        return "queue_caller_details_{$timestamp}.csv";
    }
}
