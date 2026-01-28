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
            // Drop old generic columns that will be replaced by provider-specific ones
            $table->dropColumn(['username', 'api_key', 'sender_id', 'api_secret']);
            
            // Talksasa columns
            $table->text('talksasa_api_key')->nullable();
            $table->string('talksasa_sender_id')->nullable();
            
            // Celcom columns
            $table->text('celcom_partner_id')->nullable();
            $table->text('celcom_api_key')->nullable();
            $table->string('celcom_sender_id')->nullable();
            
            // Africa's Talking columns
            $table->string('africastalking_username')->nullable();
            $table->text('africastalking_api_key')->nullable();
            $table->string('africastalking_sender_id')->nullable();
            
            // Twilio columns
            $table->text('twilio_account_sid')->nullable();
            $table->text('twilio_auth_token')->nullable();
            $table->string('twilio_from_number')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_sms_gateways', function (Blueprint $table) {
            // Restore old columns
            $table->string('username')->nullable();
            $table->string('api_key')->nullable();
            $table->string('sender_id')->nullable();
            $table->string('api_secret')->nullable();
            
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
        });
    }
};
