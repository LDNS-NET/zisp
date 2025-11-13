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
        Schema::table('tenant_mikrotiks', function (Blueprint $table) {
            // Tenant scope
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->index('tenant_id');
            // Device Information
            $table->string('name')->default('Mikrotik Device');
            $table->string('hostname')->nullable();
            $table->string('ip_address')->nullable();
            $table->integer('api_port')->default(8728);
            
            // API Credentials (encrypted)
            $table->string('api_username')->nullable();
            $table->text('api_password')->nullable(); // Will be encrypted via model casts
            
            // Onboarding & Authentication
            $table->string('sync_token')->unique();
            $table->string('onboarding_token')->nullable()->unique();
            
            // Status Tracking
            $table->enum('status', ['pending', 'onboarding', 'connected', 'disconnected', 'error'])->default('pending');
            $table->enum('onboarding_status', ['not_started', 'in_progress', 'completed', 'failed'])->default('not_started');
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            
            // Configuration URLs
            $table->text('onboarding_script_url')->nullable();
            $table->text('onboarding_script_content')->nullable();
            
            // Device Details (populated after sync)
            $table->string('device_id')->nullable();
            $table->string('board_name')->nullable();
            $table->string('system_version')->nullable();
            $table->string('interface_count')->nullable();
            
            // Error Handling
            $table->text('last_error')->nullable();
            $table->integer('sync_attempts')->default(0);
            $table->integer('connection_failures')->default(0);
            
            // Tracking
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            
            // Indexes for performance
            $table->index('status');
            $table->index('onboarding_status');
            $table->index('ip_address');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_mikrotiks', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\User::class, 'created_by');
            $table->dropForeignIdFor(\App\Models\Tenant::class, 'tenant_id');
            $table->dropIndex(['status']);
            $table->dropIndex(['onboarding_status']);
            $table->dropIndex(['ip_address']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['tenant_id']);
            
            $table->dropColumn([
                'tenant_id',
                'name', 'hostname', 'ip_address', 'api_port', 'api_username', 'api_password',
                'sync_token', 'onboarding_token', 'status', 'onboarding_status', 'onboarding_completed_at',
                'last_connected_at', 'last_seen_at', 'onboarding_script_url', 'onboarding_script_content',
                'device_id', 'board_name', 'system_version', 'interface_count', 'last_error',
                'sync_attempts', 'connection_failures', 'created_by'
            ]);
        });
    }
};
