<?php
// database/migrations/2026_02_23_013226_add_tracking_fields_to_tenant_equipments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tenant_equipments', function (Blueprint $table) {
            $table->boolean('track_serials')->default(false)->after('notes');
            $table->boolean('track_length')->default(false)->after('track_serials');
        });
    }

    public function down()
    {
        Schema::table('tenant_equipments', function (Blueprint $table) {
            $table->dropColumn(['track_serials', 'track_length']);
        });
    }
};