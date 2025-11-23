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
        Schema::table('tenant_mikrotiks', function (Blueprint $table) {
            // Remove phone-home related fields
            if (Schema::hasColumn('tenant_mikrotiks', 'public_ip_address')) {
                $table->dropColumn('public_ip_address');
            }
            
            // Add API polling fields
            $table->boolean('online')->default(false)->after('status');
            $table->decimal('cpu', 5, 2)->nullable()->after('cpu_usage');
            $table->decimal('memory', 5, 2)->nullable()->after('memory_usage');
            
            // Note: uptime already exists, we'll use it
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_mikrotiks', function (Blueprint $table) {
            // Restore public_ip_address if needed
            $table->string('public_ip_address')->nullable()->after('ip_address');
            
            // Remove API polling fields
            $table->dropColumn(['online', 'cpu', 'memory']);
        });
    }
};
