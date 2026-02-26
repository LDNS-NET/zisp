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
        Schema::table('network_users', function (Blueprint $table) {
            // Drop old unique index if it exists
            $table->dropUnique('network_users_phone_unique');

            // Add composite unique index for phone and type
            $table->unique(['phone', 'type'], 'network_users_phone_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('network_users', function (Blueprint $table) {
            $table->dropUnique('network_users_phone_type_unique');
            $table->unique('phone', 'network_users_phone_unique');
        });
    }
};
