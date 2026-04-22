<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use Generator;

/**
 * Contract for GoTo Connect API client service.
 */
interface GoToApiClientInterface
{
    /**
     * Make an authenticated GET request to the GoTo API.
     */
    public function get(string $endpoint, array $query = []): array;

    /**
     * Make an authenticated POST request to the GoTo API.
     */
    public function post(string $endpoint, array $data = [], array $query = []): array;

    /**
     * Fetch all paginated results from a GET endpoint.
     * Returns a generator to handle large datasets efficiently.
     */
    public function getPaginated(string $endpoint, array $query = []): Generator;

    /**
     * Fetch all paginated results from a POST endpoint.
     * Returns a generator to handle large datasets efficiently.
     */
    public function postPaginated(string $endpoint, array $data = [], array $query = []): Generator;

    /**
     * Set a custom page size for pagination.
     */
    public function setPageSize(int $size): self;
}
