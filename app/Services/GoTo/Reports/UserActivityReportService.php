<?php

declare(strict_types=1);

namespace App\Services\GoTo\Reports;

use Generator;

/**
 * Service for User Activity reports.
 */
class UserActivityReportService extends BaseReportService
{
    protected string $reportName = 'user_activity';
    protected string $endpoint;

    public function __construct($apiClient)
    {
        parent::__construct($apiClient);
        $this->endpoint = config('goto.endpoints.user_activity');
    }

    /**
     * Get user activity data.
     */
    public function getData(array $params = []): Generator
    {
        $query = $this->buildDateRangeParams($params);

        // Handle detail request for specific user
        if (!empty($params['userId'])) {
            $detailEndpoint = $this->endpoint . '/' . $params['userId'];
            yield from $this->apiClient->getPaginated($detailEndpoint, $query);
        } else {
            yield from $this->apiClient->getPaginated($this->endpoint, $query);
        }
    }

    /**
     * Get CSV headers for user activity report.
     */
    public function getCsvHeaders(): array
    {
        return [
            'User ID',
            'User Name',
            'Total Calls',
            'Inbound Calls',
            'Inbound Duration (ms)',
            'Outbound Calls',
            'Outbound Duration (ms)',
            'Average Duration (ms)',
            'Total Duration (ms)',
            'Inbound Queue Volume',
        ];
    }

    /**
     * Transform a single data row into CSV format.
     */
    public function transformRow(array $row): array
    {
        return [
            $this->getNestedValue($row, 'userId', ''),
            $this->getNestedValue($row, 'userName', ''),
            $this->getNestedValue($row, 'dataValues.volume', 0),
            $this->getNestedValue($row, 'dataValues.inboundVolume', 0),
            $this->getNestedValue($row, 'dataValues.inboundDuration', 0),
            $this->getNestedValue($row, 'dataValues.outboundVolume', 0),
            $this->getNestedValue($row, 'dataValues.outboundDuration', 0),
            $this->getNestedValue($row, 'dataValues.averageDuration', 0),
            $this->getNestedValue($row, 'dataValues.totalDuration', 0),
            $this->getNestedValue($row, 'dataValues.inboundQueueVolume', 0),
        ];
    }

    /**
     * Get the filename for CSV export.
     */
    public function getFilename(array $params = []): string
    {
        $timestamp = date('Y-m-d_His');
        
        if (!empty($params['userId'])) {
            return "user_activity_detail_{$params['userId']}_{$timestamp}.csv";
        }
        
        return "user_activity_summary_{$timestamp}.csv";
    }
}
