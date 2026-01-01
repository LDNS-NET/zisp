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
        Schema::table('tenant_payment_gateways', function (Blueprint $table) {
            $table->text('paystack_public_key')->nullable();
            $table->text('paystack_secret_key')->nullable();
            $table->text('flutterwave_public_key')->nullable();
            $table->text('flutterwave_secret_key')->nullable();
            $table->text('momo_api_user')->nullable();
            $table->text('momo_api_key')->nullable();
            $table->text('momo_subscription_key')->nullable();
            $table->string('momo_env')->nullable();
            $table->text('airtel_client_id')->nullable();
            $table->text('airtel_client_secret')->nullable();
            $table->string('airtel_env')->nullable();
            $table->text('equitel_client_id')->nullable();
            $table->text('equitel_client_secret')->nullable();
            $table->text('tigo_pesa_client_id')->nullable();
            $table->text('tigo_pesa_client_secret')->nullable();
            $table->text('halopesa_client_id')->nullable();
            $table->text('halopesa_client_secret')->nullable();
            $table->text('hormuud_api_key')->nullable();
            $table->text('hormuud_merchant_id')->nullable();
            $table->text('zaad_api_key')->nullable();
            $table->text('zaad_merchant_id')->nullable();
            $table->text('vodafone_cash_client_id')->nullable();
            $table->text('vodafone_cash_client_secret')->nullable();
            $table->text('orange_money_client_id')->nullable();
            $table->text('orange_money_client_secret')->nullable();
            $table->text('telebirr_app_id')->nullable();
            $table->text('telebirr_app_key')->nullable();
            $table->text('telebirr_public_key')->nullable();
            $table->text('cbe_birr_client_id')->nullable();
            $table->text('cbe_birr_client_secret')->nullable();
            $table->text('fawry_merchant_code')->nullable();
            $table->text('fawry_security_key')->nullable();
            $table->text('ecocash_client_id')->nullable();
            $table->text('ecocash_client_secret')->nullable();
            $table->text('wave_api_key')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_payment_gateways', function (Blueprint $table) {
            $table->dropColumn([
                'paystack_public_key',
                'paystack_secret_key',
                'flutterwave_public_key',
                'flutterwave_secret_key',
                'momo_api_user',
                'momo_api_key',
                'momo_subscription_key',
                'momo_env',
                'airtel_client_id',
                'airtel_client_secret',
                'airtel_env',
                'equitel_client_id',
                'equitel_client_secret',
                'tigo_pesa_client_id',
                'tigo_pesa_client_secret',
                'halopesa_client_id',
                'halopesa_client_secret',
                'hormuud_api_key',
                'hormuud_merchant_id',
                'zaad_api_key',
                'zaad_merchant_id',
                'vodafone_cash_client_id',
                'vodafone_cash_client_secret',
                'orange_money_client_id',
                'orange_money_client_secret',
                'telebirr_app_id',
                'telebirr_app_key',
                'telebirr_public_key',
                'cbe_birr_client_id',
                'cbe_birr_client_secret',
                'fawry_merchant_code',
                'fawry_security_key',
                'ecocash_client_id',
                'ecocash_client_secret',
                'wave_api_key',
            ]);
        });
    }
};
