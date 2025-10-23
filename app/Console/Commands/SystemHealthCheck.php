<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Exception;

class SystemHealthCheck extends Command
{
    protected $signature = 'system:health';
    protected $description = 'Check system health status (database, redis, storage)';

    public function handle()
    {
        $healthy = true;
        $errors = [];

        // Check Database
        try {
            DB::connection()->getPdo();
            $this->info('✓ Database: OK');
        } catch (Exception $e) {
            $this->error('✗ Database: FAILED - ' . $e->getMessage());
            $errors[] = 'Database connection failed';
            $healthy = false;
        }

        // Check Redis
        try {
            Redis::connection()->ping();
            $this->info('✓ Redis: OK');
        } catch (Exception $e) {
            $this->error('✗ Redis: FAILED - ' . $e->getMessage());
            $errors[] = 'Redis connection failed';
            $healthy = false;
        }

        // Check Storage
        $storagePath = storage_path('app');
        if (is_writable($storagePath)) {
            $this->info('✓ Storage: OK');
        } else {
            $this->error('✗ Storage: NOT WRITABLE');
            $errors[] = 'Storage not writable';
            $healthy = false;
        }

        // Check required directories
        $requiredDirs = [
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
        ];

        foreach ($requiredDirs as $dir) {
            if (!is_dir($dir) || !is_writable($dir)) {
                $this->error("✗ Directory not writable: $dir");
                $errors[] = "Directory not writable: $dir";
                $healthy = false;
            }
        }

        if ($healthy) {
            $this->info('');
            $this->info('============================');
            $this->info('System Status: HEALTHY');
            $this->info('============================');
            return Command::SUCCESS;
        } else {
            $this->error('');
            $this->error('============================');
            $this->error('System Status: UNHEALTHY');
            $this->error('============================');
            $this->error('Errors:');
            foreach ($errors as $error) {
                $this->error('  - ' . $error);
            }
            return Command::FAILURE;
        }
    }
}
