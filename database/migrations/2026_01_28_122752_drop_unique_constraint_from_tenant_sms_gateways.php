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
            // Drop the unique constraint on tenant_id + provider
            // This was from the original table creation but conflicts with our new architecture
            // where we have one row per tenant with all provider fields (like payment gateways)
            $table->dropUnique(['tenant_id', 'provider']);
            
            // Add unique constraint on tenant_id only (one row per tenant)
            $table->unique('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_sms_gateways', function (Blueprint $table) {
            // Restore the old constraint
            $table->dropUnique(['tenant_id']);
            $table->unique(['tenant_id', 'provider']);
        });
    }
};
