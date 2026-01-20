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
        Schema::create('tenant_report_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id');
            $table->timestamp('generated_at');
            $table->string('file_path')->nullable();
            $table->string('status', 50); // 'pending', 'completed', 'failed'
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('report_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_report_runs');
    }
};
