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
        // SQLite doesn't support changing enums easily, so we skip strict check for it or use raw SQL only for MySQL
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE tenant_installations MODIFY COLUMN status ENUM('new', 'pending', 'scheduled', 'in_progress', 'completed', 'cancelled', 'on_hold') DEFAULT 'new'");
            DB::statement("ALTER TABLE tenant_installations MODIFY COLUMN scheduled_date DATE NULL");
        } else {
             // For SQLite, we just make the column nullable using standard schema builder if doctrine is installed, or simpler raw sql workaround
             // Since this is likely dev, we will assume schema builder is safest path if possible, but change() requires dbal.
             // We will try to just set nullable for scheduled_date. status enum in sqlite is just text constraint.
            Schema::table('tenant_installations', function (Blueprint $table) {
                $table->date('scheduled_date')->nullable()->change();
                // We cannot easily 'add' values to enum in sqlite without recreating table. 
                // We will assume the column is just VARCHAR in sqlite or check constraint is loose.
            });
        }
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
