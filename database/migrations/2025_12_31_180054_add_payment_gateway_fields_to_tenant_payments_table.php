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
        Schema::table('tenant_payments', function (Blueprint $table) {
            // Common
            $table->string('currency', 3)->default('KES')->after('amount');
            $table->string('payment_method')->nullable()->after('currency'); // e.g., mpesa, paystack, paypal

            // M-Pesa
            $table->string('merchant_request_id')->nullable()->after('intasend_checkout_id');
            $table->string('checkout_request_id')->nullable()->after('merchant_request_id');
            $table->string('mpesa_receipt_number')->nullable()->after('checkout_request_id');
            $table->string('result_code')->nullable()->after('mpesa_receipt_number');
            $table->string('result_desc')->nullable()->after('result_code');

            // Paystack
            $table->string('paystack_reference')->nullable()->after('result_desc');
            $table->string('paystack_auth_code')->nullable()->after('paystack_reference');

            // Flutterwave
            $table->string('flw_transaction_id')->nullable()->after('paystack_auth_code');
            $table->string('flw_ref')->nullable()->after('flw_transaction_id');

            // PayPal
            $table->string('paypal_order_id')->nullable()->after('flw_ref');
            $table->string('paypal_payer_id')->nullable()->after('paypal_order_id');

            // Pesapal
            $table->string('pesapal_order_tracking_id')->nullable()->after('paypal_payer_id');
            $table->string('pesapal_merchant_reference')->nullable()->after('pesapal_order_tracking_id');

            // MTN MoMo (Ghana, Uganda, Zambia, Rwanda, etc.)
            $table->string('momo_reference')->nullable()->after('pesapal_merchant_reference');
            $table->string('momo_transaction_id')->nullable()->after('momo_reference');

            // Airtel Money (Africa, Asia, Tanzania, DRC)
            $table->string('airtel_money_reference')->nullable()->after('momo_transaction_id');
            $table->string('airtel_money_transaction_id')->nullable()->after('airtel_money_reference');

            // Tigo Pesa (Tanzania)
            $table->string('tigo_pesa_reference')->nullable()->after('airtel_money_transaction_id');
            $table->string('tigo_pesa_transaction_id')->nullable()->after('tigo_pesa_reference');

            // Orange Money (DRC, Rwanda, Cameroon)
            $table->string('orange_money_reference')->nullable()->after('tigo_pesa_transaction_id');
            $table->string('orange_money_transaction_id')->nullable()->after('orange_money_reference');

            // Telebirr (Ethiopia)
            $table->string('telebirr_reference')->nullable()->after('orange_money_transaction_id');
            $table->string('telebirr_transaction_id')->nullable()->after('telebirr_reference');

            // Hormuud EVC Plus (Somalia)
            $table->string('evc_plus_reference')->nullable()->after('telebirr_transaction_id');
            $table->string('evc_plus_transaction_id')->nullable()->after('evc_plus_reference');

            // Wave (Senegal, Cote d'Ivoire)
            $table->string('wave_reference')->nullable()->after('evc_plus_transaction_id');
            $table->string('wave_transaction_id')->nullable()->after('wave_reference');

            // Ecocash (Zimbabwe)
            $table->string('ecocash_reference')->nullable()->after('wave_transaction_id');
            $table->string('ecocash_transaction_id')->nullable()->after('ecocash_reference');

            // CBE Birr (Ethiopia)
            $table->string('cbe_birr_reference')->nullable()->after('ecocash_transaction_id');
            $table->string('cbe_birr_transaction_id')->nullable()->after('cbe_birr_reference');

            // Zaad (Somaliland)
            $table->string('zaad_reference')->nullable()->after('cbe_birr_transaction_id');
            $table->string('zaad_transaction_id')->nullable()->after('zaad_reference');

            // Equitel (Kenya)
            $table->string('equitel_reference')->nullable()->after('zaad_transaction_id');
            $table->string('equitel_transaction_id')->nullable()->after('equitel_reference');

            // Halopesa (Tanzania)
            $table->string('halopesa_reference')->nullable()->after('equitel_transaction_id');
            $table->string('halopesa_transaction_id')->nullable()->after('halopesa_reference');

            // Vodafone Cash (Egypt, Ghana, etc.)
            $table->string('vodafone_cash_reference')->nullable()->after('halopesa_transaction_id');
            $table->string('vodafone_cash_transaction_id')->nullable()->after('vodafone_cash_reference');

            // Fawry (Egypt)
            $table->string('fawry_reference')->nullable()->after('vodafone_cash_transaction_id');
            $table->string('fawry_transaction_id')->nullable()->after('fawry_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->dropColumn([
                'currency',
                'payment_method',
                'merchant_request_id',
                'checkout_request_id',
                'mpesa_receipt_number',
                'result_code',
                'result_desc',
                'paystack_reference',
                'paystack_auth_code',
                'flw_transaction_id',
                'flw_ref',
                'paypal_order_id',
                'paypal_payer_id',
                'pesapal_order_tracking_id',
                'pesapal_merchant_reference',
                'momo_reference',
                'momo_transaction_id',
                'airtel_money_reference',
                'airtel_money_transaction_id',
                'tigo_pesa_reference',
                'tigo_pesa_transaction_id',
                'orange_money_reference',
                'orange_money_transaction_id',
                'telebirr_reference',
                'telebirr_transaction_id',
                'evc_plus_reference',
                'evc_plus_transaction_id',
                'wave_reference',
                'wave_transaction_id',
                'ecocash_reference',
                'ecocash_transaction_id',
                'cbe_birr_reference',
                'cbe_birr_transaction_id',
                'zaad_reference',
                'zaad_transaction_id',
                'equitel_reference',
                'equitel_transaction_id',
                'halopesa_reference',
                'halopesa_transaction_id',
                'vodafone_cash_reference',
                'vodafone_cash_transaction_id',
                'fawry_reference',
                'fawry_transaction_id'
            ]);
        });
    }
};
