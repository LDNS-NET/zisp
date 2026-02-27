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
        Schema::table('packages', function (Blueprint $table) {
            $table->foreignId('hotspot_category_id')->nullable()->constrained('hotspot_categories')->onDelete('set null');
        });

        Schema::table('tenant_hotspot_packages', function (Blueprint $table) {
            $table->foreignId('hotspot_category_id')->nullable()->constrained('hotspot_categories')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_hotspot_packages', function (Blueprint $table) {
            $table->dropForeign(['hotspot_category_id']);
            $table->dropColumn('hotspot_category_id');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropForeign(['hotspot_category_id']);
            $table->dropColumn('hotspot_category_id');
        });
    }
};
