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
            if (Schema::hasColumn('tenant_equipment_usages', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->change();
            } else {
                $table->string('tenant_id')->nullable()->after('id')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_equipment_usages', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_equipment_usages', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            }
        });
    }
};
