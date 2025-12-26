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
        Schema::create('tenant_hotspot_packages', function (Blueprint $table) {
    $table->id();

    // Tenant ownership
    $table->uuid('tenant_id')->index();
    $table->foreign('tenant_id')
        ->references('id')
        ->on('tenants')
        ->cascadeOnDelete();

    // Package details
    $table->string('name');

    $table->unsignedInteger('duration_value'); // e.g. 30
    $table->enum('duration_unit', ['minutes', 'hours', 'days', 'weeks', 'months']); // e.g. days, months

    $table->unsignedInteger('price'); // store in cents if possible
    $table->unsignedTinyInteger('device_limit')->default(1);

    // Speeds in Kbps (MikroTik friendly)
    $table->unsignedInteger('upload_speed')->nullable();
    $table->unsignedInteger('download_speed')->nullable();
    $table->unsignedInteger('burst_limit')->nullable();

    // Ownership / auditing
    $table->foreignId('created_by')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();

    // Status
    $table->boolean('active')->default(true);
    $table->timestamps();
    });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_hotspot_packages');
    }
};
