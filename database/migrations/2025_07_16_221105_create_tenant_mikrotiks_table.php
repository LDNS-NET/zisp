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
        Schema::create('tenant_mikrotiks', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['pending', 'online', 'offline'])->default('pending');
            $table->string('name');
            $table->string('wireguard_address')->nullable();
            $table->string('wireguard_public_key')->nullable();
            $table->string('wireguard_allowed_ips')->nullable();
            $table->string('wireguard_port')->nullable();
            $table->string('wireguard_status')->nullable();
            $table->string('api_username')->nullable();
            $table->string('api_password')->nullable();
            $table->integer('api_port')->default(8728);
            $table->integer('ssh_port')->default(22);
            $table->string('router_username')->nullable();
            $table->string('router_password')->nullable(); // encrypted
            $table->boolean('online')->default(false);
            $table->enum('connection_type', ['api', 'ssh', 'ovpn'])->default('api');
            $table->timestamp('last_seen_at')->nullable();
            $table->string('model')->nullable(); // RB750, RB450G, etc.
            $table->string('architecture')->nullable(); // mipsbe, arm, etc.
            $table->string('version')->nullable(); // RouterOS version
            $table->bigInteger('uptime')->nullable(); // in seconds
            $table->decimal('cpu_usage', 5, 2)->nullable(); // percentage
            $table->decimal('cpu', 5, 2)->nullable(); // percentage
            $table->decimal('memory_usage', 5, 2)->nullable(); // percentage
            $table->decimal('memory', 5, 2)->nullable(); // percentage
            $table->decimal('temperature', 5, 2)->nullable(); // in celsius
            $table->text('notes')->nullable();
            $table->string('sync_token', 64)->nullable(); // for secure sync endpoint
            $table->unsignedBigInteger('created_by')->nullable(); // for tenant/user scoping
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_mikrotiks');
    }
};
