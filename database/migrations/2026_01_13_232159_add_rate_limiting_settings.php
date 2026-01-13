<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Seed rate limiting settings
        DB::table('system_settings')->insert([
            // 1. Tenant Login
            [
                'key' => 'throttle_tenant_login_limit',
                'value' => '5',
                'group' => 'rate_limits',
                'type' => 'integer',
                'label' => 'Tenant Login Limit',
                'description' => 'Login attempts per minute per IP for Admin Dashboard.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // 2. Captive Portal Login
            [
                'key' => 'throttle_portal_login_limit',
                'value' => '20',
                'group' => 'rate_limits',
                'type' => 'integer',
                'label' => 'Portal Login Limit',
                'description' => 'Requests per minute per IP for Hotspot/PPPoE/Static portal auth.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // 3. Voucher Authentication
            [
                'key' => 'throttle_voucher_auth_limit',
                'value' => '10',
                'group' => 'rate_limits',
                'type' => 'integer',
                'label' => 'Voucher Auth Limit',
                'description' => 'Requests per minute per MAC address for voucher verification.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // 4. SMS Sending
            [
                'key' => 'throttle_sms_minute_limit',
                'value' => '60',
                'group' => 'rate_limits',
                'type' => 'integer',
                'label' => 'SMS Minute Limit',
                'description' => 'Maximum SMS per minute per tenant.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // 5. Payment Webhook
            [
                'key' => 'throttle_payment_webhook_limit',
                'value' => '30',
                'group' => 'rate_limits',
                'type' => 'integer',
                'label' => 'Payment Webhook Limit',
                'description' => 'Requests per minute per IP for M-Pesa/IntaSend callbacks.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // 6. STK Push Initiation
            [
                'key' => 'throttle_stk_push_limit',
                'value' => '3',
                'group' => 'rate_limits',
                'type' => 'integer',
                'label' => 'STK Push Limit',
                'description' => 'Initiations per minute per subscriber/phone.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // 7. Network Users CRUD
            [
                'key' => 'throttle_user_crud_limit',
                'value' => '60',
                'group' => 'rate_limits',
                'type' => 'integer',
                'label' => 'Network User CRUD Limit',
                'description' => 'Create/Update/Delete operations per minute per tenant.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // 8. Online Users Fetch
            [
                'key' => 'throttle_online_users_limit',
                'value' => '6',
                'group' => 'rate_limits',
                'type' => 'integer',
                'label' => 'Online Users Limit',
                'description' => 'Requests per minute per router/tenant (Default: 1 every 10s).',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // 9. Bulk Actions
            [
                'key' => 'throttle_bulk_actions_limit',
                'value' => '5',
                'group' => 'rate_limits',
                'type' => 'integer',
                'label' => 'Bulk Actions Limit',
                'description' => 'Bulk delete/SMS/Export/Import actions per minute per tenant.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // 10. Tenant Registration
            [
                'key' => 'throttle_tenant_registration_limit',
                'value' => '2',
                'group' => 'rate_limits',
                'type' => 'integer',
                'label' => 'Registration Limit',
                'description' => 'Signups per HOUR per IP address.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // 11. WireGuard Sync
            [
                'key' => 'throttle_wireguard_sync_limit',
                'value' => '2',
                'group' => 'rate_limits',
                'type' => 'integer',
                'label' => 'WireGuard Sync Limit',
                'description' => 'Sync triggers per minute per router/tenant (Default: 1 every 30s).',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // 12. File Uploads
            [
                'key' => 'throttle_file_upload_limit',
                'value' => '10',
                'group' => 'rate_limits',
                'type' => 'integer',
                'label' => 'File Upload Limit',
                'description' => 'Media/Document uploads per minute per tenant.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // 13. MikroTik API queries
            [
                'key' => 'throttle_mikrotik_api_limit',
                'value' => '4',
                'group' => 'rate_limits',
                'type' => 'integer',
                'label' => 'MikroTik API Limit',
                'description' => 'Router-based API queries per minute per router (Default: 1 every 15s).',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
 
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('system_settings')->where('group', 'rate_limits')->delete();
    }
};
