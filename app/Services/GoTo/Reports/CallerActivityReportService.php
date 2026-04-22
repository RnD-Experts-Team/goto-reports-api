<?php

declare(strict_types=1);

namespace App\Services\GoTo\Reports;

use Generator;

/**
 * Service for Caller Activity reports.
 */
class CallerActivityReportService extends BaseReportService
{
    protected string $reportName = 'caller_activity';
    protected string $endpoint;

    public function __construct($apiClient)
    {
        parent::__construct($apiClient);
        $this->endpoint = config('goto.endpoints.caller_activity');
    }

    /**
     * Get caller activity data.
     */
    public function getData(array $params = []): Generator
    {
        $query = $this->buildDateRangeParams($params);

        // Handle detail request for specific caller number
        if (!empty($params['callerNumber'])) {
            $detailEndpoint = $this->endpoint . '/' . urlencode($params['callerNumber']);
            yield from $this->apiClient->getPaginated($detailEndpoint, $query);
        } else {
            yield from $this->apiClient->getPaginated($this->endpoint, $query);
        }
    }

    /**
     * Get CSV headers for caller activity report.
     */
    public function getCsvHeaders(): array
    {
        return [
            'Caller Number',
            'Caller Name',
            'Total Calls',
            'Inbound Calls',
            'Inbound Duration (ms)',
            'Outbound Calls',
            'Outbound Duration (ms)',
            'Average Duration (ms)',
            'Total Duration (ms)',
        ];
    }

    /**
     * Transform a single data row into CSV format.
     */
    public function transformRow(array $row): array
    {
        return [
            $this->getNestedValue($row, 'number', ''),
            $this->getNestedValue($row, 'name', ''),
            $this->getNestedValue($row, 'dataValues.volume', 0),
            $this->getNestedValue($row, 'dataValues.inboundVolume', 0),
            $this->getNestedValue($row, 'dataValues.inboundDuration', 0),
            $this->getNestedValue($row, 'dataValues.outboundVolume', 0),
            $this->getNestedValue($row, 'dataValues.outboundDuration', 0),
            $this->getNestedValue($row, 'dataValues.averageDuration', 0),
            $this->getNestedValue($row, 'dataValues.totalDuration', 0),
        ];
    }

    /**
     * Get the filename for CSV export.
     */
    public function getFilename(array $params = []): string
    {
        $timestamp = date('Y-m-d_His');
        
        if (!empty($params['callerNumber'])) {
            $sanitizedNumber = preg_replace('/[^0-9]/', '', $params['callerNumber']);
            return "caller_activity_detail_{$sanitizedNumber}_{$timestamp}.csv";
        }
        
        return "caller_activity_summary_{$timestamp}.csv";
    }
}
