<?php
// database/migrations/2026_02_23_013227_create_tenant_equipment_serials_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tenant_equipment_serials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('equipment_id')->constrained('tenant_equipments')->onDelete('cascade');
            $table->string('serial')->nullable();
            $table->string('mac')->nullable();
            $table->enum('status', ['available', 'in_use', 'maintenance', 'retired'])->default('available');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('network_users')->onDelete('set null');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->unique(['equipment_id', 'serial']);
            $table->index('status');
            $table->index('tenant_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tenant_equipment_serials');
    }
};