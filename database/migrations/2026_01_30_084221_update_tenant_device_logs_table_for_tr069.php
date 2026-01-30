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
        Schema::table('tenant_device_logs', function (Blueprint $table) {
            // Ensure all required columns exist
            if (!Schema::hasColumn('tenant_device_logs', 'tenant_device_id')) {
                $table->foreignId('tenant_device_id')->constrained('tenant_devices')->onDelete('cascade');
            }
            
            if (!Schema::hasColumn('tenant_device_logs', 'log_type')) {
                $table->enum('log_type', ['info', 'warning', 'error', 'success'])->default('info');
            }
            
            if (!Schema::hasColumn('tenant_device_logs', 'message')) {
                $table->string('message');
            }
            
            if (!Schema::hasColumn('tenant_device_logs', 'raw_payload')) {
                $table->json('raw_payload')->nullable();
            }
            
            // Add indexes if they don't exist
            $table->index(['tenant_device_id', 'created_at'], 'tenant_device_logs_device_created_idx');
            $table->index('log_type', 'tenant_device_logs_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_device_logs', function (Blueprint $table) {
            $table->dropIndex('tenant_device_logs_device_created_idx');
            $table->dropIndex('tenant_device_logs_type_idx');
        });
    }
};
