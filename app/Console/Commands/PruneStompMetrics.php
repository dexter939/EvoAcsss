<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StompMetric;
use Illuminate\Support\Facades\Log;

class PruneStompMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stomp:prune-metrics
                            {--days=30 : Number of days to retain metrics}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune STOMP metrics older than specified retention period (default: 30 days)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $retentionDays = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        
        $cutoffDate = now()->subDays($retentionDays);
        
        $this->info("STOMP Metrics Cleanup");
        $this->info("Retention Period: {$retentionDays} days");
        $this->info("Cutoff Date: {$cutoffDate->toDateTimeString()}");
        $this->newLine();

        // Count metrics to be deleted
        $count = StompMetric::where('collected_at', '<', $cutoffDate)->count();
        
        if ($count === 0) {
            $this->info('✅ No metrics to prune. Database is clean.');
            return 0;
        }

        if ($dryRun) {
            $this->warn("🔍 DRY RUN MODE - No data will be deleted");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Metrics to delete', $count],
                    ['Oldest metric', StompMetric::where('collected_at', '<', $cutoffDate)->min('collected_at')],
                    ['Newest to delete', StompMetric::where('collected_at', '<', $cutoffDate)->max('collected_at')],
                ]
            );
            
            $this->newLine();
            $this->info("Run without --dry-run to actually delete these metrics");
            return 0;
        }

        // Confirm deletion
        if (!$this->confirm("Delete {$count} metrics older than {$retentionDays} days?", true)) {
            $this->info('Operation cancelled.');
            return 0;
        }

        // Delete old metrics
        $deleted = StompMetric::where('collected_at', '<', $cutoffDate)->delete();
        
        Log::info("STOMP metrics pruned", [
            'retention_days' => $retentionDays,
            'cutoff_date' => $cutoffDate->toDateTimeString(),
            'deleted_count' => $deleted,
        ]);

        $this->info("✅ Successfully deleted {$deleted} old metrics");
        
        // Show current stats
        $remaining = StompMetric::count();
        $oldestRemaining = StompMetric::min('collected_at');
        
        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Deleted', $deleted],
                ['Remaining', $remaining],
                ['Oldest remaining', $oldestRemaining ?? 'N/A'],
            ]
        );

        return 0;
    }
}
