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
        Schema::create('configuration_templates_library', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_id')->constrained('router_manufacturers')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained('router_products')->onDelete('cascade');
            
            $table->string('template_name');
            $table->string('template_category');
            $table->text('description')->nullable();
            
            $table->string('protocol')->default('TR-069');
            $table->json('parameter_values');
            
            $table->json('applicable_firmware_versions')->nullable();
            $table->json('required_capabilities')->nullable();
            
            $table->boolean('is_official')->default(false);
            $table->boolean('is_tested')->default(false);
            $table->integer('usage_count')->default(0);
            
            $table->string('created_by')->nullable();
            $table->text('usage_notes')->nullable();
            $table->integer('rating')->nullable();
            
            $table->timestamps();
            
            $table->index('manufacturer_id');
            $table->index('product_id');
            $table->index('template_category');
            $table->index('protocol');
            $table->index(['manufacturer_id', 'template_category']);
            $table->index(['is_official', 'is_tested']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuration_templates_library');
    }
};
