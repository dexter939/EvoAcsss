<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\CpeDevice;
use Illuminate\Support\Facades\Hash;

class DeviceAccessTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates test data for multi-tenant device access backfill:
     * - 1 super-admin user
     * - 3 regular users
     * - 10 CPE devices
     */
    public function run(): void
    {
        $this->command->info('🌱 Seeding test data for Device Access Backfill...');

        // Create or get super-admin role
        $superAdminRole = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            [
                'name' => 'Super Administrator',
                'description' => 'Full system access with bypass permissions',
            ]
        );

        // Create super-admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@acs.local'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password'),
            ]
        );
        
        if (!$admin->hasRole('super-admin')) {
            $admin->assignRole($superAdminRole);
        }
        
        $this->command->info("✅ Created super-admin: {$admin->email}");

        // Create regular users
        $regularUsers = [
            ['name' => 'Marco Rossi', 'email' => 'marco.rossi@acs.local'],
            ['name' => 'Laura Bianchi', 'email' => 'laura.bianchi@acs.local'],
            ['name' => 'Giuseppe Verdi', 'email' => 'giuseppe.verdi@acs.local'],
        ];

        foreach ($regularUsers as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                ]
            );
            
            $this->command->info("✅ Created user: {$user->email}");
        }

        // Create CPE devices
        $vendors = ['ZTE', 'Huawei', 'Nokia', 'Ericsson', 'TP-Link'];
        $models = ['HG8245H', 'EG8145V5', 'G-240W-C', 'ONT-2426H', 'TD-W8961N'];
        
        for ($i = 1; $i <= 10; $i++) {
            $vendor = $vendors[array_rand($vendors)];
            $model = $models[array_rand($models)];
            
            $device = CpeDevice::firstOrCreate(
                ['serial_number' => 'SN' . str_pad($i, 10, '0', STR_PAD_LEFT)],
                [
                    'manufacturer' => $vendor,
                    'oui' => strtoupper(substr(md5($vendor), 0, 6)),
                    'product_class' => $model,
                    'model_name' => $model,
                    'hardware_version' => '1.0',
                    'software_version' => '2.' . rand(0, 5) . '.0',
                    'status' => rand(0, 1) ? 'online' : 'offline',
                    'ip_address' => '192.168.' . rand(1, 254) . '.' . rand(1, 254),
                    'mac_address' => sprintf(
                        '%02X:%02X:%02X:%02X:%02X:%02X',
                        rand(0, 255), rand(0, 255), rand(0, 255),
                        rand(0, 255), rand(0, 255), rand(0, 255)
                    ),
                    'last_inform' => now()->subMinutes(rand(1, 1440)),
                    'is_active' => true,
                    'protocol_type' => rand(0, 1) ? 'tr069' : 'tr369',
                ]
            );

            $this->command->info("✅ Created device: {$device->serial_number} ({$vendor} {$model})");
        }

        $this->command->newLine();
        $this->command->info('✅ Test data seeding completed!');
        $this->command->info('📊 Summary:');
        $this->command->info('   - Users: ' . User::count());
        $this->command->info('   - Devices: ' . CpeDevice::count());
        $this->command->info('   - Assignments: ' . \DB::table('user_devices')->count());
        $this->command->newLine();
        $this->command->warn('💡 Run: php artisan devices:backfill-access --dry-run');
    }
}
