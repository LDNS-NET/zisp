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
        Schema::create('tenant_installation_checklists', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('installation_type', ['new', 'relocation', 'upgrade', 'repair', 'maintenance'])->default('new');
            $table->enum('service_type', ['fiber', 'wireless', 'hybrid', 'all'])->default('all');
            $table->json('checklist_items'); // Array of checklist items with structure
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active'], 'tic_tenant_active_idx');
            $table->index(['tenant_id', 'installation_type', 'service_type'], 'tic_tenant_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_installation_checklists');
    }
};
