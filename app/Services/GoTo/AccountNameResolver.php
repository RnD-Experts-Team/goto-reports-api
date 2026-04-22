<?php

declare(strict_types=1);

namespace App\Services\GoTo;

use App\Models\GotoAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resolves a human-readable name for a GoTo account.
 *
 * Source of truth: GoTo's identity SCIM endpoint
 *   GET /identity/v1/Users/me
 * Response includes:
 *   urn:scim:schemas:extension:getgo:1.0.accounts[]
 *     { value: "<accountKey>", display: "<Account Name>" }
 *
 * This is the same list shown in the GoTo desktop/web "Account" switcher
 * (e.g. "LCF 3795-0028"). One single call returns names for ALL accounts
 * the user has access to, so we fetch the whole map at once and cache it.
 *
 * Persistent cache (24h) lives in goto_accounts. Use refresh=true to force.
 */
class AccountNameResolver
{
    private const CACHE_TTL_HOURS = 24;
    private const SCIM_GETGO_EXT  = 'urn:scim:schemas:extension:getgo:1.0';

    /** @var array<string, string>|null In-process cache of accountKey => name. */
    private ?array $identityMap = null;

    public function __construct(private readonly GoToApiClient $apiClient)
    {
    }

    public function resolve(string $accountKey, ?string $organizationId = null, bool $refresh = false): ?string
    {
        $account = GotoAccount::find($accountKey);

        $isFresh = $account
            && $account->name
            && $account->name_resolved_at
            && $account->name_resolved_at->gt(Carbon::now()->subHours(self::CACHE_TTL_HOURS));

        if ($isFresh && !$refresh) {
            return $account->name;
        }

        $name = $this->lookupName($accountKey, $organizationId);

        GotoAccount::updateOrCreate(
            ['account_key' => $accountKey],
            [
                'organization_id'  => $organizationId ?? ($account->organization_id ?? null),
                'name'             => $name ?? ($account->name ?? null),
                'name_resolved_at' => $name ? Carbon::now() : ($account->name_resolved_at ?? null),
            ]
        );

        return $name ?? ($account->name ?? null);
    }

    /**
     * Bulk-resolve names. Returns [accountKey => name|null].
     * Hits the identity endpoint at most once per call.
     */
    public function resolveMany(array $accounts, bool $refresh = false): array
    {
        if ($refresh) {
            $this->identityMap = null;
        }
        $this->getIdentityMap();

        $out = [];
        foreach ($accounts as $a) {
            $key = $a['accountKey'] ?? null;
            if (!$key) {
                continue;
            }
            $out[$key] = $this->resolve($key, $a['organizationId'] ?? null, $refresh);
        }
        return $out;
    }

    /**
     * Try identity SCIM map first (one call covers all accounts), fall back to
     * location name strategy for accounts not present in SCIM (rare).
     */
    private function lookupName(string $accountKey, ?string $organizationId): ?string
    {
        $map = $this->getIdentityMap();
        if (isset($map[$accountKey]) && $map[$accountKey] !== '') {
            return $map[$accountKey];
        }

        return $this->fetchNameFromLocations($accountKey, $organizationId);
    }

    /**
     * Fetch & cache the full [accountKey => display] map from identity SCIM.
     *
     * @return array<string, string>
     */
    private function getIdentityMap(): array
    {
        if ($this->identityMap !== null) {
            return $this->identityMap;
        }

        try {
            $response = $this->apiClient->get('/identity/v1/Users/me');
            $accounts = $response[self::SCIM_GETGO_EXT]['accounts'] ?? [];

            $map = [];
            foreach ($accounts as $a) {
                $key  = (string) ($a['value']   ?? '');
                $name = trim((string) ($a['display'] ?? ''));
                if ($key !== '' && $name !== '') {
                    $map[$key] = $name;
                }
            }

            return $this->identityMap = $map;
        } catch (Throwable $e) {
            Log::warning('AccountNameResolver: identity lookup failed', [
                'error' => $e->getMessage(),
            ]);
            return $this->identityMap = [];
        }
    }

    /**
     * Fallback for accounts missing from the SCIM list: emergency/primary
     * location name from /voice-admin/v1/locations.
     */
    private function fetchNameFromLocations(string $accountKey, ?string $organizationId): ?string
    {
        try {
            $this->apiClient->setAccountOverride($accountKey, $organizationId);

            $emergency = null;
            $first     = null;

            foreach ($this->apiClient->getPaginated('/voice-admin/v1/locations', ['pageSize' => 100]) as $loc) {
                $name = trim((string) ($loc['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                if ($first === null) {
                    $first = $name;
                }
                if (!empty($loc['usedForEmergencyServices'])) {
                    $emergency = $name;
                    break;
                }
            }

            return $emergency ?? $first;
        } catch (Throwable $e) {
            Log::warning('AccountNameResolver: location fallback failed', [
                'accountKey' => $accountKey,
                'error'      => $e->getMessage(),
            ]);
            return null;
        } finally {
            $this->apiClient->clearAccountOverride();
        }
    }
}
