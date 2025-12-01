<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenant_mikrotiks', function (Blueprint $table) {
            $table->string('api_username')->nullable()->after('router_password');
            $table->string('api_password')->nullable()->after('api_username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_mikrotiks', function (Blueprint $table) {
            $table->dropColumn(['api_username', 'api_password']);
        });
    }
};
