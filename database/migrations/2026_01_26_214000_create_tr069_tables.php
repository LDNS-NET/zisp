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
        Schema::create('tenant_devices', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable(); // Link to tenant
            $table->unsignedBigInteger('subscriber_id')->nullable(); // Link to NetworkUser
            $table->string('serial_number')->unique();
            $table->string('model')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('software_version')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('wan_ip')->nullable();
            $table->string('lan_ip')->nullable();
            $table->boolean('online')->default(false);
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamps();

            // Indexing for performance
            $table->index('tenant_id');
            $table->index('subscriber_id');
            $table->index('serial_number');
        });

        Schema::create('tenant_device_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_device_id');
            $table->enum('log_type', ['info', 'warning', 'error'])->default('info');
            $table->text('message');
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->foreign('tenant_device_id')->references('id')->on('tenant_devices')->onDelete('cascade');
        });

        Schema::create('tenant_device_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_device_id');
            $table->string('action'); // reboot, reset, change_wifi, etc.
            $table->json('payload')->nullable();
            $table->enum('status', ['pending', 'sent', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('tenant_device_id')->references('id')->on('tenant_devices')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_device_actions');
        Schema::dropIfExists('tenant_device_logs');
        Schema::dropIfExists('tenant_devices');
    }
};
