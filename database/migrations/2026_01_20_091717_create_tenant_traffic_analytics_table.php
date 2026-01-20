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
        Schema::create('tenant_traffic_analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('date');
            $table->tinyInteger('hour'); // 0-23
            $table->unsignedBigInteger('bytes_in')->default(0);
            $table->unsignedBigInteger('bytes_out')->default(0);
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->string('protocol', 50)->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['tenant_id', 'date']);
            $table->index(['user_id', 'date']);
            $table->index(['tenant_id', 'user_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_traffic_analytics');
    }
};
