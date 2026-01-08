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
        Schema::table('tenant_active_users', function (Blueprint $table) {
            // Add missing fields from tenant_active_sessions
            if (!Schema::hasColumn('tenant_active_users', 'router_id')) {
                $table->unsignedBigInteger('router_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('tenant_active_users', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('router_id');
            }
            if (!Schema::hasColumn('tenant_active_users', 'session_id')) {
                $table->string('session_id')->nullable()->after('username');
            }
            if (!Schema::hasColumn('tenant_active_users', 'ip_address')) {
                $table->string('ip_address')->nullable()->after('session_id');
            }
            if (!Schema::hasColumn('tenant_active_users', 'mac_address')) {
                $table->string('mac_address')->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('tenant_active_users', 'bytes_in')) {
                $table->bigInteger('bytes_in')->default(0)->after('mac_address');
            }
            if (!Schema::hasColumn('tenant_active_users', 'bytes_out')) {
                $table->bigInteger('bytes_out')->default(0)->after('bytes_in');
            }
            if (!Schema::hasColumn('tenant_active_users', 'status')) {
                $table->string('status')->default('active')->after('bytes_out');
            }
            if (!Schema::hasColumn('tenant_active_users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('tenant_active_users', 'connected_at')) {
                $table->timestamp('connected_at')->nullable()->after('last_seen_at');
            }
            if (!Schema::hasColumn('tenant_active_users', 'disconnected_at')) {
                $table->timestamp('disconnected_at')->nullable()->after('connected_at');
            }

            // Handle existing columns
            if (Schema::hasColumn('tenant_active_users', 'ip/mac_address')) {
                $table->dropColumn('ip/mac_address');
            }
            if (Schema::hasColumn('tenant_active_users', 'session_start')) {
                $table->dropColumn('session_start');
            }
            if (Schema::hasColumn('tenant_active_users', 'session_end')) {
                $table->dropColumn('session_end');
            }
            if (Schema::hasColumn('tenant_active_users', 'mikrotik_id')) {
                $table->dropColumn('mikrotik_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_active_users', function (Blueprint $table) {
            $table->dropColumn([
                'router_id',
                'user_id',
                'session_id',
                'ip_address',
                'mac_address',
                'bytes_in',
                'bytes_out',
                'status',
                'last_seen_at',
                'connected_at',
                'disconnected_at'
            ]);
            
            $table->string('ip/mac_address')->nullable();
            $table->timestamp('session_start')->nullable();
            $table->timestamp('session_end')->nullable();
            $table->string('mikrotik_id')->nullable();
        });
    }
};
