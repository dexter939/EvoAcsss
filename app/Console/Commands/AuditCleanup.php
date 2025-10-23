<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class AuditCleanup extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'audit:cleanup 
                            {--days=90 : Delete audit logs older than this many days}
                            {--keep-critical : Keep compliance critical logs}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up old audit logs based on retention policy';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $keepCritical = $this->option('keep-critical');
        $dryRun = $this->option('dry-run');

        $this->info("Audit Log Cleanup - Retention: {$days} days");
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No logs will be deleted');
        }

        $cutoffDate = now()->subDays($days);
        
        $query = AuditLog::where('created_at', '<', $cutoffDate);
        
        if ($keepCritical) {
            $query->where('compliance_critical', false);
            $this->info('Keeping compliance critical logs');
        }

        $count = $query->count();
        
        if ($count === 0) {
            $this->info('No audit logs to clean up.');
            return Command::SUCCESS;
        }

        $this->line("Found {$count} audit logs older than {$cutoffDate->toDateString()}");

        if ($dryRun) {
            // Show sample logs that would be deleted
            $sample = $query->limit(5)->get(['id', 'event', 'category', 'created_at']);
            $this->table(
                ['ID', 'Event', 'Category', 'Created At'],
                $sample->map(fn($log) => [
                    $log->id,
                    $log->event,
                    $log->category,
                    $log->created_at->toDateTimeString(),
                ])
            );
            
            $this->warn("Would delete {$count} logs (showing first 5)");
            return Command::SUCCESS;
        }

        if (!$this->confirm("Delete {$count} audit logs?", false)) {
            $this->info('Cleanup cancelled.');
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        // Delete in chunks to avoid memory issues
        $deleted = 0;
        $query->chunkById(1000, function ($logs) use (&$deleted, $bar) {
            foreach ($logs as $log) {
                $log->delete();
                $deleted++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        
        $this->info("Successfully deleted {$deleted} audit logs.");
        
        return Command::SUCCESS;
    }
}
