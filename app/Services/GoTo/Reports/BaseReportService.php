<?php

declare(strict_types=1);

namespace App\Services\GoTo\Reports;

use App\Services\Contracts\GoToApiClientInterface;
use App\Services\Contracts\ReportServiceInterface;
use App\Services\GoTo\GoToApiClient;
use App\Services\GoTo\GoToAuthService;
use Generator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Abstract base class for report services providing common CSV streaming functionality.
 */
abstract class BaseReportService implements ReportServiceInterface
{
    protected GoToApiClientInterface $apiClient;
    protected string $reportName;
    protected string $endpoint;

    public function __construct(GoToApiClientInterface $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    /**
     * Stream report data as CSV with memory-efficient chunked output.
     */
    public function streamCsv(array $params = []): StreamedResponse
    {
        $filename = $this->getFilename($params);
        
        $response = new StreamedResponse(function () use ($params) {
            // Prevent PHP timeout/abort from injecting HTML error page into the CSV stream
            @set_time_limit(0);
            @ignore_user_abort(true);
            while (ob_get_level() > 0) { @ob_end_clean(); }

            // Open output stream
            $handle = fopen('php://output', 'w');
            
            // Set UTF-8 BOM for Excel compatibility
            fputs($handle, "\xEF\xBB\xBF");
            
            // Write CSV headers
            fputcsv($handle, $this->getCsvHeaders());
            
            try {
                // Stream data rows
                $rowCount = 0;
                foreach ($this->getData($params) as $row) {
                    $transformedRow = $this->transformRow($row);
                    // Sanitize: never write HTML into CSV cells
                    $transformedRow = $this->sanitizeCsvRow($transformedRow);
                    if ($transformedRow === null) { continue; }
                    fputcsv($handle, $transformedRow);
                    $rowCount++;
                    
                    // Flush output buffer periodically for large datasets
                    if ($rowCount % 1000 === 0) {
                        flush();
                    }
                }

                // Add a friendly indicator row when no data was returned
                if ($rowCount === 0) {
                    $note = array_fill(0, count($this->getCsvHeaders()), '');
                    $note[0] = 'NO DATA: GoTo API returned 0 records for the requested filters.';
                    fputcsv($handle, $note);
                }
            } catch (Throwable $e) {
                Log::error('CSV streaming error: ' . $e->getMessage(), [
                    'report' => $this->reportName,
                    'exception' => $e,
                ]);
                // Write a clean, single-line error in column 1 only
                $errorColumns = array_fill(0, count($this->getCsvHeaders()), '');
                $msg = preg_replace('/\s+/', ' ', strip_tags($e->getMessage()));
                $errorColumns[0] = 'ERROR: ' . $msg;
                fputcsv($handle, $errorColumns);
            }
            
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    /**
     * Stream CSV for all accounts, merging results with Account Key and Organization ID columns.
     * Failed accounts are skipped, with error summary rows appended at the end.
     */
    public function streamMultiAccountCsv(array $params = []): StreamedResponse
    {
        $filename = 'all_accounts_' . $this->getFilename($params);

        $response = new StreamedResponse(function () use ($params) {
            // Prevent PHP timeout/abort from injecting HTML error page into the CSV stream
            @set_time_limit(0);
            @ignore_user_abort(true);
            while (ob_get_level() > 0) { @ob_end_clean(); }

            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");

            // Headers: Account Key + Organization ID + original headers
            $headers = array_merge(['Account Key', 'Organization ID'], $this->getCsvHeaders());
            fputcsv($handle, $headers);

            /** @var GoToAuthService $authService */
            $authService = app(GoToAuthService::class);
            /** @var GoToApiClient $apiClient */
            $apiClient = $this->apiClient;

            $accounts = $authService->getAccountsWithOrganizations();
            $errors = [];
            $rowCount = 0;

            foreach ($accounts as $account) {
                $accountKey = $account['accountKey'];
                $organizationId = $account['organizationId'] ?? null;

                // Reset time limit per account so a single slow account can't kill the whole stream
                @set_time_limit(0);

                try {
                    // Set overrides so API calls use this account's credentials
                    if ($apiClient instanceof GoToApiClient) {
                        $apiClient->setAccountOverride($accountKey, $organizationId);
                    }

                    // Also pass accountKey into params for Contact Center services that read it from $params
                    $accountParams = array_merge($params, [
                        'accountKey' => $accountKey,
                        'organizationId' => $organizationId,
                    ]);

                    foreach ($this->getData($accountParams) as $row) {
                        $transformedRow = $this->transformRow($row);
                        $sanitized = $this->sanitizeCsvRow($transformedRow);
                        if ($sanitized === null) {
                            $errors[] = [
                                'accountKey' => $accountKey,
                                'error' => 'HTML or invalid content detected in data row (skipped)',
                            ];
                            continue;
                        }
                        $csvRow = array_merge([$accountKey, $organizationId ?? ''], $sanitized);
                        fputcsv($handle, $csvRow);
                        $rowCount++;
                        if ($rowCount % 1000 === 0) {
                            flush();
                        }
                    }
                } catch (Throwable $e) {
                    Log::warning("Multi-account CSV: failed for account {$accountKey}", [
                        'error' => $e->getMessage(),
                        'report' => $this->reportName,
                    ]);
                    $errors[] = [
                        'accountKey' => $accountKey,
                        'error' => strip_tags($e->getMessage()),
                    ];
                } finally {
                    if ($apiClient instanceof GoToApiClient) {
                        $apiClient->clearAccountOverride();
                    }
                }
            }

            // Append error summary rows at end
            if (!empty($errors)) {
                $emptyRow = array_fill(0, count($headers), '');
                fputcsv($handle, $emptyRow);
                $summaryRow = array_fill(0, count($headers), '');
                $summaryRow[0] = '--- ERRORS ---';
                fputcsv($handle, $summaryRow);

                foreach ($errors as $err) {
                    $errRow = array_fill(0, count($headers), '');
                    $errRow[0] = $err['accountKey'];
                    $errRow[1] = 'FAILED';
                    $errRow[2] = $err['error'];
                    fputcsv($handle, $errRow);
                }
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    /**
     * Get report data as a generator.
     */
    abstract public function getData(array $params = []): Generator;

    /**
     * Get the CSV headers for this report.
     */
    abstract public function getCsvHeaders(): array;

    /**
     * Transform a single data row into CSV format.
     */
    abstract public function transformRow(array $row): array;

    /**
     * Get the filename for the CSV export.
     */
    public function getFilename(array $params = []): string
    {
        $timestamp = date('Y-m-d_His');
        return "{$this->reportName}_{$timestamp}.csv";
    }

    /**
     * Sanitize a CSV row: returns null if the row contains HTML/garbage,
     * otherwise returns the row with each cell coerced to a safe scalar string.
     */
    protected function sanitizeCsvRow(array $row): ?array
    {
        $clean = [];
        foreach ($row as $cell) {
            if (is_array($cell) || is_object($cell)) {
                $cell = json_encode($cell);
            }
            $cell = (string) ($cell ?? '');
            // Detect HTML markers anywhere in the cell
            if ($cell !== '' && preg_match('/<!DOCTYPE|<html|<head|<body|<script|<style/i', $cell)) {
                return null;
            }
            // Strip any stray tags just in case and normalize newlines
            $cell = strip_tags($cell);
            $cell = str_replace(["\r\n", "\r"], "\n", $cell);
            $clean[] = $cell;
        }
        return $clean;
    }

    /**
     * Format a timestamp for CSV output.
     */
    protected function formatTimestamp(?string $timestamp): string
    {
        if (empty($timestamp)) {
            return '';
        }
        
        try {
            return date('Y-m-d H:i:s', strtotime($timestamp));
        } catch (\Exception $e) {
            return $timestamp;
        }
    }

    /**
     * Format duration in seconds to human-readable format.
     */
    protected function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }
        
        return sprintf('%02d:%02d', $minutes, $secs);
    }

    /**
     * Safely get a nested value from an array.
     */
    protected function getNestedValue(array $data, string $key, mixed $default = ''): mixed
    {
        $keys = explode('.', $key);
        $value = $data;

        foreach ($keys as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Build query parameters with date range.
     */
    protected function buildDateRangeParams(array $params): array
    {
        $query = [];

        // Handle date range parameters
        if (!empty($params['startTime'])) {
            $query['startTime'] = $params['startTime'];
        } elseif (!empty($params['start_date'])) {
            $query['startTime'] = $this->formatApiDate($params['start_date']);
        }

        if (!empty($params['endTime'])) {
            $query['endTime'] = $params['endTime'];
        } elseif (!empty($params['end_date'])) {
            $query['endTime'] = $this->formatApiDate($params['end_date']);
        }

        return $query;
    }

    /**
     * Format date for API request.
     */
    protected function formatApiDate(string $date): string
    {
        // Convert to ISO 8601 format if not already
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date . 'T00:00:00Z';
        }
        return $date;
    }
}
