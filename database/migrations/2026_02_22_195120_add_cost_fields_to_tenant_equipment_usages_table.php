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
        Schema::table('tenant_equipment_usages', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 2)->default(0)->after('quantity');
            $table->decimal('total_cost', 12, 2)->default(0)->after('unit_cost');
            $table->string('reference_no')->nullable()->after('total_cost'); // To group multiple items in one form
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_equipment_usages', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'total_cost', 'reference_no']);
        });
    }
};
