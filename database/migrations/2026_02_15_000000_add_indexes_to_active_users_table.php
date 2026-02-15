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
        // 1. tenant_active_users
        Schema::table('tenant_active_users', function (Blueprint $table) {
            if (!indexExists('tenant_active_users', 'tenant_active_users_session_id_index')) {
                $table->index('session_id');
            }
            if (!indexExists('tenant_active_users', 'tau_tenant_router_status_idx')) {
                $table->index(['tenant_id', 'router_id', 'status'], 'tau_tenant_router_status_idx');
            }
            if (!indexExists('tenant_active_users', 'tenant_active_users_user_id_index')) {
                $table->index('user_id');
            }
            if (!indexExists('tenant_active_users', 'tenant_active_users_last_seen_at_index')) {
                $table->index('last_seen_at');
            }
        });

        // 2. network_users
        Schema::table('network_users', function (Blueprint $table) {
            if (!indexExists('network_users', 'network_users_expires_at_index')) {
                $table->index('expires_at');
            }
            if (!indexExists('network_users', 'network_users_status_index')) {
                $table->index('status');
            }
            if (!indexExists('network_users', 'network_users_online_index')) {
                $table->index('online');
            }
        });

        // 3. tenant_mikrotiks
        Schema::table('tenant_mikrotiks', function (Blueprint $table) {
            if (!indexExists('tenant_mikrotiks', 'tenant_mikrotiks_wireguard_address_index')) {
                $table->index('wireguard_address');
            }
            if (!indexExists('tenant_mikrotiks', 'tenant_mikrotiks_online_index')) {
                $table->index('online');
            }
        });

        // 4. tenant_sms
        Schema::table('tenant_sms', function (Blueprint $table) {
            if (!indexExists('tenant_sms', 'tenant_sms_status_index')) {
                $table->index('status');
            }
        });

        // 5. tenant_payments
        Schema::table('tenant_payments', function (Blueprint $table) {
            if (!indexExists('tenant_payments', 'tenant_payments_phone_index')) {
                $table->index('phone');
            }
            if (!indexExists('tenant_payments', 'tenant_payments_checked_index')) {
                $table->index('checked');
            }
        });

        // 6. tenant_bandwidth_usages
        Schema::table('tenant_bandwidth_usages', function (Blueprint $table) {
            if (!indexExists('tenant_bandwidth_usages', 'tenant_bandwidth_usages_router_id_index')) {
                $table->index('router_id');
            }
            if (!indexExists('tenant_bandwidth_usages', 'tenant_bandwidth_usages_timestamp_index')) {
                $table->index('timestamp');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_active_users', function (Blueprint $table) {
            $table->dropIndex('tenant_active_users_session_id_index');
            $table->dropIndex('tau_tenant_router_status_idx');
            $table->dropIndex('tenant_active_users_user_id_index');
            $table->dropIndex('tenant_active_users_last_seen_at_index');
        });

        Schema::table('network_users', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropIndex(['status']);
            $table->dropIndex(['online']);
        });

        Schema::table('tenant_mikrotiks', function (Blueprint $table) {
            $table->dropIndex(['wireguard_address']);
            $table->dropIndex(['online']);
        });

        Schema::table('tenant_sms', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropIndex(['checked']);
        });

        Schema::table('tenant_bandwidth_usages', function (Blueprint $table) {
            $table->dropIndex(['router_id']);
            $table->dropIndex(['timestamp']);
        });
    }
};

/**
 * Helper to check if index exists (since Blueprint doesn't have a reliable hasIndex method across all drivers)
 */
function indexExists($table, $index)
{
    $conn = Schema::getConnection();
    $dbSchemaManager = $conn->getDoctrineSchemaManager();
    $indexes = $dbSchemaManager->listTableIndexes($table);
    return array_key_exists($index, $indexes);
}
