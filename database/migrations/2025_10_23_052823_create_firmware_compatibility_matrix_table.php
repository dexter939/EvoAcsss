<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('firmware_compatibility_matrix', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firmware_version_id')->constrained('firmware_versions')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('router_products')->onDelete('cascade');
            
            $table->string('compatibility_status')->default('compatible');
            $table->string('min_hardware_revision')->nullable();
            $table->string('max_hardware_revision')->nullable();
            
            $table->json('supported_features')->nullable();
            $table->json('known_issues')->nullable();
            $table->json('prerequisites')->nullable();
            
            $table->boolean('tested')->default(false);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('tested_by')->nullable();
            
            $table->text('installation_notes')->nullable();
            $table->text('rollback_notes')->nullable();
            
            $table->integer('performance_rating')->nullable();
            $table->integer('stability_rating')->nullable();
            
            $table->timestamps();
            
            $table->unique(['firmware_version_id', 'product_id']);
            $table->index('compatibility_status');
            $table->index('tested');
            $table->index(['product_id', 'compatibility_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('firmware_compatibility_matrix');
    }
};
