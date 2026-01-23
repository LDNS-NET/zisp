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
        Schema::table('tenant_installations', function (Blueprint $table) {
            // Check if columns don't exist before adding them
            if (!Schema::hasColumn('tenant_installations', 'picked_by')) {
                $table->foreignId('picked_by')->nullable()->after('technician_id')->constrained('users')->onDelete('set null');
            }
            
            if (!Schema::hasColumn('tenant_installations', 'picked_at')) {
                $table->timestamp('picked_at')->nullable()->after('picked_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_installations', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_installations', 'picked_by')) {
                $table->dropForeign(['picked_by']);
                $table->dropColumn('picked_by');
            }
            
            if (Schema::hasColumn('tenant_installations', 'picked_at')) {
                $table->dropColumn('picked_at');
            }
        });
    }
};
