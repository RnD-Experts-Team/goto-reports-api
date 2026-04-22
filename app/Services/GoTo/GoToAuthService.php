<?php

declare(strict_types=1);

namespace App\Services\GoTo;

use App\Services\Contracts\GoToAuthServiceInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * OAuth2 authentication service for GoTo Connect API.
 * Handles token lifecycle including automatic refresh.
 */
class GoToAuthService implements GoToAuthServiceInterface
{
    private Client $httpClient;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $authUrl;
    private string $tokenCacheKey;
    private int $tokenCacheTtl;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'verify' => true,
        ]);

        $this->clientId = config('goto.client_id');
        $this->clientSecret = config('goto.client_secret');
        $this->redirectUri = config('goto.redirect_uri');
        $this->authUrl = config('goto.auth_url');
        $this->tokenCacheKey = config('goto.token_cache_key', 'goto_oauth_tokens');
        $this->tokenCacheTtl = config('goto.token_cache_ttl', 3500);
    }

    /**
     * Get the OAuth2 authorization URL for user consent.
     */
    public function getAuthorizationUrl(?string $state = null): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
        ];

        if ($state !== null) {
            $params['state'] = $state;
        }

        return $this->authUrl . config('goto.oauth.authorize') . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access and refresh tokens.
     */
    public function exchangeCodeForTokens(string $code): array
    {
        try {
            $response = $this->httpClient->post($this->authUrl . config('goto.oauth.token'), [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'application/json',
                ],
            ]);

            $tokens = json_decode($response->getBody()->getContents(), true);
            
            // Fetch organization info from admin API
            $orgInfo = $this->fetchOrganizationInfo($tokens['access_token']);
            if (!empty($orgInfo['account_key'])) {
                $tokens['account_key'] = $orgInfo['account_key'];
            }
            if (!empty($orgInfo['organization_id'])) {
                $tokens['organization_id'] = $orgInfo['organization_id'];
            }
            
            $this->storeTokens($tokens);

            Log::info('GoTo OAuth tokens obtained successfully', [
                'account_key' => $orgInfo['account_key'] ?? null,
                'organization_id' => $orgInfo['organization_id'] ?? null,
            ]);

            return $tokens;
        } catch (GuzzleException $e) {
            Log::error('Failed to exchange code for tokens', [
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Failed to exchange authorization code for tokens: ' . $e->getMessage());
        }
    }

    /**
     * Fetch organization info (accountKey + organizationId UUID) from GoTo APIs.
     * Uses /users/v1/me for accountKey, then /voice-admin/v1/phone-numbers for org UUID.
     */
    private function fetchOrganizationInfo(string $accessToken): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ];
        $result = [];

        try {
            // Step 1: Get accountKey from /users/v1/me
            $response = $this->httpClient->get(config('goto.api_base_url') . '/users/v1/me', [
                'headers' => $headers,
            ]);
            $data = json_decode($response->getBody()->getContents(), true);

            $accountKey = config('goto.account_key');
            if (empty($accountKey) && !empty($data['items'][0]['accountKey'])) {
                $accountKey = (string) $data['items'][0]['accountKey'];
            }
            if ($accountKey) {
                $result['account_key'] = $accountKey;
            }
        } catch (GuzzleException $e) {
            Log::warning('Failed to fetch /users/v1/me', ['error' => $e->getMessage()]);
            $accountKey = config('goto.account_key');
            if ($accountKey) {
                $result['account_key'] = $accountKey;
            }
        }

        // Step 2: Get organizationId UUID from voice-admin phone-numbers
        if (!empty($accountKey)) {
            try {
                $response = $this->httpClient->get(config('goto.api_base_url') . '/voice-admin/v1/phone-numbers', [
                    'headers' => $headers,
                    'query' => ['accountKey' => $accountKey, 'pageSize' => 1],
                ]);
                $phoneData = json_decode($response->getBody()->getContents(), true);

                if (!empty($phoneData['items'][0]['organizationId'])) {
                    $result['organization_id'] = (string) $phoneData['items'][0]['organizationId'];
                }
            } catch (GuzzleException $e) {
                Log::warning('Failed to fetch org UUID from voice-admin', ['error' => $e->getMessage()]);
            }
        }

        Log::info('Fetched organization info', [
            'accountKey' => $result['account_key'] ?? null,
            'organizationId' => $result['organization_id'] ?? null,
        ]);

        return $result;
    }

    /**
     * Get all available accounts for the user.
     */
    public function getAvailableAccounts(): array
    {
        try {
            $accessToken = $this->getValidAccessToken();
            
            $response = $this->httpClient->get(config('goto.api_base_url') . '/users/v1/me', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            return array_map(function($item) {
                return [
                    'accountKey' => (string) $item['accountKey'],
                ];
            }, $data['items'] ?? []);
        } catch (GuzzleException $e) {
            Log::warning('Failed to fetch available accounts', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Refresh the access token using the refresh token.
     */
    public function refreshAccessToken(): array
    {
        $storedTokens = $this->getStoredTokens();
        
        if (!$storedTokens || empty($storedTokens['refresh_token'])) {
            // Try to use the refresh token from environment
            $envRefreshToken = config('goto.refresh_token');
            if (empty($envRefreshToken)) {
                throw new RuntimeException('No refresh token available. Please re-authenticate.');
            }
            $refreshToken = $envRefreshToken;
        } else {
            $refreshToken = $storedTokens['refresh_token'];
        }

        try {
            $response = $this->httpClient->post($this->authUrl . config('goto.oauth.token'), [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'application/json',
                ],
            ]);

            $tokens = json_decode($response->getBody()->getContents(), true);

            // Preserve account_key and organization_id from previously stored tokens (refresh response doesn't include them)
            $previousTokens = $this->getStoredTokens();
            if (!empty($previousTokens['account_key']) && empty($tokens['account_key'])) {
                $tokens['account_key'] = $previousTokens['account_key'];
            }
            if (!empty($previousTokens['organization_id']) && empty($tokens['organization_id'])) {
                $tokens['organization_id'] = $previousTokens['organization_id'];
            }

            $this->storeTokens($tokens);

            Log::info('GoTo OAuth tokens refreshed successfully');

            return $tokens;
        } catch (GuzzleException $e) {
            Log::error('Failed to refresh access token', [
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Failed to refresh access token: ' . $e->getMessage());
        }
    }

    /**
     * Get a valid access token (refreshing if necessary).
     */
    public function getValidAccessToken(): string
    {
        $storedTokens = $this->getStoredTokens();

        // First check for a valid cached token
        if ($storedTokens && !empty($storedTokens['access_token'])) {
            // Check if token is still valid (has some buffer time)
            if (isset($storedTokens['expires_at']) && $storedTokens['expires_at'] > time() + 60) {
                return $storedTokens['access_token'];
            }
        }

        // Check if we have an access token in env (for initial setup)
        $envAccessToken = config('goto.access_token');
        if (!empty($envAccessToken) && (!$storedTokens || empty($storedTokens['access_token']))) {
            $accountKey = config('goto.account_key');
            $organizationId = config('goto.organization_id');

            // Fetch both identifiers if either is missing
            if (empty($accountKey) || empty($organizationId)) {
                $orgInfo = $this->fetchOrganizationInfo($envAccessToken);
                $accountKey = $accountKey ?: ($orgInfo['account_key'] ?? null);
                $organizationId = $organizationId ?: ($orgInfo['organization_id'] ?? null);
            }

            $this->storeTokens([
                'access_token' => $envAccessToken,
                'refresh_token' => config('goto.refresh_token'),
                'expires_in' => 3600,
                'account_key' => $accountKey,
                'organization_id' => $organizationId,
            ]);
            return $envAccessToken;
        }

        // Need to refresh the token
        $newTokens = $this->refreshAccessToken();
        return $newTokens['access_token'];
    }

    /**
     * Store OAuth tokens securely.
     */
    public function storeTokens(array $tokens): void
    {
        $expiresAt = isset($tokens['expires_in']) 
            ? time() + (int) $tokens['expires_in'] 
            : time() + 3600;

        $tokenData = [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expires_at' => $expiresAt,
            'token_type' => $tokens['token_type'] ?? 'Bearer',
            'account_key' => $tokens['account_key'] ?? config('goto.account_key'),
            'organization_id' => $tokens['organization_id'] ?? config('goto.organization_id'),
            'organizer_key' => $tokens['organizer_key'] ?? null,
        ];

        Cache::put($this->tokenCacheKey, $tokenData, $this->tokenCacheTtl);

        // Also update .env file for persistence across restarts
        $this->updateEnvFile($tokenData);
    }

    /**
     * Get stored OAuth tokens.
     */
    public function getStoredTokens(): ?array
    {
        return Cache::get($this->tokenCacheKey);
    }

    /**
     * Check if tokens are currently stored and valid.
     */
    public function hasValidTokens(): bool
    {
        $tokens = $this->getStoredTokens();
        
        if (!$tokens || empty($tokens['access_token'])) {
            // Check env for initial access token
            return !empty(config('goto.access_token'));
        }

        // Check if token is expired (with 60 second buffer)
        if (isset($tokens['expires_at']) && $tokens['expires_at'] <= time() + 60) {
            // Token is expired but we might have a refresh token
            return !empty($tokens['refresh_token']) || !empty(config('goto.refresh_token'));
        }

        return true;
    }

    /**
     * Update .env file with new token values.
     */
    private function updateEnvFile(array $tokens): void
    {
        $envPath = base_path('.env');
        
        if (!file_exists($envPath)) {
            return;
        }

        $envContent = file_get_contents($envPath);
        
        $updates = [
            'GOTO_ACCESS_TOKEN' => $tokens['access_token'],
            'GOTO_REFRESH_TOKEN' => $tokens['refresh_token'] ?? '',
        ];

        if (!empty($tokens['account_key'])) {
            $updates['GOTO_ACCOUNT_KEY'] = $tokens['account_key'];
        }

        if (!empty($tokens['organization_id'])) {
            $updates['GOTO_ORGANIZATION_ID'] = $tokens['organization_id'];
        }

        foreach ($updates as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$value}";
            
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$replacement}";
            }
        }

        file_put_contents($envPath, $envContent);
    }

    /**
     * Get account key (numeric) from stored tokens or config.
     * Used for Contact Center Analytics URL paths.
     */
    public function getAccountKey(): ?string
    {
        $tokens = $this->getStoredTokens();
        return $tokens['account_key'] ?? config('goto.account_key');
    }

    /**
     * Get organization ID (UUID) from stored tokens or config.
     * Used for Call Reports/History/Events organizationId query param.
     */
    public function getOrganizationId(): ?string
    {
        $tokens = $this->getStoredTokens();
        return $tokens['organization_id'] ?? config('goto.organization_id');
    }

    /**
     * Resolve the organizationId UUID for a given accountKey.
     * Uses voice-admin phone-numbers endpoint.
     */
    public function resolveOrganizationId(string $accountKey): ?string
    {
        $cacheKey = "goto_org_id_{$accountKey}";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        try {
            $accessToken = $this->getValidAccessToken();
            $response = $this->httpClient->get(config('goto.api_base_url') . '/voice-admin/v1/phone-numbers', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                ],
                'query' => ['accountKey' => $accountKey, 'pageSize' => 1],
            ]);
            $data = json_decode($response->getBody()->getContents(), true);

            if (!empty($data['items'][0]['organizationId'])) {
                $orgId = (string) $data['items'][0]['organizationId'];
                Cache::put($cacheKey, $orgId, $this->tokenCacheTtl);
                return $orgId;
            }
        } catch (GuzzleException $e) {
            Log::warning("Failed to resolve orgId for account {$accountKey}", ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Get all available accounts with their organization IDs resolved.
     * Returns: [{accountKey: "...", organizationId: "..."|null}, ...]
     */
    public function getAccountsWithOrganizations(): array
    {
        $accounts = $this->getAvailableAccounts();

        return array_map(function ($account) {
            $account['organizationId'] = $this->resolveOrganizationId($account['accountKey']);
            return $account;
        }, $accounts);
    }
}
