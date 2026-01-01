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
            $table->text('mpesa_consumer_key')->nullable();
            $table->text('mpesa_consumer_secret')->nullable();
            $table->string('mpesa_shortcode')->nullable();
            $table->text('mpesa_passkey')->nullable();
            $table->string('mpesa_env')->default('sandbox');
            $table->boolean('use_own_api')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_payment_gateways', function (Blueprint $table) {
            $table->dropColumn([
                'mpesa_consumer_key',
                'mpesa_consumer_secret',
                'mpesa_shortcode',
                'mpesa_passkey',
                'mpesa_env',
                'use_own_api'
            ]);
        });
    }
};
