<?php

use App\Http\Controllers\CallEventsController;
use App\Http\Controllers\CallHistoryController;
use App\Http\Controllers\CallReportsController;
use App\Http\Controllers\ContactCenterController;
use App\Http\Controllers\ConversationsController;
use App\Http\Controllers\GoToAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| GoTo Connect Report Extraction API Routes
|
| All routes are prefixed with /api by the RouteServiceProvider.
| These endpoints generate streamed CSV downloads of GoTo Connect reports.
|
*/

/*
|--------------------------------------------------------------------------
| OAuth Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('goto')->group(function () {
    // Initiate OAuth flow
    Route::get('/auth', [GoToAuthController::class, 'redirect'])
        ->name('goto.auth');

    // OAuth callback handler
    Route::get('/callback', [GoToAuthController::class, 'callback'])
        ->name('goto.callback');

    // Check authentication status
    Route::get('/status', [GoToAuthController::class, 'status'])
        ->name('goto.status');

    // Manual token refresh
    Route::post('/refresh', [GoToAuthController::class, 'refresh'])
        ->name('goto.refresh');

    // Get available accounts
    Route::get('/accounts', [GoToAuthController::class, 'accounts'])
        ->name('goto.accounts');

    // Set active account
    Route::post('/accounts/set', [GoToAuthController::class, 'setAccount'])
        ->name('goto.accounts.set');
});

/*
|--------------------------------------------------------------------------
| Call Reports (Aggregated Activity) - CSV Export Endpoints
|--------------------------------------------------------------------------
|
| These endpoints return streamed CSV files containing complete datasets
| with automatic pagination handling.
|
| Query Parameters:
| - startTime: Start of date range (ISO 8601 format)
| - endTime: End of date range (ISO 8601 format)
| - start_date: Alternative start date parameter
| - end_date: Alternative end date parameter
|
*/
Route::prefix('reports/call-reports')->group(function () {
    // Phone Number Activity
    Route::get('/phone-number-activity', [CallReportsController::class, 'phoneNumberActivitySummary'])
        ->name('reports.phone-number-activity.summary');
    
    Route::get('/phone-number-activity/{phoneNumberId}', [CallReportsController::class, 'phoneNumberActivityDetails'])
        ->name('reports.phone-number-activity.details');

    // Caller Activity
    Route::get('/caller-activity', [CallReportsController::class, 'callerActivitySummary'])
        ->name('reports.caller-activity.summary');
    
    Route::get('/caller-activity/{callerNumber}', [CallReportsController::class, 'callerActivityDetails'])
        ->name('reports.caller-activity.details');

    // User Activity
    Route::get('/user-activity', [CallReportsController::class, 'userActivitySummary'])
        ->name('reports.user-activity.summary');
    
    Route::get('/user-activity/{userId}', [CallReportsController::class, 'userActivityDetails'])
        ->name('reports.user-activity.details');
});

/*
|--------------------------------------------------------------------------
| Call History - CSV Export Endpoint
|--------------------------------------------------------------------------
|
| Query Parameters:
| - startTime/endTime: Date range (ISO 8601)
| - direction: 'inbound' or 'outbound' (optional)
| - result: 'answered', 'missed', 'voicemail' (optional)
| - userKey: Filter by specific user (optional)
|
*/
Route::prefix('reports/call-history')->group(function () {
    Route::get('/calls', [CallHistoryController::class, 'index'])
        ->name('reports.call-history.calls');
});

/*
|--------------------------------------------------------------------------
| Call Events Report - CSV Export Endpoints
|--------------------------------------------------------------------------
|
| Query Parameters:
| - startTime/endTime: Date range (ISO 8601)
|
*/
Route::prefix('reports/call-events')->group(function () {
    // Call Events Summaries
    Route::get('/summaries', [CallEventsController::class, 'summaries'])
        ->name('reports.call-events.summaries');
    
    // Call Events Details by Conversation
    Route::get('/details/{conversationSpaceId}', [CallEventsController::class, 'details'])
        ->name('reports.call-events.details');
});

/*
|--------------------------------------------------------------------------
| Conversations Report (DB-backed) - JSON / CSV
|--------------------------------------------------------------------------
|
| Slim, persisted view of Call Events Summaries with an Account Name column
| (resolved from /voice-admin/v1/locations). Each request:
|   1. Pulls the requested range from GoTo
|   2. Upserts new rows into Postgres (existing rows untouched)
|   3. Returns rows from the local DB filtered by accountKey/accountName
|
| Query Parameters:
|   - startTime/endTime  ISO 8601
|   - accountKey         specific key, or 'all' (default)
|   - accountName        e.g. 'LCF 3795-0027' — filters local rows
|   - sync               '0' to skip GoTo sync and read DB only
|   - format             'json' (default) or 'csv'
|   - limit              optional integer cap
|
*/
Route::prefix('reports/conversations')->group(function () {
    Route::get('/',              [ConversationsController::class, 'index'])
        ->name('reports.conversations.index');
    Route::get('/account-names', [ConversationsController::class, 'accountNames'])
        ->name('reports.conversations.account-names');
    // Long-running: pulls every available account for the past N days (default 365).
    Route::match(['get', 'post'], '/backfill', [ConversationsController::class, 'backfill'])
        ->name('reports.conversations.backfill');
});

