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
        Schema::table('tenant_sms_gateways', function (Blueprint $table) {
            // First, drop the unique constraint if it exists
            // We need to check if there's a foreign key using it first
            try {
                $table->dropUnique(['tenant_id', 'provider']);
            } catch (\Exception $e) {
                // If it fails, it might be used by a foreign key, skip for now
            }
            
            // Drop old generic columns
            if (Schema::hasColumn('tenant_sms_gateways', 'username')) {
                $table->dropColumn('username');
            }
            if (Schema::hasColumn('tenant_sms_gateways', 'api_key')) {
                $table->dropColumn('api_key');
            }
            if (Schema::hasColumn('tenant_sms_gateways', 'sender_id')) {
                $table->dropColumn('sender_id');
            }
            if (Schema::hasColumn('tenant_sms_gateways', 'api_secret')) {
                $table->dropColumn('api_secret');
            }
        });
        
        Schema::table('tenant_sms_gateways', function (Blueprint $table) {
            // Add provider-specific columns
            // Talksasa
            $table->text('talksasa_api_key')->nullable();
            $table->string('talksasa_sender_id')->nullable();
            
            // Celcom
            $table->text('celcom_partner_id')->nullable();
            $table->text('celcom_api_key')->nullable();
            $table->string('celcom_sender_id')->nullable();
            
            // Africa's Talking
            $table->string('africastalking_username')->nullable();
            $table->text('africastalking_api_key')->nullable();
            $table->string('africastalking_sender_id')->nullable();
            
            // Twilio
            $table->text('twilio_account_sid')->nullable();
            $table->text('twilio_auth_token')->nullable();
            $table->string('twilio_from_number')->nullable();
            
            // Add unique constraint on tenant_id only (one row per tenant)
            $table->unique('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_sms_gateways', function (Blueprint $table) {
            // Drop the tenant_id unique constraint
            $table->dropUnique(['tenant_id']);
            
            // Drop provider-specific columns
            $table->dropColumn([
                'talksasa_api_key',
                'talksasa_sender_id',
                'celcom_partner_id',
                'celcom_api_key',
                'celcom_sender_id',
                'africastalking_username',
                'africastalking_api_key',
                'africastalking_sender_id',
                'twilio_account_sid',
                'twilio_auth_token',
                'twilio_from_number',
            ]);
            
            // Restore old columns
            $table->string('username')->nullable();
            $table->string('api_key')->nullable();
            $table->string('sender_id')->nullable();
            $table->string('api_secret')->nullable();
            
            // Restore the old unique constraint
            $table->unique(['tenant_id', 'provider']);
        });
    }
};
