<?php
// database/migrations/2026_02_23_013228_create_tenant_stock_movements_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tenant_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('equipment_id')->constrained('tenant_equipments')->onDelete('cascade');
            $table->string('location');
            $table->decimal('quantity', 10, 2);
            $table->enum('direction', ['IN', 'OUT', 'TRANSFER']);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['equipment_id', 'created_at']);
            $table->index('direction');
            $table->index('tenant_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tenant_stock_movements');
    }
};