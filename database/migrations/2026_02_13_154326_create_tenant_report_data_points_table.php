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
        Schema::create('tenant_report_data_points', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('category'); // e.g., "Marketing", "Operations"
            $table->decimal('value', 15, 2)->nullable(); // Generic numeric value
            $table->text('description')->nullable(); // "What is it for"
            $table->unsignedBigInteger('created_by'); // "Who created it"
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('category');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_report_data_points');
    }
};
