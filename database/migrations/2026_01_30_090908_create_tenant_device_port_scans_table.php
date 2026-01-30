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
        Schema::create('tenant_device_port_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_device_id')->constrained('tenant_devices')->onDelete('cascade');
            $table->enum('scan_type', ['auto', 'manual'])->default('manual');
            $table->json('ports_found')->nullable()->comment('Array of discovered port information');
            $table->enum('scan_status', ['pending', 'running', 'completed', 'failed'])->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index(['tenant_device_id', 'created_at'], 'port_scans_device_date_idx');
            $table->index('scan_status', 'port_scans_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_device_port_scans');
    }
};
