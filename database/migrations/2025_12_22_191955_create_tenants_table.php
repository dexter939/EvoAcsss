<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('domain')->nullable();
            $table->string('subdomain', 100)->nullable()->unique();
            $table->json('settings')->default('{}');
            $table->string('api_key', 64)->unique()->nullable();
            $table->string('api_secret', 128)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('max_devices')->default(10000);
            $table->integer('max_users')->default(100);
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('api_secret_rotated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('slug');
        });

        Schema::create('tenant_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('credential_type', 50);
            $table->string('credential_key', 255);
            $table->text('credential_secret')->nullable();
            $table->json('scopes')->default('[]');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'credential_type']);
            $table->index('credential_key');
        });

        if (Schema::hasTable('personal_access_tokens')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                if (!Schema::hasColumn('personal_access_tokens', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->after('tokenable_id');
                    $table->index('tenant_id');
                }
                if (!Schema::hasColumn('personal_access_tokens', 'tenant_abilities')) {
                    $table->json('tenant_abilities')->nullable()->after('abilities');
                }
            });
        }

        if (Schema::hasTable('sessions')) {
            Schema::table('sessions', function (Blueprint $table) {
                if (!Schema::hasColumn('sessions', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->after('user_id');
                    $table->index('tenant_id');
                }
            });
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('audit_logs', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                    $table->index('tenant_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                if (Schema::hasColumn('personal_access_tokens', 'tenant_id')) {
                    $table->dropIndex(['tenant_id']);
                    $table->dropColumn('tenant_id');
                }
                if (Schema::hasColumn('personal_access_tokens', 'tenant_abilities')) {
                    $table->dropColumn('tenant_abilities');
                }
            });
        }

        if (Schema::hasTable('sessions')) {
            Schema::table('sessions', function (Blueprint $table) {
                if (Schema::hasColumn('sessions', 'tenant_id')) {
                    $table->dropIndex(['tenant_id']);
                    $table->dropColumn('tenant_id');
                }
            });
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                if (Schema::hasColumn('audit_logs', 'tenant_id')) {
                    $table->dropIndex(['tenant_id']);
                    $table->dropColumn('tenant_id');
                }
            });
        }

        Schema::dropIfExists('tenant_credentials');
        Schema::dropIfExists('tenants');
    }
};
