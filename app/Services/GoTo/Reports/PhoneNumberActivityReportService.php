<?php

declare(strict_types=1);

namespace App\Services\GoTo\Reports;

use Generator;

/**
 * Service for Phone Number Activity reports.
 */
class PhoneNumberActivityReportService extends BaseReportService
{
    protected string $reportName = 'phone_number_activity';
    protected string $endpoint;

    public function __construct($apiClient)
    {
        parent::__construct($apiClient);
        $this->endpoint = config('goto.endpoints.phone_number_activity');
    }

    /**
     * Get phone number activity data.
     */
    public function getData(array $params = []): Generator
    {
        $query = $this->buildDateRangeParams($params);

        // Handle detail request for specific phone number
        if (!empty($params['phoneNumberId'])) {
            $detailEndpoint = $this->endpoint . '/' . $params['phoneNumberId'];
            yield from $this->apiClient->getPaginated($detailEndpoint, $query);
        } else {
            yield from $this->apiClient->getPaginated($this->endpoint, $query);
        }
    }

    /**
     * Get CSV headers for phone number activity report.
     */
    public function getCsvHeaders(): array
    {
        return [
            'Phone Number ID',
            'Phone Number',
            'Phone Number Name',
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
            $this->getNestedValue($row, 'phoneNumberId', ''),
            $this->getNestedValue($row, 'phoneNumber', ''),
            $this->getNestedValue($row, 'phoneNumberName', ''),
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
        
        if (!empty($params['phoneNumberId'])) {
            return "phone_number_activity_detail_{$params['phoneNumberId']}_{$timestamp}.csv";
        }
        
        return "phone_number_activity_summary_{$timestamp}.csv";
    }
}
