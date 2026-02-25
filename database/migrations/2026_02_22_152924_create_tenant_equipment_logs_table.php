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
        Schema::create('tenant_equipment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('tenant_equipments')->onDelete('cascade');
            $table->string('action'); // status_change, assigned, unassigned, created
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->foreignId('performed_by')->constrained('users')->onDelete('cascade');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_equipment_logs');
    }
};
