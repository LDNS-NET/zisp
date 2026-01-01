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
            $table->string('disbursement_status')->default('pending'); // pending, processing, completed, failed
            $table->string('disbursement_transaction_id')->nullable();
            $table->json('disbursement_response')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->dropColumn([
                'disbursement_status',
                'disbursement_transaction_id',
                'disbursement_response'
            ]);
        });
    }
};
