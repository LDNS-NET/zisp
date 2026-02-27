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
        if (!Schema::hasColumn('packages', 'hotspot_category_id')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->foreignId('hotspot_category_id')->nullable()->constrained('hotspot_categories')->onDelete('set null');
            });
        } else {
            // If column exists, just add the constraint
            Schema::table('packages', function (Blueprint $table) {
                $table->foreign('hotspot_category_id')->references('id')->on('hotspot_categories')->onDelete('set null');
            });
        }

        if (!Schema::hasColumn('tenant_hotspot_packages', 'hotspot_category_id')) {
            Schema::table('tenant_hotspot_packages', function (Blueprint $table) {
                $table->foreignId('hotspot_category_id')->nullable()->constrained('hotspot_categories')->onDelete('set null');
            });
        } else {
            // If column exists, just add the constraint
            Schema::table('tenant_hotspot_packages', function (Blueprint $table) {
                $table->foreign('hotspot_category_id')->references('id')->on('hotspot_categories')->onDelete('set null');
            });
        }
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
