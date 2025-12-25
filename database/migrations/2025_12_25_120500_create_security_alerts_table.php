<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_alerts', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('type', 100)->index();
            $table->string('severity', 20)->default('medium');
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->json('data')->nullable();
            $table->boolean('is_acknowledged')->default(false);
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['severity', 'is_acknowledged']);
            $table->index(['tenant_id', 'is_acknowledged']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_alerts');
    }
};
