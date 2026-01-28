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
            // BulkSMS columns
            $table->text('bulksms_username')->nullable();
            $table->text('bulksms_password')->nullable();
            
            // ClickSend columns
            $table->text('clicksend_username')->nullable();
            $table->text('clicksend_api_key')->nullable();
            
            // Infobip columns
            $table->text('infobip_api_key')->nullable();
            $table->string('infobip_base_url')->nullable();
            $table->string('infobip_sender_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_sms_gateways', function (Blueprint $table) {
            $table->dropColumn([
                'bulksms_username',
                'bulksms_password',
                'clicksend_username',
                'clicksend_api_key',
                'infobip_api_key',
                'infobip_base_url',
                'infobip_sender_id',
            ]);
        });
    }
};
