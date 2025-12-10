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
        Schema::create('all_mikrotiks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip_address')->index()->nullable();
            $table->string('model')->nullable();
            $table->integer('active_users')->default(0);
            $table->enum('status', ['online', 'offline'])->default('offline');
            $table->string('location')->nullable();
            $table->string('owner')->nullable();
            $table->string('api_username')->nullable();
            $table->string('api_password')->nullable();
            $table->string('wireguard_status')->nullable();
            $table->string('winbox')->nullable();
            $table->string('uptime')->nullable();
            $table->string('version')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('all_mikrotiks');
    }
};
