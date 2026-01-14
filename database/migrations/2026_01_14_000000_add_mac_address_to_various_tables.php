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
        // Add mac_address to network_users
        if (Schema::hasTable('network_users')) {
            Schema::table('network_users', function (Blueprint $table) {
                if (!Schema::hasColumn('network_users', 'mac_address')) {
                    $table->string('mac_address')->nullable()->after('type')->index();
                }
            });
        }

        // Add mac_address to tenant_payments
        if (Schema::hasTable('tenant_payments')) {
            Schema::table('tenant_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('tenant_payments', 'mac_address')) {
                    $table->string('mac_address')->nullable()->after('hotspot_package_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('network_users')) {
            Schema::table('network_users', function (Blueprint $table) {
                $table->dropColumn('mac_address');
            });
        }

        if (Schema::hasTable('tenant_payments')) {
            Schema::table('tenant_payments', function (Blueprint $table) {
                $table->dropColumn('mac_address');
            });
        }
    }
};
