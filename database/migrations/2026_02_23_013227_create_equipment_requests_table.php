<?php
// database/migrations/2026_02_23_013230_create_tenant_equipment_requests_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tenant_equipment_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('technician_id')->constrained('network_users')->onDelete('cascade');
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->enum('priority', ['LOW', 'MEDIUM', 'HIGH', 'URGENT'])->default('MEDIUM');
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'FULFILLED'])->default('PENDING');
            $table->json('items')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index('status');
            $table->index('priority');
            $table->index('technician_id');
            $table->index('tenant_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tenant_equipment_requests');
    }
};