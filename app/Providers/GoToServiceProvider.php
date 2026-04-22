<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Contracts\GoToApiClientInterface;
use App\Services\Contracts\GoToAuthServiceInterface;
use App\Services\GoTo\GoToApiClient;
use App\Services\GoTo\GoToAuthService;
use App\Services\GoTo\Reports\AgentStatusesReportService;
use App\Services\GoTo\Reports\CallerActivityReportService;
use App\Services\GoTo\Reports\CallEventsDetailReportService;
use App\Services\GoTo\Reports\CallEventsSummaryReportService;
use App\Services\GoTo\Reports\CallHistoryReportService;
use App\Services\GoTo\Reports\PhoneNumberActivityReportService;
use App\Services\GoTo\Reports\QueueCallerDetailsReportService;
use App\Services\GoTo\Reports\QueueMetricsReportService;
use App\Services\GoTo\Reports\UserActivityReportService;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for GoTo Connect integration.
 */
class GoToServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/goto.php', 'goto'
        );

        // Bind auth service as singleton
        $this->app->singleton(GoToAuthServiceInterface::class, GoToAuthService::class);
        $this->app->singleton(GoToAuthService::class);

        // Bind API client as singleton
        $this->app->singleton(GoToApiClientInterface::class, function ($app) {
            return new GoToApiClient($app->make(GoToAuthServiceInterface::class));
        });

        // Register report services
        $this->registerReportServices();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/goto.php' => config_path('goto.php'),
        ], 'goto-config');
    }

    /**
     * Register report service bindings.
     */
    private function registerReportServices(): void
    {
        // Call Reports
        $this->app->bind(PhoneNumberActivityReportService::class, function ($app) {
            return new PhoneNumberActivityReportService(
                $app->make(GoToApiClientInterface::class)
            );
        });

        $this->app->bind(CallerActivityReportService::class, function ($app) {
            return new CallerActivityReportService(
                $app->make(GoToApiClientInterface::class)
            );
        });

        $this->app->bind(UserActivityReportService::class, function ($app) {
            return new UserActivityReportService(
                $app->make(GoToApiClientInterface::class)
            );
        });

        // Call History
        $this->app->bind(CallHistoryReportService::class, function ($app) {
            return new CallHistoryReportService(
                $app->make(GoToApiClientInterface::class)
            );
        });

        // Call Events
        $this->app->bind(CallEventsSummaryReportService::class, function ($app) {
            return new CallEventsSummaryReportService(
                $app->make(GoToApiClientInterface::class)
            );
        });

        $this->app->bind(CallEventsDetailReportService::class, function ($app) {
            return new CallEventsDetailReportService(
                $app->make(GoToApiClientInterface::class)
            );
        });

        // Contact Center Analytics (require auth service for account key)
        $this->app->bind(QueueCallerDetailsReportService::class, function ($app) {
            return new QueueCallerDetailsReportService(
                $app->make(GoToApiClientInterface::class),
                $app->make(GoToAuthService::class)
            );
        });

        $this->app->bind(QueueMetricsReportService::class, function ($app) {
            return new QueueMetricsReportService(
                $app->make(GoToApiClientInterface::class),
                $app->make(GoToAuthService::class)
            );
        });

        $this->app->bind(AgentStatusesReportService::class, function ($app) {
            return new AgentStatusesReportService(
                $app->make(GoToApiClientInterface::class),
                $app->make(GoToAuthService::class)
            );
        });
    }
}
