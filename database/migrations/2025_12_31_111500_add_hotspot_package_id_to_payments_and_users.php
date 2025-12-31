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
            // Drop existing FK on package_id if we want to allow it to be empty for hotspot
            // But actually we are adding a new column, so we just make package_id nullable if it wasn't
            // It already is nullable based on previous migrations.
            
            $table->foreignId('hotspot_package_id')
                ->after('package_id')
                ->nullable()
                ->constrained('tenant_hotspot_packages')
                ->onDelete('set null');
        });

        Schema::table('network_users', function (Blueprint $table) {
            // For network_users, we might want to keep package_id but drop the FK to packages
            // OR add hotspot_package_id there too. 
            // The current model logic uses package_id for both.
            // To be safe and clean, let's add hotspot_package_id and update the relationship.
            
            $table->foreignId('hotspot_package_id')
                ->after('package_id')
                ->nullable()
                ->constrained('tenant_hotspot_packages')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->dropForeign(['hotspot_package_id']);
            $table->dropColumn('hotspot_package_id');
        });

        Schema::table('network_users', function (Blueprint $table) {
            $table->dropForeign(['hotspot_package_id']);
            $table->dropColumn('hotspot_package_id');
        });
    }
};
