<?php
require_once __DIR__ . '/vendor/autoload.php';

$token = trim(shell_exec("grep '^GOTO_ACCESS_TOKEN=' .env | cut -d= -f2-"));
$orgId = 'fc93823b-19b5-4988-863f-130136ac3368';
$accountKey1 = '8679294554919070803';
$accountKey2 = '3690163784255667730';

$client = new GuzzleHttp\Client(['http_errors' => false]);

// Test with EXACT config endpoints
echo "=== Phone Number Activity (config: /call-reports/v1/reports/phone-number-activity) ===\n";
$resp = $client->get("https://api.goto.com/call-reports/v1/reports/phone-number-activity", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
    'query' => ['organizationId' => $orgId, 'startTime' => '2024-06-01T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z', 'pageSize' => 5],
]);
echo "Status: " . $resp->getStatusCode() . " Body: " . substr($resp->getBody()->getContents(), 0, 500) . "\n\n";

// Test Call History with the CONFIG endpoint: /call-history/v1/calls
echo "=== Call History (config: /call-history/v1/calls) ===\n";
$resp = $client->get("https://api.goto.com/call-history/v1/calls", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
    'query' => ['organizationId' => $orgId, 'accountKey' => $accountKey1, 'startTime' => '2024-06-01T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z', 'pageSize' => 5],
]);
echo "Status: " . $resp->getStatusCode() . " Body: " . substr($resp->getBody()->getContents(), 0, 500) . "\n\n";

// Test Call Events with CONFIG endpoint: /call-events-report/v1/report-summaries
echo "=== Call Events Summaries (config: /call-events-report/v1/report-summaries) ===\n";
$resp = $client->get("https://api.goto.com/call-events-report/v1/report-summaries", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
    'query' => ['organizationId' => $orgId, 'accountKey' => $accountKey1, 'startTime' => '2024-06-01T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z', 'pageSize' => 5],
]);
echo "Status: " . $resp->getStatusCode() . " Body: " . substr($resp->getBody()->getContents(), 0, 500) . "\n\n";

// Now test phone-number-activity with BOTH account keys to see which has data
echo "=== Phone Number Activity with accountKey1=$accountKey1 ===\n";
$resp = $client->get("https://api.goto.com/call-reports/v1/reports/phone-number-activity", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
    'query' => ['organizationId' => $orgId, 'startTime' => '2024-06-01T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z', 'pageSize' => 5],
]);
echo "Status: " . $resp->getStatusCode() . " Body: " . substr($resp->getBody()->getContents(), 0, 500) . "\n\n";

// Try without orgId to see if there's an alternative
echo "=== Phone Number Activity WITHOUT orgId ===\n";
$resp = $client->get("https://api.goto.com/call-reports/v1/reports/phone-number-activity", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
    'query' => ['startTime' => '2024-06-01T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z', 'pageSize' => 5],
]);
echo "Status: " . $resp->getStatusCode() . " Body: " . substr($resp->getBody()->getContents(), 0, 500) . "\n\n";

// Try fetching /users/v1/me to see ALL accounts
echo "=== /users/v1/me (all accounts) ===\n";
$resp = $client->get("https://api.goto.com/users/v1/me", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
]);
echo "Status: " . $resp->getStatusCode() . " Body: " . substr($resp->getBody()->getContents(), 0, 1000) . "\n\n";

// Try the other orgId - fetch it via voice-admin for original account key
echo "=== Resolve orgId for accountKey2=$accountKey2 ===\n";
$resp = $client->get("https://api.goto.com/voice-admin/v1/phone-numbers", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
    'query' => ['accountKey' => $accountKey2, 'pageSize' => 1],
]);
echo "Status: " . $resp->getStatusCode() . " Body: " . substr($resp->getBody()->getContents(), 0, 500) . "\n\n";
