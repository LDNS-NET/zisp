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
            // Drop the foreign key constraint but keep the column
            // The constraint name is usually 'tenant_payments_user_id_foreign'
            $table->dropForeign(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('network_users')
                ->onDelete('cascade');
        });
    }
};
