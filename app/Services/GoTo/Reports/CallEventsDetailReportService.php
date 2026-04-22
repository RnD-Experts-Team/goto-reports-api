<?php

declare(strict_types=1);

namespace App\Services\GoTo\Reports;

use Generator;

/**
 * Service for Call Events Detail reports (by conversation).
 */
class CallEventsDetailReportService extends BaseReportService
{
    protected string $reportName = 'call_events_detail';
    protected string $endpoint;

    public function __construct($apiClient)
    {
        parent::__construct($apiClient);
        $this->endpoint = config('goto.endpoints.call_events_details');
    }

    /**
     * Get call events detail data for a specific conversation.
     */
    public function getData(array $params = []): Generator
    {
        if (empty($params['conversationSpaceId'])) {
            throw new \InvalidArgumentException('conversationSpaceId is required for call events detail report');
        }

        $endpoint = str_replace('{conversationSpaceId}', $params['conversationSpaceId'], $this->endpoint);
        yield from $this->apiClient->getPaginated($endpoint, []);
    }

    /**
     * Get CSV headers for call events detail report.
     */
    public function getCsvHeaders(): array
    {
        return [
            'Event ID',
            'Conversation Space ID',
            'Event Type',
            'Event Time',
            'Duration (seconds)',
            'Sequence Number',
            'User ID',
            'User Name',
            'User Extension',
            'Device',
            'From Number',
            'From Name',
            'To Number',
            'To Name',
            'Call Direction',
            'Call Result',
            'Hold Duration (seconds)',
            'Ring Duration (seconds)',
            'Talk Duration (seconds)',
            'Transfer Type',
            'Transfer Target',
            'Recording URL',
            'Notes',
        ];
    }

    /**
     * Transform a single data row into CSV format.
     */
    public function transformRow(array $row): array
    {
        return [
            $this->getNestedValue($row, 'eventId', ''),
            $this->getNestedValue($row, 'conversationSpaceId', ''),
            $this->getNestedValue($row, 'eventType', ''),
            $this->formatTimestamp($this->getNestedValue($row, 'eventTime', '')),
            $this->getNestedValue($row, 'duration', 0),
            $this->getNestedValue($row, 'sequenceNumber', 0),
            $this->getNestedValue($row, 'user.id', ''),
            $this->getNestedValue($row, 'user.name', ''),
            $this->getNestedValue($row, 'user.extension', ''),
            $this->getNestedValue($row, 'device', ''),
            $this->getNestedValue($row, 'from.number', ''),
            $this->getNestedValue($row, 'from.name', ''),
            $this->getNestedValue($row, 'to.number', ''),
            $this->getNestedValue($row, 'to.name', ''),
            $this->getNestedValue($row, 'direction', ''),
            $this->getNestedValue($row, 'result', ''),
            $this->getNestedValue($row, 'holdDuration', 0),
            $this->getNestedValue($row, 'ringDuration', 0),
            $this->getNestedValue($row, 'talkDuration', 0),
            $this->getNestedValue($row, 'transfer.type', ''),
            $this->getNestedValue($row, 'transfer.target', ''),
            $this->getNestedValue($row, 'recording.url', ''),
            $this->getNestedValue($row, 'notes', ''),
        ];
    }

    /**
     * Get the filename for CSV export.
     */
    public function getFilename(array $params = []): string
    {
        $timestamp = date('Y-m-d_His');
        $conversationId = $params['conversationSpaceId'] ?? 'unknown';
        return "call_events_detail_{$conversationId}_{$timestamp}.csv";
    }
}
