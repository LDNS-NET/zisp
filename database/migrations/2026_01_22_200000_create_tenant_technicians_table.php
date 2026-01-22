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
        Schema::create('tenant_technicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->string('employee_id')->unique();
            $table->enum('status', ['active', 'inactive', 'on_leave'])->default('active');
            $table->text('specialization')->nullable(); // e.g., Fiber, Wireless, Router Config
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamp('last_location_update')->nullable();
            $table->json('skills')->nullable(); // JSON array of skills
            $table->integer('completed_installations')->default(0);
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_technicians');
    }
};
