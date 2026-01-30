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
            // Add environment field for Africa's Talking (sandbox/production)
            $table->string('africastalking_environment')->default('production')->after('africastalking_sender_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_sms_gateways', function (Blueprint $table) {
            $table->dropColumn('africastalking_environment');
        });
    }
};
