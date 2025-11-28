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
        Schema::create('tenant_hotspot', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->string('name');
            $table->string('duration_value');
            $table->string('duration_unit');
            $table->string('price');
            $table->string('device_limit');
            $table->string('upload_speed');
            $table->string('download_speed');
            $table->string('burst_limit');
            $table->string('created_by');
            $table->string('domain');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_hotspot');
    }
};
