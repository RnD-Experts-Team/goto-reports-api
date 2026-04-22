<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GoTo Connect API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for GoTo Connect OAuth2 authentication and API access.
    |
    */

    'client_id' => env('GOTO_CLIENT_ID'),
    'client_secret' => env('GOTO_CLIENT_SECRET'),
    'redirect_uri' => env('GOTO_REDIRECT_URI', 'http://localhost:8000/goto/callback'),

    'auth_url' => env('GOTO_AUTH_URL', 'https://authentication.logmeininc.com'),
    'api_base_url' => env('GOTO_API_BASE_URL', 'https://api.goto.com'),

    'access_token' => env('GOTO_ACCESS_TOKEN'),
    'refresh_token' => env('GOTO_REFRESH_TOKEN'),
    'account_key' => env('GOTO_ACCOUNT_KEY'),
    'organization_id' => env('GOTO_ORGANIZATION_ID'),

    /*
    |--------------------------------------------------------------------------
    | OAuth Endpoints
    |--------------------------------------------------------------------------
    */
    'oauth' => [
        'authorize' => '/oauth/authorize',
        'token' => '/oauth/token',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    */
    'endpoints' => [
        // Call Reports
        'phone_number_activity' => '/call-reports/v1/reports/phone-number-activity',
        'caller_activity' => '/call-reports/v1/reports/caller-activity',
        'user_activity' => '/call-reports/v1/reports/user-activity',

        // Call History
        'call_history' => '/call-history/v1/calls',

        // Call Events Report
        'call_events_summaries' => '/call-events-report/v1/report-summaries',
        'call_events_details' => '/call-events-report/v1/reports/{conversationSpaceId}',

        // Contact Center Analytics (requires accountKey)
        'queue_caller_details' => '/contact-center-analytics/v1/accounts/{accountKey}/queue-caller-details',
        'queue_metrics' => '/contact-center-analytics/v1/accounts/{accountKey}/queue-metrics',
        'agent_statuses' => '/contact-center-analytics/v1/accounts/{accountKey}/agent-statuses',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination Settings
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'default_page_size' => 100,
        'max_page_size' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Cache Settings
    |--------------------------------------------------------------------------
    */
    'token_cache_key' => 'goto_oauth_tokens',
    'token_cache_ttl' => 3500, // Slightly less than 1 hour to account for clock drift
];
