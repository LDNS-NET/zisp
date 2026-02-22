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
        Schema::table('tenant_equipments', function (Blueprint $table) {
            // Change quantity to decimal to support units like meters, feet, etc.
            if (Schema::hasColumn('tenant_equipments', 'quantity')) {
                $table->decimal('quantity', 12, 2)->default(0)->change();
            }
            
            if (!Schema::hasColumn('tenant_equipments', 'unit')) {
                $table->string('unit')->default('pcs')->after('quantity');
            }
        });

        Schema::table('tenant_equipment_usages', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_equipment_usages', 'quantity')) {
                $table->decimal('quantity', 12, 2)->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_equipments', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_equipments', 'unit')) {
                $table->dropColumn('unit');
            }
            if (Schema::hasColumn('tenant_equipments', 'quantity')) {
                $table->integer('quantity')->default(0)->change();
            }
        });

        Schema::table('tenant_equipment_usages', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_equipment_usages', 'quantity')) {
                $table->integer('quantity')->change();
            }
        });
    }
};
