<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GoTo\Reports\ConversationsReportService;
use Illuminate\Console\Command;

/**
 * Long-running backfill of every account on the current GoTo token.
 *
 * Examples:
 *   php artisan conversations:backfill                # last 365 days
 *   php artisan conversations:backfill --days=90      # last 90 days
 *   php artisan conversations:backfill --days=730     # last 2 years
 *
 * Run in background (Linux/macOS):
 *   nohup php artisan conversations:backfill --days=365 \
 *         > storage/logs/backfill.log 2>&1 &
 */
class ConversationsBackfillCommand extends Command
{
    protected $signature = 'conversations:backfill
        {--days=365 : How many days back to sync (1..1825)}';

    protected $description = 'Backfill conversation summaries from GoTo for every account on the token.';

    public function handle(ConversationsReportService $service): int
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $days = max(1, min((int) $this->option('days'), 1825));

        $this->info("Starting backfill: last {$days} day(s), all accounts.");
        $started = microtime(true);

        $result = $service->backfillAll($days);

        $elapsed = round(microtime(true) - $started, 1);
        $this->info('Backfill complete.');
        $this->table(
            ['accounts', 'days', 'newly synced', 'window start', 'window end', 'duration (s)'],
            [[
                $result['accounts'],
                $result['days'],
                $result['synced'],
                $result['startTime'],
                $result['endTime'],
                $elapsed,
            ]]
        );

        return self::SUCCESS;
    }
}
