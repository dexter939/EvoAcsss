<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates user_devices pivot table for multi-tenant device access control.
     * Each user can access multiple devices with specific roles (viewer, manager, admin).
     */
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('cpe_device_id')->constrained('cpe_devices')->onDelete('cascade');
            
            // Role for device access (viewer, manager, admin)
            // - viewer: Read-only access to device data
            // - manager: Can configure device and execute commands
            // - admin: Full access including deletion and firmware updates
            $table->enum('role', ['viewer', 'manager', 'admin'])->default('viewer');
            
            // Optional: Department/Group for organization
            $table->string('department')->nullable();
            
            // Timestamps for auditing
            $table->timestamps();
            
            // Unique constraint: A user can only have one role per device
            $table->unique(['user_id', 'cpe_device_id']);
            
            // Indexes for performance
            $table->index('user_id');
            $table->index('cpe_device_id');
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
