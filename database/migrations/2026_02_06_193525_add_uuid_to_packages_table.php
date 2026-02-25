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
        Schema::table('packages', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        // Generate UUIDs for existing records
        DB::table('packages')->whereNull('uuid')->chunkById(100, function ($packages) {
            foreach ($packages as $package) {
                DB::table('packages')
                    ->where('id', $package->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            }
        });

        // Make UUID unique and non-nullable after population
        Schema::table('packages', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->nullable(false)->change();
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndex(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
