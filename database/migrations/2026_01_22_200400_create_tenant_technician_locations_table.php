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
        Schema::create('tenant_technician_locations', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->foreignId('technician_id')->constrained('tenant_technicians')->onDelete('cascade');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('accuracy', 8, 2)->nullable(); // GPS accuracy in meters
            $table->decimal('speed', 8, 2)->nullable(); // Speed in km/h
            $table->string('activity_type')->nullable(); // still, walking, driving, etc.
            $table->foreignId('installation_id')->nullable()->constrained('tenant_installations')->onDelete('set null');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['technician_id', 'recorded_at'], 'ttl_tech_time_idx');
            $table->index(['tenant_id', 'recorded_at'], 'ttl_tenant_time_idx');
            $table->index(['installation_id'], 'ttl_inst_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_technician_locations');
    }
};
