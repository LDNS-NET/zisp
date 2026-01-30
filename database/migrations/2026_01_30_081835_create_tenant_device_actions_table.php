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
        Schema::create('tenant_device_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_device_id')->constrained('tenant_devices')->onDelete('cascade');
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
            $table->json('payload')->nullable()->comment('Action parameters and values');
            $table->enum('status', ['pending', 'sent', 'completed', 'failed'])->default('pending')->index();
            $table->string('genieacs_task_id')->nullable()->unique();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['tenant_device_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_device_actions');
    }
};
