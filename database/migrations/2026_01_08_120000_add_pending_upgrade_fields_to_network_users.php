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
        Schema::table('network_users', function (Blueprint $table) {
            $table->unsignedBigInteger('pending_package_id')->nullable()->after('hotspot_package_id');
            $table->unsignedBigInteger('pending_hotspot_package_id')->nullable()->after('pending_package_id');
            $table->timestamp('pending_package_activation_at')->nullable()->after('pending_hotspot_package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('network_users', function (Blueprint $table) {
            $table->dropColumn(['pending_package_id', 'pending_hotspot_package_id', 'pending_package_activation_at']);
        });
    }
};