/*
|--------------------------------------------------------------------------
| Contact Center Analytics - CSV Export Endpoints
|--------------------------------------------------------------------------
|
| These endpoints require an account key (from OAuth or environment).
|
| Query Parameters:
| - startTime/endTime: Date range (ISO 8601)
| - accountKey: Override account key (optional)
| - timezone: Timezone for data (default: UTC)
| - queueIds: Comma-separated queue IDs (optional)
| - agentIds: Comma-separated agent IDs (optional, for agent-statuses)
| - interval: Aggregation interval: 'HOUR', 'DAY', 'WEEK' (optional, for queue-metrics)
|
*/
Route::prefix('reports/contact-center')->group(function () {
    // Queue Caller Details
    Route::get('/queue-caller-details', [ContactCenterController::class, 'queueCallerDetails'])
        ->name('reports.contact-center.queue-caller-details');

    // Queue Metrics
    Route::get('/queue-metrics', [ContactCenterController::class, 'queueMetrics'])
        ->name('reports.contact-center.queue-metrics');

    // Agent Statuses
    Route::get('/agent-statuses', [ContactCenterController::class, 'agentStatuses'])
        ->name('reports.contact-center.agent-statuses');
});

/*
|--------------------------------------------------------------------------
| Health Check & Documentation
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'service' => 'GoTo Reports API',
        'version' => '1.0.0',
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('health');

Route::get('/openapi.yaml', function () {
    $path = base_path('openapi.yaml');
    return response()->file($path, [
        'Content-Type' => 'application/x-yaml',
    ]);
})->name('openapi.yaml');

Route::get('/openapi.json', function () {
    $path = base_path('openapi.yaml');
    $yaml = file_get_contents($path);
    $spec = \Symfony\Component\Yaml\Yaml::parse($yaml);
    return response()->json($spec);
})->name('openapi.json');

Route::get('/docs', function () {
    return response()->view('swagger');
})->name('docs');

Route::get('/', function () {
    return response()->json([
        'service' => 'GoTo Connect Report Extraction API',
        'version' => '1.0.0',
        'documentation' => [
            'authentication' => [
                'GET /api/goto/auth' => 'Initiate OAuth2 flow',
                'GET /api/goto/callback' => 'OAuth2 callback handler',
                'GET /api/goto/status' => 'Check authentication status',
                'POST /api/goto/refresh' => 'Manually refresh access token',
            ],
            'call_reports' => [
                'GET /api/reports/call-reports/phone-number-activity' => 'Phone number activity summary CSV',
                'GET /api/reports/call-reports/phone-number-activity/{id}' => 'Phone number activity details CSV',
                'GET /api/reports/call-reports/caller-activity' => 'Caller activity summary CSV',
                'GET /api/reports/call-reports/caller-activity/{number}' => 'Caller activity details CSV',
                'GET /api/reports/call-reports/user-activity' => 'User activity summary CSV',
                'GET /api/reports/call-reports/user-activity/{id}' => 'User activity details CSV',
            ],
            'call_history' => [
                'GET /api/reports/call-history/calls' => 'Call history CSV',
            ],
            'call_events' => [
                'GET /api/reports/call-events/summaries' => 'Call events summaries CSV',
                'GET /api/reports/call-events/details/{conversationSpaceId}' => 'Call events details CSV',
            ],
            'contact_center' => [
                'GET /api/reports/contact-center/queue-caller-details' => 'Queue caller details CSV',
                'GET /api/reports/contact-center/queue-metrics' => 'Queue metrics CSV',
                'GET /api/reports/contact-center/agent-statuses' => 'Agent statuses CSV',
            ],
            'common_parameters' => [
                'startTime' => 'Start of date range (ISO 8601, e.g., 2024-01-01T00:00:00Z)',
                'endTime' => 'End of date range (ISO 8601, e.g., 2024-01-31T23:59:59Z)',
                'start_date' => 'Alternative: Start date (YYYY-MM-DD)',
                'end_date' => 'Alternative: End date (YYYY-MM-DD)',
            ],
        ],
    ]);
})->name('api.index');
