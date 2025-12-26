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
        Schema::table('tenant_hotspot_packages', function (Blueprint $table) {
        $table->unsignedBigInteger('package_id')->after('id')->nullable();
        $table->foreign('package_id')->references('id')->on('packages')->onDelete('cascade');
    });

    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_hotspot_packages', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropColumn('package_id');

        });
    }
};
