<?php

declare(strict_types=1);

namespace App\Services\GoTo;

use App\Services\Contracts\GoToApiClientInterface;
use App\Services\Contracts\GoToAuthServiceInterface;
use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * HTTP client for GoTo Connect API with automatic token refresh and pagination handling.
 */
class GoToApiClient implements GoToApiClientInterface
{
    private Client $httpClient;
    private GoToAuthServiceInterface $authService;
    private string $baseUrl;
    private int $pageSize;
    private ?string $accountKeyOverride = null;
    private ?string $organizationIdOverride = null;

    public function __construct(GoToAuthServiceInterface $authService)
    {
        $this->authService = $authService;
        $this->baseUrl = config('goto.api_base_url');
        $this->pageSize = config('goto.pagination.default_page_size', 100);
        
        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 120,
            'verify' => true,
        ]);
    }

    /**
     * Make an authenticated GET request to the GoTo API.
     */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, $query);
    }

    /**
     * Make an authenticated POST request to the GoTo API.
     */
    public function post(string $endpoint, array $data = [], array $query = []): array
    {
        return $this->request('POST', $endpoint, $query, $data);
    }

    /**
     * Fetch all paginated results from a GET endpoint.
     */
    public function getPaginated(string $endpoint, array $query = []): Generator
    {
        yield from $this->paginatedRequest('GET', $endpoint, $query);
    }

    /**
     * Fetch all paginated results from a POST endpoint.
     */
    public function postPaginated(string $endpoint, array $data = [], array $query = []): Generator
    {
        yield from $this->paginatedRequest('POST', $endpoint, $query, $data);
    }

    /**
     * Set a custom page size for pagination.
     */
    public function setPageSize(int $size): self
    {
        $maxSize = config('goto.pagination.max_page_size', 1000);
        $this->pageSize = min($size, $maxSize);
        return $this;
    }

    /**
     * Make an authenticated HTTP request with automatic token refresh on 401.
     */
    private function request(string $method, string $endpoint, array $query = [], ?array $data = null, bool $authRetried = false, int $rateLimitRetries = 0): array
    {
        $accessToken = $this->authService->getValidAccessToken();
        
        // Get organization ID for API calls that require it
        $organizationId = $this->getOrganizationId();

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ];

        // Add organizationId to query params for endpoints that need it
        if ($organizationId && $this->endpointRequiresOrgId($endpoint)) {
            $query['organizationId'] = $organizationId;
        }

        // Add accountKey for endpoints that require it
        if ($this->endpointRequiresAccountKey($endpoint)) {
            $accountKey = $this->getAccountKey();
            if ($accountKey) {
                $query['accountKey'] = $accountKey;
            }
        }

        if (!empty($query)) {
            $options['query'] = $query;
        }

        if ($data !== null && $method === 'POST') {
            $options['json'] = $data;
        }

        try {
            $response = $this->httpClient->request($method, $endpoint, $options);
            $body = $response->getBody()->getContents();
            
            return json_decode($body, true) ?? [];
        } catch (ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();

            // Handle 401 Unauthorized - try to refresh token
            if ($statusCode === 401 && !$authRetried) {
                Log::info('Received 401, attempting to refresh token');
                $this->authService->refreshAccessToken();
                return $this->request($method, $endpoint, $query, $data, true, $rateLimitRetries);
            }

            // Handle 429 Too Many Requests - retry with backoff (up to 3 times)
            if ($statusCode === 429 && $rateLimitRetries < 3) {
                $retryAfter = (int) ($e->getResponse()->getHeaderLine('Retry-After') ?: (($rateLimitRetries + 1) * 2));
                // Cap sleep to 10s to avoid exceeding PHP max_execution_time
                $retryAfter = min($retryAfter, 10);
                Log::warning("Rate limited (429), retry {$rateLimitRetries}/3 after {$retryAfter}s", ['endpoint' => $endpoint]);
                sleep($retryAfter);
                return $this->request($method, $endpoint, $query, $data, $authRetried, $rateLimitRetries + 1);
            }

            $this->logAndThrowError($e, $endpoint);
        } catch (GuzzleException $e) {
            $this->logAndThrowError($e, $endpoint);
        }

        return [];
    }

    /**
     * Get organization ID (UUID) from auth service.
     */
    private function getOrganizationId(): ?string
    {
        if ($this->organizationIdOverride !== null) {
            return $this->organizationIdOverride;
        }
        if ($this->authService instanceof GoToAuthService) {
            return $this->authService->getOrganizationId();
        }
        return config('goto.organization_id');
    }

    /**
     * Check if endpoint requires organizationId parameter.
     */
    private function endpointRequiresOrgId(string $endpoint): bool
    {
        $requiresOrgId = [
            '/call-reports/',
            '/call-history/',
            '/call-events-report/',
        ];

        foreach ($requiresOrgId as $pattern) {
            if (str_contains($endpoint, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if endpoint requires accountKey parameter.
     */
    private function endpointRequiresAccountKey(string $endpoint): bool
    {
        $requiresAccountKey = [
            '/call-history/',
            '/call-events-report/',
            '/users/v1/users',
            '/voice-admin/v1/',
        ];

        foreach ($requiresAccountKey as $pattern) {
            if (str_contains($endpoint, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get account key from auth service.
     */
    private function getAccountKey(): ?string
    {
        if ($this->accountKeyOverride !== null) {
            return $this->accountKeyOverride;
        }
        if ($this->authService instanceof GoToAuthService) {
            return $this->authService->getAccountKey();
        }
        return config('goto.account_key');
    }

    /**
     * Set temporary account overrides for multi-account mode.
     */
    public function setAccountOverride(string $accountKey, ?string $organizationId): void
    {
        $this->accountKeyOverride = $accountKey;
        $this->organizationIdOverride = $organizationId;
    }

    /**
     * Clear account overrides (restore default behavior).
     */
    public function clearAccountOverride(): void
    {
        $this->accountKeyOverride = null;
        $this->organizationIdOverride = null;
    }

    /**
     * Handle paginated requests with automatic page marker handling.
     */

    private function paginatedRequest(string $method, string $endpoint, array $query = [], ?array $data = null): Generator
    {
        $pageMarker = null;
        $totalRecords = 0;
        $pageCount = 0;
        $maxPages = 100; // Safety limit to prevent infinite pagination
        $consecutiveEmptyPages = 0;

        do {
            // Add pagination parameters
            $paginatedQuery = array_merge($query, [
                'pageSize' => $this->pageSize,
            ]);

            if ($pageMarker !== null) {
                $paginatedQuery['pageMarker'] = $pageMarker;
            }

            // For POST requests, add pagination to the body as well
            $paginatedData = $data;
            if ($method === 'POST' && $data !== null) {
                $paginatedData = array_merge($data, [
                    'pageSize' => $this->pageSize,
                ]);
                if ($pageMarker !== null) {
                    $paginatedData['pageMarker'] = $pageMarker;
                }
            }

            $response = $this->request($method, $endpoint, $paginatedQuery, $paginatedData);
            $pageCount++;

            // Extract items from various response formats
            $items = $this->extractItems($response);
            $totalRecords += count($items);

            Log::debug('Fetched page from GoTo API', [
                'endpoint' => $endpoint,
                'page' => $pageCount,
                'items_on_page' => count($items),
                'total_so_far' => $totalRecords,
            ]);

            // Yield each item individually for memory-efficient processing
            foreach ($items as $item) {
                yield $item;
            }

            // Stop if page returned no items (prevents infinite loop on empty pages with markers)
            if (empty($items)) {
                $consecutiveEmptyPages++;
                if ($consecutiveEmptyPages >= 2) {
                    Log::info('Stopping pagination after consecutive empty pages', [
                        'endpoint' => $endpoint,
                        'pages_fetched' => $pageCount,
                    ]);
                    break;
                }
            } else {
                $consecutiveEmptyPages = 0;
            }

            // Get next page marker
            $pageMarker = $this->extractNextPageMarker($response);

        } while ($pageMarker !== null && $pageCount < $maxPages);

        Log::info('Completed paginated fetch', [
            'endpoint' => $endpoint,
            'total_pages' => $pageCount,
            'total_records' => $totalRecords,
        ]);
    }

    /**
     * Extract items from various GoTo API response formats.
     */
    private function extractItems(array $response): array
    {
        // Different endpoints use different response structures
        $possibleKeys = ['items', 'records', 'data', 'calls', 'results', 'summaries', 'events'];

        foreach ($possibleKeys as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        // Handle Queue Metrics nested structure: queueMetricsPeriods[].queueMetricsItems[]
        if (isset($response['queueMetricsPeriods']) && is_array($response['queueMetricsPeriods'])) {
            $allItems = [];
            foreach ($response['queueMetricsPeriods'] as $period) {
                if (!empty($period['queueMetricsItems']) && is_array($period['queueMetricsItems'])) {
                    foreach ($period['queueMetricsItems'] as $item) {
                        // Inject the period's time range into each item
                        $item['periodStart'] = $period['startTime'] ?? '';
                        $item['periodEnd'] = $period['endTime'] ?? '';
                        $allItems[] = $item;
                    }
                }
            }
            return $allItems;
        }

        // If no known key found, check if response itself is an array of items
        if (isset($response[0]) && is_array($response[0])) {
            return $response;
        }

        // Return empty array if no items found
        return [];
    }

    /**
     * Extract next page marker from response.
     */
    private function extractNextPageMarker(array $response): ?string
    {
        // Check various pagination formats
        $markers = [
            'nextPageMarker',
            'next_page_marker',
            'nextPage',
            'pagination.nextPageMarker',
        ];

        foreach ($markers as $marker) {
            if (str_contains($marker, '.')) {
                // Handle nested keys
                $keys = explode('.', $marker);
                $value = $response;
                foreach ($keys as $key) {
                    if (isset($value[$key])) {
                        $value = $value[$key];
                    } else {
                        $value = null;
                        break;
                    }
                }
                if ($value !== null) {
                    return (string) $value;
                }
            } elseif (!empty($response[$marker])) {
                return (string) $response[$marker];
            }
        }

        // Check for Link header style pagination
        if (isset($response['_links']['next'])) {
            // Extract page marker from next URL if present
            $nextUrl = $response['_links']['next'];
            parse_str(parse_url($nextUrl, PHP_URL_QUERY) ?? '', $queryParams);
            return $queryParams['pageMarker'] ?? null;
        }

        return null;
    }

    /**
     * Log error and throw runtime exception with a clean, single-line message
     * derived from the GoTo error envelope when available.
     */
    private function logAndThrowError(GuzzleException $e, string $endpoint): void
    {
        $responseBody = '';
        $statusCode = 0;
        if ($e instanceof ClientException) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = $e->getResponse()->getBody()->getContents();
        }

        Log::error('GoTo API request failed', [
            'endpoint' => $endpoint,
            'status' => $statusCode,
            'error' => $e->getMessage(),
            'response' => $responseBody,
        ]);

        // Try to parse GoTo error envelope: { status, errorCode, message, reference }
        $clean = null;
        if ($responseBody !== '') {
            $decoded = json_decode($responseBody, true);
            if (is_array($decoded)) {
                $code = $decoded['errorCode'] ?? $decoded['code'] ?? null;
                $msg  = $decoded['message'] ?? $decoded['error_description'] ?? $decoded['error'] ?? null;
                if ($code || $msg) {
                    $clean = trim(($code ? "[{$code}] " : '') . ($msg ?? ''));
                }
            }
        }

        // Friendly hints for common cases
        if ($statusCode === 403) {
            $hint = 'The OAuth token does not have permission for this resource, or the account lacks the required GoTo license/feature.';
            $clean = ($clean ?: 'Forbidden') . ' — ' . $hint;
        } elseif ($statusCode === 404) {
            $clean = ($clean ?: 'Not Found') . ' — endpoint or resource does not exist for this account.';
        } elseif ($statusCode === 400) {
            $clean = ($clean ?: 'Bad Request') . ' — check date range and query parameters.';
        }

        $message = $clean ?: $e->getMessage();
        // Always single-line, no HTML
        $message = preg_replace('/\s+/', ' ', strip_tags($message));

        throw new RuntimeException("GoTo API {$statusCode}: {$message}");
    }
}
