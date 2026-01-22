<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Alter the status enum to include 'new' and 'pending'
        DB::statement("ALTER TABLE tenant_installations MODIFY COLUMN status ENUM('new', 'pending', 'scheduled', 'in_progress', 'completed', 'cancelled', 'on_hold') DEFAULT 'new'");
        
        // Make scheduled_date nullable if not already
        DB::statement("ALTER TABLE tenant_installations MODIFY COLUMN scheduled_date DATE NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to old enum values
        DB::statement("ALTER TABLE tenant_installations MODIFY COLUMN status ENUM('scheduled', 'in_progress', 'completed', 'cancelled', 'on_hold') DEFAULT 'scheduled'");
        
        // Make scheduled_date required again
        DB::statement("ALTER TABLE tenant_installations MODIFY COLUMN scheduled_date DATE NOT NULL");
    }
};
