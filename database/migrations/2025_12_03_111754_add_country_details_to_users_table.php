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
        Schema::table('users', function (Blueprint $table) {
            $table->string('country')->nullable()->after('email');
            $table->string('country_code')->nullable()->after('country');
            $table->string('currency')->nullable()->after('country_code');
            $table->string('currency_name')->nullable()->after('currency');
            $table->string('dial_code')->nullable()->after('currency_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['country', 'country_code', 'currency', 'currency_name', 'dial_code']);
        });
    }
};
