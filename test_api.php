<?php
require __DIR__ . '/vendor/autoload.php';
\$app = require_once __DIR__ . '/bootstrap/app.php';
\$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);
\$kernel->bootstrap();

use App\Services\GoTo\GoToAuthService;
use GuzzleHttp\Client;

\$auth = app(GoToAuthService::class);
\$token = \$auth->getValidAccessToken();
echo "Token prefix: " . substr(\$token, 0, 20) . "\n";
echo "Account key: " . \$auth->getAccountKey() . "\n";
echo "Org ID: " . \$auth->getOrganizationId() . "\n";

\$client = new Client();
\$response = \$client->get("https://api.goto.com/call-reports/v1/reports/phone-number-activity", [
    "headers" => [
        "Authorization" => "Bearer " . \$token,
        "Accept" => "application/json",
    ],
    "query" => [
        "organizationId" => \$auth->getOrganizationId(),
        "startTime" => "2025-01-01T00:00:00Z",
        "endTime" => "2025-01-31T23:59:59Z",
        "pageSize" => 5,
    ],
    "http_errors" => false,
]);
echo "Status: " . \$response->getStatusCode() . "\n";
\$body = \$response->getBody()->getContents();
echo "Body (first 500 chars): " . substr(\$body, 0, 500) . "\n";
