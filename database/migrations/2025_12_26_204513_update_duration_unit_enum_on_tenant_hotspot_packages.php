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
        DB::statement("
            ALTER TABLE tenant_hotspot_packages
            MODIFY duration_unit ENUM(
                'minutes',
                'hours',
                'days',
                'weeks',
                'months'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE tenant_hotspot_packages
            MODIFY duration_unit ENUM(
                'minutes',
                'hours',
                'days'
            ) NOT NULL
        ");
    }

};
