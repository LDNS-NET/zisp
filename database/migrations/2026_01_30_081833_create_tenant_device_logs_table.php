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
        Schema::create('tenant_device_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_device_id')->constrained('tenant_devices')->onDelete('cascade');
            $table->enum('log_type', ['info', 'warning', 'error', 'success'])->default('info');
            $table->string('message');
            $table->json('raw_payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            // Index for efficient queries
            $table->index(['tenant_device_id', 'created_at']);
            $table->index('log_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_device_logs');
    }
};
