<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\CpeDevice;
use Illuminate\Support\Facades\DB;

class DeviceAccessBackfill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devices:backfill-access
                            {--dry-run : Show what would be done without making changes}
                            {--role=admin : Default role to assign (viewer, manager, admin)}
                            {--user= : Backfill only for specific user ID}
                            {--device= : Backfill only for specific device ID}
                            {--skip-super-admin : Do not auto-assign all devices to super-admins}
                            {--force : Force overwrite existing assignments}
                            {--chunk=500 : Number of devices to process per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill user_devices table with existing devices and users (carrier-grade scalable)';

    /**
     * Backfill statistics
     */
    protected array $stats = [
        'super_admins' => 0,
        'regular_users' => 0,
        'assignments_created' => 0,
        'assignments_skipped' => 0,
        'assignments_updated' => 0,
        'users_processed' => 0,
        'devices_processed' => 0,
        'chunks_processed' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $defaultRole = $this->option('role');
        $userFilter = $this->option('user');
        $deviceFilter = $this->option('device');
        $skipSuperAdmin = $this->option('skip-super-admin');
        $force = $this->option('force');
        $chunkSize = (int) $this->option('chunk');

        // Validate role
        $validRoles = ['viewer', 'manager', 'admin'];
        if (!in_array($defaultRole, $validRoles)) {
            $this->error("Invalid role: {$defaultRole}. Must be one of: " . implode(', ', $validRoles));
            return self::FAILURE;
        }

        // Validate chunk size
        if ($chunkSize <= 0) {
            $this->error("Invalid chunk size: {$chunkSize}. Must be a positive integer.");
            return self::FAILURE;
        }

        $this->info('🔄 Device Access Backfill Tool (Carrier-Grade)');
        $this->info('================================================');
        
        if ($dryRun) {
            $this->warn('🧪 DRY-RUN MODE: No changes will be made');
        }

        // Count totals for progress tracking
        $totalUsers = User::query()
            ->when($userFilter, fn($q) => $q->where('id', $userFilter))
            ->count();
        
        $totalDevices = CpeDevice::query()
            ->when($deviceFilter, fn($q) => $q->where('id', $deviceFilter))
            ->count();

        $this->info("📊 Found {$totalUsers} users and {$totalDevices} devices");
        $this->info("⚙️  Chunk size: {$chunkSize} devices per batch");

        if ($totalUsers === 0) {
            $this->warn('⚠️  No users found in the database');
            return self::SUCCESS;
        }

        if ($totalDevices === 0) {
            $this->warn('⚠️  No devices found in the database');
            return self::SUCCESS;
        }

        // Process users with cursor() to avoid memory exhaustion
        $this->newLine();
        $this->info('🚀 Starting backfill...');
        
        try {
            User::query()
                ->when($userFilter, fn($q) => $q->where('id', $userFilter))
                ->cursor()
                ->each(function (User $user) use ($defaultRole, $skipSuperAdmin, $force, $dryRun, $chunkSize, $deviceFilter) {
                    $this->processUser($user, $defaultRole, $skipSuperAdmin, $force, $dryRun, $chunkSize, $deviceFilter);
                });

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }

        // Display summary
        $this->newLine();
        $this->info('📈 Backfill Summary');
        $this->info('===================');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Users processed', $this->stats['users_processed']],
                ['Devices processed (total)', $this->stats['devices_processed']],
                ['Chunks processed', $this->stats['chunks_processed']],
                ['Super-admins', $this->stats['super_admins']],
                ['Regular users', $this->stats['regular_users']],
                ['Assignments created', $this->stats['assignments_created']],
                ['Assignments skipped (existing)', $this->stats['assignments_skipped']],
                ['Assignments updated (forced)', $this->stats['assignments_updated']],
            ]
        );

        if ($dryRun) {
            $this->warn('⚠️  This was a DRY-RUN. Run without --dry-run to apply changes.');
        } else {
            $this->info('✅ Backfill completed successfully!');
        }

        return self::SUCCESS;
    }

    /**
     * Process single user with chunked device assignment
     * 
     * @param User $user
     * @param string $defaultRole
     * @param bool $skipSuperAdmin
     * @param bool $force
     * @param bool $dryRun
     * @param int $chunkSize
     * @param int|null $deviceFilter
     */
    protected function processUser(User $user, string $defaultRole, bool $skipSuperAdmin, bool $force, bool $dryRun, int $chunkSize, ?int $deviceFilter): void
    {
        $isSuperAdmin = $user->isSuperAdmin();
        $role = $isSuperAdmin ? 'admin' : $defaultRole;

        if ($isSuperAdmin) {
            $this->stats['super_admins']++;
            
            if ($skipSuperAdmin) {
                $this->line("⏭️  Skipping super-admin: {$user->name} (#{$user->id})");
                return;
            }

            $this->info("👑 Processing super-admin: {$user->name} (#{$user->id})");
        } else {
            $this->stats['regular_users']++;
            $this->line("👤 Processing user: {$user->name} (#{$user->id}) with role '{$role}'");
        }

        $this->stats['users_processed']++;

        // Preload existing assignments for this user (memory-efficient)
        $existingAssignments = DB::table('user_devices')
            ->where('user_id', $user->id)
            ->when($deviceFilter, fn($q) => $q->where('cpe_device_id', $deviceFilter))
            ->pluck('role', 'cpe_device_id')
            ->toArray();

        // Process devices in chunks to avoid memory exhaustion
        CpeDevice::query()
            ->when($deviceFilter, fn($q) => $q->where('id', $deviceFilter))
            ->orderBy('id')
            ->chunk($chunkSize, function ($devices) use ($user, $role, $force, $dryRun, &$existingAssignments) {
                $this->processDeviceChunk($user, $devices, $role, $force, $dryRun, $existingAssignments);
                $this->stats['chunks_processed']++;
                $this->stats['devices_processed'] += $devices->count();
            });
    }

    /**
     * Process chunk of devices for a user with batch operations
     * 
     * @param User $user
     * @param \Illuminate\Support\Collection $devices
     * @param string $role
     * @param bool $force
     * @param bool $dryRun
     * @param array $existingAssignments
     */
    protected function processDeviceChunk(User $user, $devices, string $role, bool $force, bool $dryRun, array &$existingAssignments): void
    {
        $toInsert = [];
        $toUpdate = [];

        foreach ($devices as $device) {
            if (isset($existingAssignments[$device->id])) {
                // Assignment exists
                if ($force) {
                    $toUpdate[] = $device->id;
                    $this->line("  🔄 Will update: Device #{$device->id} ({$device->serial_number}) - Role: {$role}");
                    $this->stats['assignments_updated']++;
                } else {
                    $currentRole = $existingAssignments[$device->id];
                    $this->line("  ⏭️  Skipped: Device #{$device->id} (role '{$currentRole}')");
                    $this->stats['assignments_skipped']++;
                }
            } else {
                // New assignment
                $toInsert[] = [
                    'user_id' => $user->id,
                    'cpe_device_id' => $device->id,
                    'role' => $role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $this->line("  ✅ Will assign: Device #{$device->id} ({$device->serial_number}) - Role: {$role}");
                $this->stats['assignments_created']++;
            }
        }

        // Batch operations within scoped transaction (not global!)
        if (!$dryRun && (count($toInsert) > 0 || count($toUpdate) > 0)) {
            DB::transaction(function () use ($user, $role, $toInsert, $toUpdate) {
                // Batch insert new assignments
                if (count($toInsert) > 0) {
                    DB::table('user_devices')->insert($toInsert);
                }

                // Batch update existing assignments
                if (count($toUpdate) > 0) {
                    DB::table('user_devices')
                        ->where('user_id', $user->id)
                        ->whereIn('cpe_device_id', $toUpdate)
                        ->update([
                            'role' => $role,
                            'updated_at' => now(),
                        ]);
                }
            });
        }
    }
}
