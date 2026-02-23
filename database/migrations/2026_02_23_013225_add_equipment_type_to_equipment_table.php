<?php
// database/migrations/2026_02_23_013225_add_equipment_type_to_tenant_equipments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tenant_equipments', function (Blueprint $table) {
            $table->string('equipment_type')->default('PROVISIONAL')->after('type');
            $table->integer('min_stock')->default(5)->after('quantity');
            $table->decimal('purchase_price', 10, 2)->nullable()->after('price');
            $table->integer('depreciation_rate')->default(15)->nullable()->after('purchase_price');
            $table->integer('maintenance_interval')->default(90)->nullable()->after('depreciation_rate');
            $table->date('last_maintenance_date')->nullable()->after('maintenance_interval');
            $table->date('next_maintenance_date')->nullable()->after('last_maintenance_date');
            $table->string('cable_type')->nullable()->after('next_maintenance_date');
            $table->decimal('cable_length', 10, 2)->nullable()->after('cable_type');
        });
    }

    public function down()
    {
        Schema::table('tenant_equipments', function (Blueprint $table) {
            $table->dropColumn([
                'equipment_type',
                'min_stock',
                'purchase_price',
                'depreciation_rate',
                'maintenance_interval',
                'last_maintenance_date',
                'next_maintenance_date',
                'cable_type',
                'cable_length'
            ]);
        });
    }
};