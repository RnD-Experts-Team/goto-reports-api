<?php
require_once __DIR__ . '/vendor/autoload.php';

$token = trim(shell_exec("grep '^GOTO_ACCESS_TOKEN=' .env | cut -d= -f2-"));
$acctWithData = '8026784973782889224';
$client = new GuzzleHttp\Client(['http_errors' => false]);

// Use a shorter date range to find periods with data
$resp = $client->post("https://api.goto.com/contact-center-analytics/v1/accounts/{$acctWithData}/queue-metrics?pageSize=3&interval=DAY", [
    'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json', 'Content-Type' => 'application/json'],
    'json' => ['startTime' => '2025-04-15T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z'],
]);
$body = json_decode($resp->getBody()->getContents(), true);
echo "Status: " . $resp->getStatusCode() . "\n";
echo "Top-level keys: " . implode(', ', array_keys($body)) . "\n\n";

if (!empty($body['queueMetricsPeriods'])) {
    echo "Periods: " . count($body['queueMetricsPeriods']) . "\n";
    foreach ($body['queueMetricsPeriods'] as $i => $period) {
        $items = $period['queueMetricsItems'] ?? [];
        if (!empty($items)) {
            echo "\nPeriod $i (items=" . count($items) . "):\n";
            echo "  Time: {$period['startTime']} - {$period['endTime']}\n";
            echo "  Sample item: " . json_encode($items[0], JSON_PRETTY_PRINT) . "\n";
            break;
        }
    }
    // If no items found, show first period structure
    if (!empty($body['queueMetricsPeriods'][0])) {
        echo "\nFirst period keys: " . implode(', ', array_keys($body['queueMetricsPeriods'][0])) . "\n";
    }
}
