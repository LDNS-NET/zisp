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
            $table->string('router_username')->nullable()->change();
            $table->string('router_password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_mikrotiks', function (Blueprint $table) {
            $table->string('router_username')->nullable(false)->change();
            $table->string('router_password')->nullable(false)->change();
        });
    }
};
