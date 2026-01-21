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
        Schema::table('tenant_general_settings', function (Blueprint $table) {
            $table->string('management_support_phone')->after('support_phone')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_general_settings', function (Blueprint $table) {
            $table->dropColumn('management_support_phone');
        });
    }
};
