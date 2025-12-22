<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\User;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $defaultTenant = Tenant::firstOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'Default Organization',
                'slug' => 'default',
                'is_active' => true,
                'max_devices' => 100000,
                'max_users' => 1000,
                'settings' => [
                    'features' => [
                        'ai_assistant' => true,
                        'bulk_operations' => true,
                        'advanced_diagnostics' => true,
                    ],
                    'branding' => [
                        'primary_color' => '#5e72e4',
                        'logo_url' => null,
                    ],
                ],
                'notes' => 'System default tenant',
            ]
        );

        $this->command->info("Default tenant created/updated: {$defaultTenant->name} (ID: {$defaultTenant->id})");

        User::whereNull('tenant_id')->update(['tenant_id' => $defaultTenant->id]);
        
        $updatedUsers = User::where('tenant_id', $defaultTenant->id)->count();
        $this->command->info("Assigned {$updatedUsers} users to default tenant");
    }
}
