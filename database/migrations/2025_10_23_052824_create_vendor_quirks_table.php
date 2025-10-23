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
        Schema::create('vendor_quirks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_id')->constrained('router_manufacturers')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained('router_products')->onDelete('cascade');
            
            $table->string('quirk_type');
            $table->string('quirk_name');
            $table->text('description');
            
            $table->string('affects_protocol')->nullable();
            $table->string('firmware_versions_affected')->nullable();
            
            $table->json('workaround_config')->nullable();
            $table->text('workaround_notes')->nullable();
            
            $table->string('severity')->default('low');
            $table->boolean('auto_apply')->default(false);
            
            $table->string('discovered_by')->nullable();
            $table->timestamp('discovered_at')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->index('manufacturer_id');
            $table->index('product_id');
            $table->index('quirk_type');
            $table->index('affects_protocol');
            $table->index(['manufacturer_id', 'quirk_type']);
            $table->index(['is_active', 'severity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_quirks');
    }
};
