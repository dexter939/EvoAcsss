<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cpe_devices', function (Blueprint $table) {
            if (!Schema::hasColumn('cpe_devices', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }
        });

        Schema::table('alarms', function (Blueprint $table) {
            if (!Schema::hasColumn('alarms', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }
        });

        $defaultTenantId = DB::table('tenants')->where('slug', 'default')->value('id') ?? 1;

        DB::table('cpe_devices')->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
        DB::table('alarms')->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
    }

    public function down(): void
    {
        Schema::table('cpe_devices', function (Blueprint $table) {
            if (Schema::hasColumn('cpe_devices', 'tenant_id')) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('alarms', function (Blueprint $table) {
            if (Schema::hasColumn('alarms', 'tenant_id')) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });
    }
};
