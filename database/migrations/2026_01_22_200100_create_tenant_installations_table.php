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
        Schema::create('tenant_installations', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->foreignId('network_user_id')->nullable()->constrained('network_users')->onDelete('set null');
            $table->foreignId('technician_id')->nullable()->constrained('tenant_technicians')->onDelete('set null');
            $table->foreignId('equipment_id')->nullable()->constrained('tenant_equipments')->onDelete('set null');
            $table->string('installation_number')->unique();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->text('installation_address');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->enum('installation_type', ['new', 'relocation', 'upgrade', 'repair', 'maintenance'])->default('new');
            $table->enum('service_type', ['fiber', 'wireless', 'hybrid'])->default('wireless');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled', 'on_hold'])->default('scheduled');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->date('scheduled_date');
            $table->time('scheduled_time')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('estimated_duration')->nullable(); // in minutes
            $table->integer('actual_duration')->nullable(); // in minutes
            $table->text('installation_notes')->nullable();
            $table->text('technician_notes')->nullable();
            $table->text('customer_feedback')->nullable();
            $table->integer('customer_rating')->nullable(); // 1-5
            $table->json('checklist_data')->nullable(); // Store checklist completion data
            $table->json('equipment_installed')->nullable(); // Array of equipment details
            $table->decimal('installation_cost', 10, 2)->nullable();
            $table->boolean('payment_collected')->default(false);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status'], 'ti_tenant_status_idx');
            $table->index(['tenant_id', 'scheduled_date'], 'ti_tenant_date_idx');
            $table->index(['technician_id', 'status'], 'ti_tech_status_idx');
            $table->index(['installation_number'], 'ti_number_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_installations');
    }
};
