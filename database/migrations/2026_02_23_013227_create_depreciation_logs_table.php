<?php
// database/migrations/2026_02_23_013229_create_tenant_depreciation_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tenant_depreciation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('equipment_id')->constrained('tenant_equipments')->onDelete('cascade');
            $table->decimal('original_value', 10, 2);
            $table->decimal('current_value', 10, 2);
            $table->decimal('depreciation_amount', 10, 2);
            $table->date('calculated_date');
            $table->json('schedule')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['equipment_id', 'calculated_date']);
            $table->index('tenant_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tenant_depreciation_logs');
    }
};