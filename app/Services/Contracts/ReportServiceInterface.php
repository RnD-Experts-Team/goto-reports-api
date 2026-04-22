<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use Generator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Contract for report extraction services.
 */
interface ReportServiceInterface
{
    /**
     * Stream report data as CSV.
     */
    public function streamCsv(array $params = []): StreamedResponse;

    /**
     * Get report data as a generator for memory-efficient processing.
     */
    public function getData(array $params = []): Generator;

    /**
     * Get the CSV headers for this report.
     */
    public function getCsvHeaders(): array;

    /**
     * Transform a single data row into CSV format.
     */
    public function transformRow(array $row): array;

    /**
     * Get the filename for the CSV export.
     */
    public function getFilename(array $params = []): string;
}
