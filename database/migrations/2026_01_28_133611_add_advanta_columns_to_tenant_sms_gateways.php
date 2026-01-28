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
            // Advanta SMS columns
            $table->text('advanta_partner_id')->nullable();
            $table->text('advanta_api_key')->nullable();
            $table->string('advanta_shortcode')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_sms_gateways', function (Blueprint $table) {
            $table->dropColumn([
                'advanta_partner_id',
                'advanta_api_key',
                'advanta_shortcode',
            ]);
        });
    }
};
