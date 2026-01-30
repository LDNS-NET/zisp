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
        Schema::table('tenant_device_actions', function (Blueprint $table) {
            // Ensure all required columns exist
            if (!Schema::hasColumn('tenant_device_actions', 'tenant_device_id')) {
                $table->foreignId('tenant_device_id')->constrained('tenant_devices')->onDelete('cascade');
            }
            
            if (!Schema::hasColumn('tenant_device_actions', 'action')) {
                $table->enum('action', [
                    'reboot',
                    'factory_reset',
                    'update_firmware',
                    'change_wifi',
                    'change_pppoe',
                    'sync_params',
                    'refresh_object',
                    'get_parameters'
                ])->index();
            }
            
            if (!Schema::hasColumn('tenant_device_actions', 'payload')) {
                $table->json('payload')->nullable()->comment('Action parameters and values');
            }
            
            if (!Schema::hasColumn('tenant_device_actions', 'status')) {
                $table->enum('status', ['pending', 'sent', 'completed', 'failed'])->default('pending')->index();
            }
            
            if (!Schema::hasColumn('tenant_device_actions', 'genieacs_task_id')) {
                $table->string('genieacs_task_id')->nullable()->unique();
            }
            
            if (!Schema::hasColumn('tenant_device_actions', 'error_message')) {
                $table->text('error_message')->nullable();
            }
            
            if (!Schema::hasColumn('tenant_device_actions', 'sent_at')) {
                $table->timestamp('sent_at')->nullable();
            }
            
            if (!Schema::hasColumn('tenant_device_actions', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
            
            // Add composite index for efficient querying
            $table->index(['tenant_device_id', 'status', 'created_at'], 'tenant_device_actions_device_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_device_actions', function (Blueprint $table) {
            $table->dropIndex('tenant_device_actions_device_status_idx');
            
            // Only drop columns that were added by this migration
            $columnsToCheck = [
                'genieacs_task_id',
                'error_message',
                'sent_at',
                'completed_at'
            ];
            
            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('tenant_device_actions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
