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
        Schema::table('tenant_hotpots', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('tenant_id');
            $table->index('package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_hotpots', function (Blueprint $table) {
            $table->dropIndex(['package_id']);
            $table->dropColumn('package_id');
        });
    }
};
