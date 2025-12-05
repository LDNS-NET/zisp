<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * This migration removes the unique constraint on email to allow multiple null/empty values.
     * Email uniqueness is now handled at the application level with proper null checks.
     */
    public function up(): void
    {
        Schema::table('network_users', function (Blueprint $table) {
            // Drop the unique index on email
            $table->dropUnique(['email']);
        });

        // Convert empty strings to NULL for consistency
        DB::table('network_users')
            ->where('email', '')
            ->update(['email' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('network_users', function (Blueprint $table) {
            // Re-add the unique constraint
            $table->unique('email');
        });
    }
};
