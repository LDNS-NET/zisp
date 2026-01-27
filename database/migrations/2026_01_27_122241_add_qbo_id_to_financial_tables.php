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
        Schema::table('tenant_invoices', function (Blueprint $table) {
            $table->string('qbo_id')->nullable()->after('tenant_id');
        });

        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->string('qbo_id')->nullable()->after('tenant_id');
        });

        Schema::table('tenant_expenses', function (Blueprint $table) {
            $table->string('qbo_id')->nullable()->after('category');
        });

        Schema::table('tenant_equipments', function (Blueprint $table) {
            $table->string('qbo_id')->nullable()->after('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_invoices', function (Blueprint $table) {
            $table->dropColumn('qbo_id');
        });

        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->dropColumn('qbo_id');
        });

        Schema::table('tenant_expenses', function (Blueprint $table) {
            $table->dropColumn('qbo_id');
        });

        Schema::table('tenant_equipments', function (Blueprint $table) {
            $table->dropColumn('qbo_id');
        });
    }
};
