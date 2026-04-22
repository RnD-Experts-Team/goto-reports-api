<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Contracts\GoToAuthServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Controller for GoTo Connect OAuth2 authentication flow.
 */
class GoToAuthController
{
    private GoToAuthServiceInterface $authService;

    public function __construct(GoToAuthServiceInterface $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Redirect user to GoTo Connect OAuth authorization page.
     */
    public function redirect(Request $request): RedirectResponse
    {
        // Generate a random state for CSRF protection
        $state = Str::random(40);
        session(['goto_oauth_state' => $state]);

        $authorizationUrl = $this->authService->getAuthorizationUrl($state);

        Log::info('Redirecting to GoTo OAuth authorization', [
            'authorization_url' => $authorizationUrl,
        ]);

        return redirect($authorizationUrl);
    }

    /**
     * Handle OAuth callback from GoTo Connect.
     */
    public function callback(Request $request): JsonResponse|RedirectResponse
    {
        // Check for error response
        if ($request->has('error')) {
            Log::error('GoTo OAuth error', [
                'error' => $request->get('error'),
                'description' => $request->get('error_description'),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => $request->get('error'),
                    'message' => $request->get('error_description', 'Authentication failed'),
                ], 400);
            }
            
            return redirect('/')->with('error', $request->get('error_description', 'Authentication failed'));
        }

        // Validate state parameter
        $expectedState = session('goto_oauth_state');
        if ($expectedState && $request->get('state') !== $expectedState) {
            Log::warning('GoTo OAuth state mismatch');
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'invalid_state',
                    'message' => 'State parameter mismatch. Possible CSRF attack.',
                ], 400);
            }
            return redirect('/')->with('error', 'State parameter mismatch');
        }

        // Get authorization code
        $code = $request->get('code');
        if (empty($code)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'missing_code',
                    'message' => 'Authorization code not provided',
                ], 400);
            }
            return redirect('/')->with('error', 'Authorization code not provided');
        }

        try {
            // Exchange code for tokens
            $tokens = $this->authService->exchangeCodeForTokens($code);

            // Clear the state from session
            session()->forget('goto_oauth_state');

            Log::info('GoTo OAuth authentication successful');

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Authentication successful',
                    'data' => [
                        'token_type' => $tokens['token_type'] ?? 'Bearer',
                        'expires_in' => $tokens['expires_in'] ?? 3600,
                        'account_key' => $tokens['account_key'] ?? null,
                    ],
                ]);
            }

            // Redirect to dashboard for web requests
            return redirect('/')->with('success', 'Successfully connected to GoTo Connect!');
        } catch (\Exception $e) {
            Log::error('GoTo OAuth token exchange failed', [
                'error' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'token_exchange_failed',
                    'message' => $e->getMessage(),
                ], 500);
            }

            return redirect('/')->with('error', 'Failed to authenticate: ' . $e->getMessage());
        }
    }

    /**
     * Get current authentication status.
     */
    public function status(): JsonResponse
    {
        $hasValidTokens = $this->authService->hasValidTokens();
        $tokens = $this->authService->getStoredTokens();

        return response()->json([
            'authenticated' => $hasValidTokens,
            'account_key' => $tokens['account_key'] ?? config('goto.account_key'),
            'expires_at' => isset($tokens['expires_at']) 
                ? date('Y-m-d H:i:s', $tokens['expires_at']) 
                : null,
        ]);
    }

    /**
     * Manually trigger token refresh.
     */
    public function refresh(): JsonResponse
    {
        try {
            $tokens = $this->authService->refreshAccessToken();

            return response()->json([
                'success' => true,
                'message' => 'Tokens refreshed successfully',
                'expires_in' => $tokens['expires_in'] ?? 3600,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'refresh_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of available accounts.
     */
    public function accounts(): JsonResponse
    {
        if (!$this->authService->hasValidTokens()) {
            return response()->json([
                'success' => false,
                'error' => 'not_authenticated',
                'accounts' => [],
            ], 401);
        }

        try {
            $accounts = $this->authService->getAvailableAccounts();
            $currentAccount = $this->authService->getStoredTokens()['account_key'] ?? null;

            return response()->json([
                'success' => true,
                'accounts' => $accounts,
                'current_account' => $currentAccount,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'fetch_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set the active account.
     */
    public function setAccount(Request $request): JsonResponse
    {
        $accountKey = $request->input('account_key');
        
        if (empty($accountKey)) {
            return response()->json([
                'success' => false,
                'error' => 'missing_account_key',
            ], 400);
        }

        try {
            // Resolve the organizationId for this specific account
            $organizationId = $this->authService->resolveOrganizationId($accountKey);

            $tokens = $this->authService->getStoredTokens() ?? [];
            $tokens['account_key'] = $accountKey;
            if ($organizationId) {
                $tokens['organization_id'] = $organizationId;
            }
            $this->authService->storeTokens($tokens);

            return response()->json([
                'success' => true,
                'message' => 'Account updated successfully',
                'account_key' => $accountKey,
                'organization_id' => $organizationId,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'update_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
