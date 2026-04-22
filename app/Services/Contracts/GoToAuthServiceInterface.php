<?php

declare(strict_types=1);

namespace App\Services\Contracts;

/**
 * Contract for GoTo Connect OAuth2 authentication service.
 */
interface GoToAuthServiceInterface
{
    /**
     * Get the OAuth2 authorization URL for user consent.
     */
    public function getAuthorizationUrl(string $state = null): string;

    /**
     * Exchange authorization code for access and refresh tokens.
     */
    public function exchangeCodeForTokens(string $code): array;

    /**
     * Refresh the access token using the refresh token.
     */
    public function refreshAccessToken(): array;

    /**
     * Get a valid access token (refreshing if necessary).
     */
    public function getValidAccessToken(): string;

    /**
     * Store OAuth tokens securely.
     */
    public function storeTokens(array $tokens): void;

    /**
     * Get stored OAuth tokens.
     */
    public function getStoredTokens(): ?array;

    /**
     * Check if tokens are currently stored and valid.
     */
    public function hasValidTokens(): bool;
}
