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
        $tables = [
            'tenant_leads',
            'tenant_tickets',
            'tenant_equipments',
            'tenant_sms',
            'tenant_sms_templates',
            'tenant_invoices',
            'tenant_active_users',
            'tenant_active_sessions',
            'tenant_bandwidth_usage',
            'tenant_open_vpn_profiles',
            'tenant_router_alerts',
            'tenant_router_logs',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (!Schema::hasColumn($tableName, 'tenant_id')) {
                        $table->string('tenant_id')->nullable()->after('id')->index();
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'tenant_leads',
            'tenant_tickets',
            'tenant_equipments',
            'tenant_sms',
            'tenant_sms_templates',
            'tenant_invoices',
            'tenant_active_users',
            'tenant_active_sessions',
            'tenant_bandwidth_usage',
            'tenant_open_vpn_profiles',
            'tenant_router_alerts',
            'tenant_router_logs',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (Schema::hasColumn($tableName, 'tenant_id')) {
                        $table->dropColumn('tenant_id');
                    }
                });
            }
        }
    }
};
