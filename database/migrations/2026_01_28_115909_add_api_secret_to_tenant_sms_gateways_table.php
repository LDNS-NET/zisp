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
            $table->string('api_secret')->nullable()->after('api_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_sms_gateways', function (Blueprint $table) {
            $table->dropColumn('api_secret');
        });
    }
};
