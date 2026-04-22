<?php
require_once __DIR__ . '/vendor/autoload.php';

$token = trim(shell_exec("grep '^GOTO_ACCESS_TOKEN=' .env | cut -d= -f2-"));
$orgId = trim(shell_exec("grep '^GOTO_ORGANIZATION_ID=' .env | cut -d= -f2-"));
$accountKey = trim(shell_exec("grep '^GOTO_ACCOUNT_KEY=' .env | cut -d= -f2-"));

echo "Using orgId: $orgId\n";
echo "Using accountKey: $accountKey\n\n";

$client = new GuzzleHttp\Client(['http_errors' => false]);

// Test 1: Phone Number Activity
echo "=== 1. Phone Number Activity ===\n";
$resp = $client->get("https://api.goto.com/call-reports/v1/reports/phone-number-activity", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
    'query' => ['organizationId' => $orgId, 'startTime' => '2025-01-01T00:00:00Z', 'endTime' => '2025-01-31T23:59:59Z', 'pageSize' => 5],
]);
echo "Status: " . $resp->getStatusCode() . "\n";
echo "Body: " . substr($resp->getBody()->getContents(), 0, 1000) . "\n\n";

// Test 2: Call History
echo "=== 2. Call History ===\n";
$resp = $client->get("https://api.goto.com/call-history/v1/reports/calls", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
    'query' => ['organizationId' => $orgId, 'accountKey' => $accountKey, 'startTime' => '2025-01-01T00:00:00Z', 'endTime' => '2025-01-31T23:59:59Z', 'pageSize' => 5],
]);
echo "Status: " . $resp->getStatusCode() . "\n";
echo "Body: " . substr($resp->getBody()->getContents(), 0, 1000) . "\n\n";

// Test 3: Call Events Summaries
echo "=== 3. Call Events Summaries ===\n";
$resp = $client->get("https://api.goto.com/call-events-report/v1/reports/event-summaries", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
    'query' => ['organizationId' => $orgId, 'accountKey' => $accountKey, 'startTime' => '2025-01-01T00:00:00Z', 'endTime' => '2025-01-31T23:59:59Z', 'pageSize' => 5],
]);
echo "Status: " . $resp->getStatusCode() . "\n";
echo "Body: " . substr($resp->getBody()->getContents(), 0, 1000) . "\n\n";

// Test 4: Queue Caller Details (POST)
echo "=== 4. Queue Caller Details ===\n";
$resp = $client->post("https://api.goto.com/contact-center-analytics/v1/accounts/{$accountKey}/queue-caller-details?pageSize=5", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json', 'Content-Type' => 'application/json'],
    'json' => ['startTime' => '2025-01-01T00:00:00Z', 'endTime' => '2025-01-31T23:59:59Z'],
]);
echo "Status: " . $resp->getStatusCode() . "\n";
echo "Body: " . substr($resp->getBody()->getContents(), 0, 1000) . "\n\n";
