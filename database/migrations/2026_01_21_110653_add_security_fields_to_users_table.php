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
        Schema::table('users', function (Blueprint $table) {
            $table->json('working_hours')->nullable()->after('is_suspended');
            $table->json('allowed_ips')->nullable()->after('working_hours');
            $table->json('security_config')->nullable()->after('allowed_ips');
            $table->boolean('is_device_lock_enabled')->default(false)->after('security_config');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['working_hours', 'allowed_ips', 'security_config', 'is_device_lock_enabled']);
        });
    }
};
