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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            
            // User who performed the action
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('user_email')->nullable(); // Store email for audit trail
            
            // Auditable resource (polymorphic)
            $table->string('auditable_type')->nullable(); // Model class name
            $table->unsignedBigInteger('auditable_id')->nullable(); // Model ID
            
            // Event details
            $table->string('event'); // created, updated, deleted, restored, etc.
            $table->string('action')->nullable(); // Human-readable action description
            $table->text('description')->nullable(); // Detailed description
            
            // Changes tracking
            $table->json('old_values')->nullable(); // Before change
            $table->json('new_values')->nullable(); // After change
            $table->json('metadata')->nullable(); // Additional context
            
            // Request context
            $table->string('ip_address', 45)->nullable(); // IPv4/IPv6
            $table->text('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('route_name')->nullable();
            $table->string('http_method', 10)->nullable(); // GET, POST, PUT, DELETE
            
            // Categorization
            $table->string('tags')->nullable(); // Comma-separated tags for filtering
            $table->string('category')->default('general'); // device, user, config, firmware, etc.
            $table->string('severity')->default('info'); // info, warning, critical
            
            // Compliance fields
            $table->string('environment')->default('production'); // production, staging, development
            $table->boolean('compliance_critical')->default(false); // Flag for GDPR/SOC2/etc
            
            $table->timestamps();
            
            // Indexes for performance at 100K+ scale
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['event', 'created_at']);
            $table->index(['category', 'created_at']);
            $table->index(['severity', 'created_at']);
            $table->index('created_at'); // For date range queries
            $table->index('compliance_critical');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
