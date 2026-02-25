<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('network_users', function (Blueprint $table) {
            // Add UUID column after id
            $table->uuid('uuid')->nullable()->after('id');
        });

        // Generate UUIDs for existing records
        DB::table('network_users')->whereNull('uuid')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                DB::table('network_users')
                    ->where('id', $user->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            }
        });

        // Make UUID unique and non-nullable after population
        Schema::table('network_users', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->nullable(false)->change();
            // Add index for performance
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('network_users', function (Blueprint $table) {
            $table->dropIndex(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
