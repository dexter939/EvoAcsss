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
                            {--force : Force overwrite existing assignments}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill user_devices table with existing devices and users';

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

        // Validate role
        $validRoles = ['viewer', 'manager', 'admin'];
        if (!in_array($defaultRole, $validRoles)) {
            $this->error("Invalid role: {$defaultRole}. Must be one of: " . implode(', ', $validRoles));
            return self::FAILURE;
        }

        $this->info('🔄 Device Access Backfill Tool');
        $this->info('================================');
        
        if ($dryRun) {
            $this->warn('🧪 DRY-RUN MODE: No changes will be made');
        }

        // Get users and devices
        $usersQuery = User::query();
        $devicesQuery = CpeDevice::query();

        if ($userFilter) {
            $usersQuery->where('id', $userFilter);
        }

        if ($deviceFilter) {
            $devicesQuery->where('id', $deviceFilter);
        }

        $users = $usersQuery->get();
        $devices = $devicesQuery->get();

        $this->info("📊 Found {$users->count()} users and {$devices->count()} devices");

        if ($users->isEmpty()) {
            $this->warn('⚠️  No users found in the database');
            return self::SUCCESS;
        }

        if ($devices->isEmpty()) {
            $this->warn('⚠️  No devices found in the database');
            return self::SUCCESS;
        }

        $stats = [
            'super_admins' => 0,
            'regular_users' => 0,
            'assignments_created' => 0,
            'assignments_skipped' => 0,
            'assignments_updated' => 0,
        ];

        DB::beginTransaction();

        try {
            foreach ($users as $user) {
                $isSuperAdmin = $user->isSuperAdmin();
                
                if ($isSuperAdmin) {
                    $stats['super_admins']++;
                    
                    if ($skipSuperAdmin) {
                        $this->line("⏭️  Skipping super-admin: {$user->name} (#{$user->id})");
                        continue;
                    }

                    // Super-admins get access to ALL devices with 'admin' role
                    $this->info("👑 Processing super-admin: {$user->name} (#{$user->id})");
                    
                    foreach ($devices as $device) {
                        $result = $this->assignDevice($user, $device, 'admin', $force, $dryRun);
                        $stats[$result]++;
                    }
                } else {
                    $stats['regular_users']++;
                    
                    // Regular users: assign with default role
                    $this->line("👤 Processing user: {$user->name} (#{$user->id}) with role '{$defaultRole}'");
                    
                    foreach ($devices as $device) {
                        $result = $this->assignDevice($user, $device, $defaultRole, $force, $dryRun);
                        $stats[$result]++;
                    }
                }
            }

            if ($dryRun) {
                DB::rollBack();
                $this->warn('🧪 DRY-RUN: Transaction rolled back');
            } else {
                DB::commit();
                $this->info('✅ Transaction committed');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error: ' . $e->getMessage());
            return self::FAILURE;
        }

        // Display summary
        $this->newLine();
        $this->info('📈 Backfill Summary');
        $this->info('===================');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Super-admins processed', $stats['super_admins']],
                ['Regular users processed', $stats['regular_users']],
                ['Assignments created', $stats['assignments_created']],
                ['Assignments skipped (existing)', $stats['assignments_skipped']],
                ['Assignments updated (forced)', $stats['assignments_updated']],
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
     * Assign device to user with specified role
     * 
     * @param User $user
     * @param CpeDevice $device
     * @param string $role
     * @param bool $force
     * @param bool $dryRun
     * @return string Result status (assignments_created, assignments_skipped, assignments_updated)
     */
    protected function assignDevice(User $user, CpeDevice $device, string $role, bool $force, bool $dryRun): string
    {
        // Check if assignment already exists
        $existing = $user->devices()->where('cpe_device_id', $device->id)->first();

        if ($existing) {
            if ($force) {
                // Update existing assignment
                if (!$dryRun) {
                    $user->devices()->updateExistingPivot($device->id, [
                        'role' => $role,
                        'updated_at' => now(),
                    ]);
                }
                
                $this->line("  🔄 Updated: Device #{$device->id} ({$device->serial_number}) - Role: {$role}");
                return 'assignments_updated';
            } else {
                // Skip existing
                $currentRole = $existing->pivot->role;
                $this->line("  ⏭️  Skipped: Device #{$device->id} (already assigned with role '{$currentRole}')");
                return 'assignments_skipped';
            }
        }

        // Create new assignment
        if (!$dryRun) {
            $user->devices()->attach($device->id, [
                'role' => $role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->line("  ✅ Assigned: Device #{$device->id} ({$device->serial_number}) - Role: {$role}");
        return 'assignments_created';
    }
}
