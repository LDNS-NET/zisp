<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('network_users', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['package_id']);

            // Modify the column to be nullable
            $table->foreignId('package_id')->nullable()->change();

            // Re-add the foreign key constraint
            $table->foreign('package_id')
                ->references('id')
                ->on('packages')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('network_users', function (Blueprint $table) {
            // Drop the foreign key
            $table->dropForeign(['package_id']);

            // Make the column NOT NULL again
            $table->foreignId('package_id')->nullable(false)->change();

            // Re-add the foreign key constraint
            $table->foreign('package_id')
                ->references('id')
                ->on('packages')
                ->onDelete('cascade');
        });
    }
};
