<?php
require_once __DIR__ . '/vendor/autoload.php';

$token = trim(shell_exec("grep '^GOTO_ACCESS_TOKEN=' .env | cut -d= -f2-"));
$orgId = 'fc93823b-19b5-4988-863f-130136ac3368';

$client = new GuzzleHttp\Client(['http_errors' => false]);

// Try each account key with phone-number-activity to find one that HAS data
$accounts = ['8026784973782889224','2476665781960607745','9167002726021743377','7956053391679671813','3042240269637323008','8679294554919070803','6992944977422740993','3690163784255667730','6356711480460358973','8163731352278976295','7581144119925296402','4168579982386948115','4960623081867566099','6355267819798777361','7487550387640463618'];

echo "Testing phone-number-activity across ALL accounts...\n\n";
foreach ($accounts as $acctKey) {
    // Get org ID for this account
    $resp = $client->get("https://api.goto.com/voice-admin/v1/phone-numbers", [
        'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
        'query' => ['accountKey' => $acctKey, 'pageSize' => 1],
    ]);
    $data = json_decode($resp->getBody()->getContents(), true);
    $acctOrgId = $data['items'][0]['organizationId'] ?? 'NONE';
    $hasPhones = count($data['items'] ?? []);
    
    // Try phone number activity with this org
    if ($acctOrgId !== 'NONE') {
        $resp2 = $client->get("https://api.goto.com/call-reports/v1/reports/phone-number-activity", [
            'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
            'query' => ['organizationId' => $acctOrgId, 'startTime' => '2025-03-01T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z', 'pageSize' => 3],
        ]);
        $data2 = json_decode($resp2->getBody()->getContents(), true);
        $itemCount = count($data2['items'] ?? []);
        echo "Account $acctKey -> orgId=$acctOrgId phones=$hasPhones items=$itemCount\n";
        if ($itemCount > 0) {
            echo "  FOUND DATA: " . json_encode($data2['items'][0]) . "\n";
        }
    } else {
        echo "Account $acctKey -> NO ORG ID (no phone numbers)\n";
    }
}

// Also test call-history with accountKey param across a couple accounts
echo "\n=== Call History with accountKey ===\n";
foreach (['3690163784255667730', '8679294554919070803'] as $acctKey) {
    $resp = $client->get("https://api.goto.com/call-history/v1/calls", [
        'headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'],
        'query' => ['organizationId' => $orgId, 'accountKey' => $acctKey, 'startTime' => '2025-03-01T00:00:00Z', 'endTime' => '2025-04-20T23:59:59Z', 'pageSize' => 3],
    ]);
    $data = json_decode($resp->getBody()->getContents(), true);
    $itemCount = count($data['items'] ?? []);
    echo "CallHistory acct=$acctKey items=$itemCount\n";
    if ($itemCount > 0) {
        echo "  SAMPLE: " . substr(json_encode($data['items'][0]), 0, 300) . "\n";
    }
}
