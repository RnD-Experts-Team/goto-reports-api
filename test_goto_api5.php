<?php
require_once __DIR__ . '/vendor/autoload.php';

$token = trim(shell_exec("grep '^GOTO_ACCESS_TOKEN=' .env | cut -d= -f2-"));
$orgIdWithData = 'e3d54d2a-04c9-4bb9-808b-ebaad6cce2c2';
$acctWithData = '8026784973782889224';

$client = new GuzzleHttp\Client(['http_errors' => false]);

// Test ALL endpoint types with the account that HAS data
echo "=== 1. Phone Number Activity ===\n";
$resp = $client->get("https://api.goto.com/call-reports/v1/reports/phone-number-activity", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
    'query' => ['organizationId' => $orgIdWithData, 'startTime' => '2025-03-01T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z', 'pageSize' => 3],
]);
$body = json_decode($resp->getBody()->getContents(), true);
echo "Status: " . $resp->getStatusCode() . " Items: " . count($body['items'] ?? []) . "\n";
if (!empty($body['items'][0])) echo "Sample: " . json_encode($body['items'][0], JSON_PRETTY_PRINT) . "\n\n";

echo "=== 2. Caller Activity ===\n";
$resp = $client->get("https://api.goto.com/call-reports/v1/reports/caller-activity", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
    'query' => ['organizationId' => $orgIdWithData, 'startTime' => '2025-03-01T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z', 'pageSize' => 3],
]);
$body = json_decode($resp->getBody()->getContents(), true);
echo "Status: " . $resp->getStatusCode() . " Items: " . count($body['items'] ?? []) . "\n";
if (!empty($body['items'][0])) echo "Sample: " . json_encode($body['items'][0], JSON_PRETTY_PRINT) . "\n\n";

echo "=== 3. User Activity ===\n";
$resp = $client->get("https://api.goto.com/call-reports/v1/reports/user-activity", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
    'query' => ['organizationId' => $orgIdWithData, 'startTime' => '2025-03-01T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z', 'pageSize' => 3],
]);
$body = json_decode($resp->getBody()->getContents(), true);
echo "Status: " . $resp->getStatusCode() . " Items: " . count($body['items'] ?? []) . "\n";
if (!empty($body['items'][0])) echo "Sample: " . json_encode($body['items'][0], JSON_PRETTY_PRINT) . "\n\n";

echo "=== 4. Call History ===\n";
$resp = $client->get("https://api.goto.com/call-history/v1/calls", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
    'query' => ['organizationId' => $orgIdWithData, 'accountKey' => $acctWithData, 'startTime' => '2025-03-01T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z', 'pageSize' => 3],
]);
$body = json_decode($resp->getBody()->getContents(), true);
echo "Status: " . $resp->getStatusCode() . " Items: " . count($body['items'] ?? []) . "\n";
if (!empty($body['items'][0])) echo "Sample: " . substr(json_encode($body['items'][0], JSON_PRETTY_PRINT), 0, 800) . "\n\n";

echo "=== 5. Call Events Summaries ===\n";
$resp = $client->get("https://api.goto.com/call-events-report/v1/report-summaries", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
    'query' => ['organizationId' => $orgIdWithData, 'accountKey' => $acctWithData, 'startTime' => '2025-04-01T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z', 'pageSize' => 3],
]);
$body = json_decode($resp->getBody()->getContents(), true);
echo "Status: " . $resp->getStatusCode() . " Items: " . count($body['items'] ?? []) . "\n";
if (!empty($body['items'][0])) echo "Sample: " . json_encode($body['items'][0], JSON_PRETTY_PRINT) . "\n\n";
else echo "Full: " . substr(json_encode($body, JSON_PRETTY_PRINT), 0, 500) . "\n\n";

echo "=== 6. Queue Caller Details ===\n";
$resp = $client->post("https://api.goto.com/contact-center-analytics/v1/accounts/{$acctWithData}/queue-caller-details?pageSize=3", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json', 'Content-Type' => 'application/json'],
    'json' => ['startTime' => '2025-03-01T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z'],
]);
$body = json_decode($resp->getBody()->getContents(), true);
echo "Status: " . $resp->getStatusCode() . " Items: " . count($body['items'] ?? []) . "\n";
if (!empty($body['items'][0])) echo "Sample: " . json_encode($body['items'][0], JSON_PRETTY_PRINT) . "\n\n";
else echo "Full: " . substr(json_encode($body, JSON_PRETTY_PRINT), 0, 500) . "\n\n";

echo "=== 7. Queue Metrics ===\n";
$resp = $client->post("https://api.goto.com/contact-center-analytics/v1/accounts/{$acctWithData}/queue-metrics?pageSize=3", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json', 'Content-Type' => 'application/json'],
    'json' => ['startTime' => '2025-03-01T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z'],
]);
$body = json_decode($resp->getBody()->getContents(), true);
echo "Status: " . $resp->getStatusCode() . " Items: " . count($body['items'] ?? []) . "\n";
if (!empty($body['items'][0])) echo "Sample: " . substr(json_encode($body['items'][0], JSON_PRETTY_PRINT), 0, 500) . "\n\n";
else echo "Full: " . substr(json_encode($body, JSON_PRETTY_PRINT), 0, 500) . "\n\n";

echo "=== 8. Agent Statuses ===\n";
$resp = $client->post("https://api.goto.com/contact-center-analytics/v1/accounts/{$acctWithData}/agent-statuses?pageSize=3", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json', 'Content-Type' => 'application/json'],
    'json' => ['startTime' => '2025-03-01T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z'],
]);
echo "Status: " . $resp->getStatusCode() . " Body: " . substr($resp->getBody()->getContents(), 0, 500) . "\n\n";
