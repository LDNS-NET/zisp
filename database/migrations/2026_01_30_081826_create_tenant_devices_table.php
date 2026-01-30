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
            $table->string('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('subscriber_id')->nullable();
            $table->unsignedBigInteger('mikrotik_id')->nullable()->comment('Associated MikroTik router');
            $table->string('serial_number')->unique();
            $table->string('model')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('firmware')->nullable();
            $table->string('software_version')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('wan_ip')->nullable();
            $table->string('lan_ip')->nullable();
            $table->boolean('online')->default(false);
            $table->timestamp('last_contact_at')->nullable();
            $table->json('raw_parameters')->nullable()->comment('Full device data model from GenieACS');
            $table->string('connection_request_url')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('subscriber_id')
                  ->references('id')->on('network_users')
                  ->onDelete('set null');
                  
            $table->foreign('mikrotik_id')
                  ->references('id')->on('tenant_mikrotiks')
                  ->onDelete('set null');
            
            // Indexes for performance
            $table->index(['tenant_id', 'online']);
            $table->index('last_contact_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_devices');
    }
};
