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
        Schema::table('tenant_mikrotiks', function (Blueprint $table) {
            // Add WireGuard fields if they don't exist
            if (!Schema::hasColumn('tenant_mikrotiks', 'wireguard_address')) {
                $table->string('wireguard_address')->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('tenant_mikrotiks', 'wireguard_public_key')) {
                $table->text('wireguard_public_key')->nullable()->after('wireguard_address');
            }
            if (!Schema::hasColumn('tenant_mikrotiks', 'wireguard_allowed_ips')) {
                $table->string('wireguard_allowed_ips')->nullable()->after('wireguard_public_key');
            }
            if (!Schema::hasColumn('tenant_mikrotiks', 'wireguard_port')) {
                $table->integer('wireguard_port')->nullable()->after('wireguard_allowed_ips');
            }
            if (!Schema::hasColumn('tenant_mikrotiks', 'wireguard_status')) {
                $table->string('wireguard_status')->nullable()->after('wireguard_port');
            }
            if (!Schema::hasColumn('tenant_mikrotiks', 'wireguard_last_handshake')) {
                $table->timestamp('wireguard_last_handshake')->nullable()->after('wireguard_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_mikrotiks', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('tenant_mikrotiks', 'wireguard_address')) {
                $columns[] = 'wireguard_address';
            }
            if (Schema::hasColumn('tenant_mikrotiks', 'wireguard_public_key')) {
                $columns[] = 'wireguard_public_key';
            }
            if (Schema::hasColumn('tenant_mikrotiks', 'wireguard_allowed_ips')) {
                $columns[] = 'wireguard_allowed_ips';
            }
            if (Schema::hasColumn('tenant_mikrotiks', 'wireguard_port')) {
                $columns[] = 'wireguard_port';
            }
            if (Schema::hasColumn('tenant_mikrotiks', 'wireguard_status')) {
                $columns[] = 'wireguard_status';
            }
            if (Schema::hasColumn('tenant_mikrotiks', 'wireguard_last_handshake')) {
                $columns[] = 'wireguard_last_handshake';
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
