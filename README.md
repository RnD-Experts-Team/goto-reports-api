# GoTo Connect Report Extraction API

A Laravel 12 application for extracting reports from the GoTo Connect API and exporting them as streamed CSV files.

## Features

- **OAuth2 Authentication**: Complete token lifecycle management with automatic refresh
- **Memory-Efficient CSV Streaming**: Uses PHP generators and chunked output for large datasets
- **Automatic Pagination**: Handles all pagination automatically to fetch complete datasets
- **Multiple Report Types**: Supports Call Reports, Call History, Call Events, and Contact Center Analytics

## Installation

1. **Install Dependencies**
```bash
composer install
```

2. **Configure Environment**
```bash
cp .env.example .env
php artisan key:generate
```

3. **Set GoTo Connect Credentials**

Edit `.env` with your GoTo Connect OAuth2 credentials:
```env
GOTO_CLIENT_ID=your_client_id
GOTO_CLIENT_SECRET=your_client_secret
GOTO_REDIRECT_URI=http://localhost:8000/goto/callback
GOTO_ACCOUNT_KEY=your_account_key  # Optional, obtained during OAuth
```

4. **Start the Server**
```bash
php artisan serve
```

## Authentication

### Initial OAuth Setup

1. Navigate to `http://localhost:8000/api/goto/auth` to start the OAuth flow
2. Authorize the application in GoTo Connect
3. You'll be redirected back with tokens stored automatically

### Check Status
```bash
curl http://localhost:8000/api/goto/status
```

### Manual Token Refresh
```bash
curl -X POST http://localhost:8000/api/goto/refresh
```

## API Endpoints

All report endpoints return streamed CSV files. Use query parameters to filter data.

### Common Query Parameters

| Parameter | Description | Format |
|-----------|-------------|--------|
| `startTime` | Start of date range | ISO 8601 (e.g., `2024-01-01T00:00:00Z`) |
| `endTime` | End of date range | ISO 8601 (e.g., `2024-01-31T23:59:59Z`) |
| `start_date` | Alternative start date | `YYYY-MM-DD` |
| `end_date` | Alternative end date | `YYYY-MM-DD` |

### Call Reports (Aggregated Activity)

#### Phone Number Activity
```bash
# Summary
curl "http://localhost:8000/api/reports/call-reports/phone-number-activity?startTime=2024-01-01T00:00:00Z&endTime=2024-01-31T23:59:59Z" -o phone_activity.csv

# Details for specific phone number
curl "http://localhost:8000/api/reports/call-reports/phone-number-activity/{phoneNumberId}" -o phone_activity_detail.csv
```

#### Caller Activity
```bash
# Summary
curl "http://localhost:8000/api/reports/call-reports/caller-activity" -o caller_activity.csv

# Details for specific caller
curl "http://localhost:8000/api/reports/call-reports/caller-activity/{callerNumber}" -o caller_detail.csv
```

#### User Activity
```bash
# Summary
curl "http://localhost:8000/api/reports/call-reports/user-activity" -o user_activity.csv

# Details for specific user
curl "http://localhost:8000/api/reports/call-reports/user-activity/{userId}" -o user_detail.csv
```

### Call History

```bash
# All calls
curl "http://localhost:8000/api/reports/call-history/calls" -o call_history.csv

# With filters
curl "http://localhost:8000/api/reports/call-history/calls?direction=inbound&result=answered" -o inbound_answered.csv
```

Additional parameters:
- `direction`: `inbound` or `outbound`
- `result`: `answered`, `missed`, `voicemail`
- `userKey`: Filter by specific user

### Call Events

```bash
# Summaries
curl "http://localhost:8000/api/reports/call-events/summaries" -o call_events_summary.csv

# Details for specific conversation
curl "http://localhost:8000/api/reports/call-events/details/{conversationSpaceId}" -o call_events_detail.csv
```

### Contact Center Analytics

These endpoints require an `accountKey` (obtained during OAuth or set in `.env`).

#### Queue Caller Details
```bash
curl "http://localhost:8000/api/reports/contact-center/queue-caller-details?queueIds=queue1,queue2" -o queue_callers.csv
```

#### Queue Metrics
```bash
curl "http://localhost:8000/api/reports/contact-center/queue-metrics?interval=DAY" -o queue_metrics.csv
```

Parameters:
- `queueIds`: Comma-separated queue IDs
- `interval`: `HOUR`, `DAY`, or `WEEK`
- `timezone`: Timezone for data (default: UTC)

#### Agent Statuses
```bash
curl "http://localhost:8000/api/reports/contact-center/agent-statuses?agentIds=agent1,agent2" -o agent_statuses.csv
```

Parameters:
- `agentIds`: Comma-separated agent IDs
- `queueIds`: Comma-separated queue IDs
- `timezone`: Timezone for data (default: UTC)

## Architecture

```
goto-reports-api/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       ├── GoToAuthController.php      # OAuth authentication
│   │       ├── CallReportsController.php   # Call reports endpoints
│   │       ├── CallHistoryController.php   # Call history endpoint
│   │       ├── CallEventsController.php    # Call events endpoints
│   │       └── ContactCenterController.php # Contact center endpoints
│   ├── Providers/
│   │   └── GoToServiceProvider.php         # Service bindings
│   └── Services/
│       ├── Contracts/
│       │   ├── GoToAuthServiceInterface.php
│       │   ├── GoToApiClientInterface.php
│       │   └── ReportServiceInterface.php
│       └── GoTo/
│           ├── GoToAuthService.php         # OAuth token management
│           ├── GoToApiClient.php           # HTTP client with pagination
│           └── Reports/
│               ├── BaseReportService.php   # CSV streaming base class
│               ├── PhoneNumberActivityReportService.php
│               ├── CallerActivityReportService.php
│               ├── UserActivityReportService.php
│               ├── CallHistoryReportService.php
│               ├── CallEventsSummaryReportService.php
│               ├── CallEventsDetailReportService.php
│               ├── QueueCallerDetailsReportService.php
│               ├── QueueMetricsReportService.php
│               └── AgentStatusesReportService.php
├── config/
│   └── goto.php                            # GoTo API configuration
└── routes/
    └── api.php                             # API route definitions
```

## Key Features

### Memory-Efficient CSV Streaming

All reports use PHP generators to process data row-by-row, preventing memory exhaustion on large datasets:

```php
public function getData(array $params = []): Generator
{
    yield from $this->apiClient->getPaginated($this->endpoint, $query);
}
```

### Automatic Pagination

The `GoToApiClient` automatically handles pagination by following `nextPageMarker` tokens:

```php
do {
    $response = $this->request($method, $endpoint, $paginatedQuery);
    foreach ($items as $item) {
        yield $item;
    }
    $pageMarker = $this->extractNextPageMarker($response);
} while ($pageMarker !== null);
```

### Token Auto-Refresh

When receiving a 401 Unauthorized response, the client automatically refreshes the access token:

```php
if ($e->getResponse()->getStatusCode() === 401 && !$isRetry) {
    $this->authService->refreshAccessToken();
    return $this->request($method, $endpoint, $query, $data, true);
}
```

## Health Check

```bash
curl http://localhost:8000/api/health
```

## API Documentation

```bash
curl http://localhost:8000/api/
```

## License

Proprietary
