<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Legacy Fallback Removal Migration
     * 
     * This migration enforces tenant isolation by making tenant_id NOT NULL
     * on all multi-tenant tables. Run this ONLY after all existing records
     * have been assigned a tenant_id.
     * 
     * Prerequisites:
     * - All cpe_devices must have tenant_id assigned
     * - All alarms must have tenant_id assigned  
     * - All users must have tenant_id assigned
     * - Default tenant must exist (id=1)
     */
    public function up(): void
    {
        // Ensure default tenant exists
        $defaultTenant = DB::table('tenants')->where('id', 1)->first();
        if (!$defaultTenant) {
            DB::table('tenants')->insert([
                'id' => 1,
                'name' => 'Default Tenant',
                'slug' => 'default',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Assign default tenant to orphaned records
        DB::table('users')->whereNull('tenant_id')->update(['tenant_id' => 1]);
        DB::table('cpe_devices')->whereNull('tenant_id')->update(['tenant_id' => 1]);
        DB::table('alarms')->whereNull('tenant_id')->update(['tenant_id' => 1]);

        // Make tenant_id NOT NULL on users (no default - explicit assignment required)
        if (Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            });
        }

        // Make tenant_id NOT NULL on cpe_devices (no default - explicit assignment required)
        if (Schema::hasColumn('cpe_devices', 'tenant_id')) {
            Schema::table('cpe_devices', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            });
        }

        // Make tenant_id NOT NULL on alarms (no default - explicit assignment required)
        if (Schema::hasColumn('alarms', 'tenant_id')) {
            Schema::table('alarms', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            });
        }

        // Make tenant_id NOT NULL on sessions (no default - explicit assignment required)
        if (Schema::hasColumn('sessions', 'tenant_id')) {
            Schema::table('sessions', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            });
        }

        // Make tenant_id NOT NULL on personal_access_tokens (no default - explicit assignment required)
        if (Schema::hasColumn('personal_access_tokens', 'tenant_id')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations - restore nullable tenant_id for rollback
     */
    public function down(): void
    {
        // Restore nullable on users
        if (Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        }

        // Restore nullable on cpe_devices
        if (Schema::hasColumn('cpe_devices', 'tenant_id')) {
            Schema::table('cpe_devices', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        }

        // Restore nullable on alarms
        if (Schema::hasColumn('alarms', 'tenant_id')) {
            Schema::table('alarms', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        }

        // Restore nullable on sessions
        if (Schema::hasColumn('sessions', 'tenant_id')) {
            Schema::table('sessions', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        }

        // Restore nullable on personal_access_tokens
        if (Schema::hasColumn('personal_access_tokens', 'tenant_id')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        }
    }
};
