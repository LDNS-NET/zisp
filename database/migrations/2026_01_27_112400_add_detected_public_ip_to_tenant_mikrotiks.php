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
            $table->string('detected_public_ip')->nullable()->after('public_ip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_mikrotiks', function (Blueprint $table) {
            $table->dropColumn('detected_public_ip');
        });
    }
};
