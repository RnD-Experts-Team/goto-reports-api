<?php
require_once __DIR__ . '/vendor/autoload.php';

$token = trim(shell_exec("grep '^GOTO_ACCESS_TOKEN=' .env | cut -d= -f2-"));
$orgId = 'fc93823b-19b5-4988-863f-130136ac3368';

$client = new GuzzleHttp\Client(['http_errors' => false]);

// Try a very recent date range
echo "=== Phone Number Activity (recent: last 7 days) ===\n";
$resp = $client->get("https://api.goto.com/call-reports/v1/reports/phone-number-activity", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
    'query' => ['organizationId' => $orgId, 'startTime' => '2025-04-13T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z', 'pageSize' => 5],
]);
echo "Status: " . $resp->getStatusCode() . " Body: " . substr($resp->getBody()->getContents(), 0, 800) . "\n\n";

// Try a wider range
echo "=== Phone Number Activity (3 months) ===\n";
$resp = $client->get("https://api.goto.com/call-reports/v1/reports/phone-number-activity", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
    'query' => ['organizationId' => $orgId, 'startTime' => '2025-01-01T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z', 'pageSize' => 5],
]);
echo "Status: " . $resp->getStatusCode() . " Body: " . substr($resp->getBody()->getContents(), 0, 800) . "\n\n";

// Call History recent
echo "=== Call History (recent: last 7 days) ===\n";
$resp = $client->get("https://api.goto.com/call-history/v1/calls", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
    'query' => ['organizationId' => $orgId, 'startTime' => '2025-04-13T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z', 'pageSize' => 5],
]);
echo "Status: " . $resp->getStatusCode() . " Body: " . substr($resp->getBody()->getContents(), 0, 800) . "\n\n";

// User Activity recent
echo "=== User Activity (recent: last 7 days) ===\n";
$resp = $client->get("https://api.goto.com/call-reports/v1/reports/user-activity", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
    'query' => ['organizationId' => $orgId, 'startTime' => '2025-04-13T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z', 'pageSize' => 5],
]);
echo "Status: " . $resp->getStatusCode() . " Body: " . substr($resp->getBody()->getContents(), 0, 800) . "\n\n";

// Call Events with 7-day range (within 31-day limit)
echo "=== Call Events Summaries (7 days) ===\n";
$resp = $client->get("https://api.goto.com/call-events-report/v1/report-summaries", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
    'query' => ['organizationId' => $orgId, 'accountKey' => '3690163784255667730', 'startTime' => '2025-04-13T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z', 'pageSize' => 5],
]);
echo "Status: " . $resp->getStatusCode() . " Body: " . substr($resp->getBody()->getContents(), 0, 800) . "\n\n";

// Queue Caller Details with original account key
echo "=== Queue Caller Details (accountKey=3690163784255667730, 7 days) ===\n";
$resp = $client->post("https://api.goto.com/contact-center-analytics/v1/accounts/3690163784255667730/queue-caller-details?pageSize=5", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json', 'Content-Type' => 'application/json'],
    'json' => ['startTime' => '2025-04-13T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z'],
]);
echo "Status: " . $resp->getStatusCode() . " Body: " . substr($resp->getBody()->getContents(), 0, 800) . "\n\n";
