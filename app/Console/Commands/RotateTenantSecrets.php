<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RotateTenantSecrets extends Command
{
    protected $signature = 'tenant:rotate-secrets 
                            {--tenant= : Rotate secrets for specific tenant ID}
                            {--force : Force rotation even if not due}
                            {--days=90 : Rotation interval in days}
                            {--grace=24 : Grace period in hours for old secrets}';

    protected $description = 'Rotate API secrets for tenants based on security policy';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $force = $this->option('force');
        $rotationDays = (int) $this->option('days');
        $graceHours = (int) $this->option('grace');

        $this->info('Starting tenant secret rotation...');
        $this->info("Rotation policy: {$rotationDays} days, Grace period: {$graceHours} hours");

        $query = Tenant::where('is_active', true);

        if ($tenantId) {
            $query->where('id', $tenantId);
        }

        if (!$force) {
            $query->where(function ($q) use ($rotationDays) {
                $q->whereNull('api_secret_rotated_at')
                  ->orWhere('api_secret_rotated_at', '<', now()->subDays($rotationDays));
            });
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->info('No tenants require secret rotation.');
            return Command::SUCCESS;
        }

        $this->info("Found {$tenants->count()} tenant(s) requiring rotation.");

        $rotated = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            try {
                $this->rotateTenantSecret($tenant, $graceHours);
                $rotated++;
                $this->line("  [OK] Rotated secrets for tenant: {$tenant->name} (ID: {$tenant->id})");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  [FAIL] Failed to rotate secrets for tenant: {$tenant->name} - {$e->getMessage()}");
                Log::error('Tenant secret rotation failed', [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Rotation complete. Rotated: {$rotated}, Failed: {$failed}");

        if ($failed > 0) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function rotateTenantSecret(Tenant $tenant, int $graceHours): void
    {
        $oldApiSecret = $tenant->api_secret;
        $newApiSecret = Str::random(128);

        $tenant->update([
            'api_secret' => $newApiSecret,
            'api_secret_rotated_at' => now(),
        ]);

        if ($graceHours > 0 && $oldApiSecret) {
            $cacheKey = "tenant:{$tenant->id}:old_api_secret";
            Cache::put($cacheKey, $oldApiSecret, now()->addHours($graceHours));
        }

        $this->logSecretRotation($tenant);

        foreach ($tenant->credentials as $credential) {
            if ($credential->credential_type === 'api_key' && $credential->isExpiringSoon()) {
                $this->rotateCredential($credential, $graceHours);
            }
        }
    }

    protected function rotateCredential($credential, int $graceHours): void
    {
        $oldSecret = $credential->credential_secret;
        $newSecret = Str::random(128);

        $credential->update([
            'credential_secret' => $newSecret,
        ]);

        if ($graceHours > 0 && $oldSecret) {
            $cacheKey = "tenant_credential:{$credential->id}:old_secret";
            Cache::put($cacheKey, $oldSecret, now()->addHours($graceHours));
        }
    }

    protected function logSecretRotation(Tenant $tenant): void
    {
        $logData = [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'rotated_at' => now()->toIso8601String(),
            'event' => 'secret_rotation',
        ];

        try {
            $channel = config('logging.channels.security') ? 'security' : 'daily';
            Log::channel($channel)->info('Tenant API secret rotated', $logData);
        } catch (\Throwable $e) {
            Log::info('Tenant API secret rotated', $logData);
        }
    }
}
