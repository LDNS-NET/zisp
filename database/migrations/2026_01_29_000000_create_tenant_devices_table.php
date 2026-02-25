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
        if (!Schema::hasTable('tenant_devices')) {
            Schema::create('tenant_devices', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable()->index(); // Nullable as per model global scope
                $table->unsignedBigInteger('subscriber_id')->nullable()->index();
                
                $table->string('serial_number')->nullable()->index();
                $table->string('model')->nullable();
                $table->string('manufacturer')->nullable();
                $table->string('software_version')->nullable();
                $table->string('mac_address')->nullable();
                $table->string('wan_ip')->nullable();
                $table->string('lan_ip')->nullable();
                
                $table->boolean('online')->default(false);
                $table->timestamp('last_contact_at')->nullable();
                
                $table->timestamps();

                // Foreign Keys (Optional but good practice if tables exist)
                // $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                // $table->foreign('subscriber_id')->references('id')->on('network_users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_devices');
    }
};
