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
            // Add public IP column if not already present
            if (!Schema::hasColumn('tenant_mikrotiks', 'public_ip')) {
                $table->string('public_ip')->nullable()->after('wireguard_port');
            }
            // Add unique winbox port column
            $table->unsignedInteger('winbox_port')->unique()->nullable()->after('public_ip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_mikrotiks', function (Blueprint $table) {
            $table->dropColumn(['winbox_port', 'public_ip']);
        });
    }
};
