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
        Schema::create('compensations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');
            $table->foreignId('user_id')->constrained('network_users')->onDelete('cascade');
            $table->integer('duration_value');
            $table->string('duration_unit'); // minutes, hours, days, weeks, months
            $table->timestamp('old_expires_at')->nullable();
            $table->timestamp('new_expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compensations');
    }
};
