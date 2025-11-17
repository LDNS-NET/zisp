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
            $table->timestamp('last_test_at')->nullable()->after('last_seen_at');
            $table->string('last_status', 20)->nullable()->after('last_test_at');
            $table->text('last_message')->nullable()->after('last_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_mikrotiks', function (Blueprint $table) {
            $table->dropColumn(['last_test_at', 'last_status', 'last_message']);
        });
    }
};
