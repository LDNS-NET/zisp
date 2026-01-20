<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_general_settings', function (Blueprint $table) {
            $table->string('primary_color')->nullable()->default('#3b82f6'); // Default blue-500
            $table->string('secondary_color')->nullable()->default('#1e40af'); // Default blue-800
        });
    }

    public function down(): void
    {
        Schema::table('tenant_general_settings', function (Blueprint $table) {
            $table->dropColumn(['primary_color', 'secondary_color']);
        });
    }
};
