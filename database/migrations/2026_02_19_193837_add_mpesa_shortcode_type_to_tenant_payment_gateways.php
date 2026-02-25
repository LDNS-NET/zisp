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
            $table->string('mpesa_shortcode_type')->default('paybill')->after('mpesa_shortcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_payment_gateways', function (Blueprint $table) {
            $table->dropColumn('mpesa_shortcode_type');
        });
    }
};
