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
            $table->index('role');
            $table->index('is_suspended');
            $table->index(['tenant_id', 'created_at']);
            $table->index('country_code');
        });
        
        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->index('status');
            $table->index('payment_method');
            $table->index(['created_by', 'paid_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['is_suspended']);
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropIndex(['country_code']);
        });
        
        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['payment_method']);
            $table->dropIndex(['created_by', 'paid_at']);
            $table->dropIndex(['created_at']);
        });
    }
};
