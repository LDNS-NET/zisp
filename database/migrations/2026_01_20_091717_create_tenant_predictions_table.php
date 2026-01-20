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
        Schema::create('tenant_predictions', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('prediction_type', 50); // 'churn', 'revenue', 'capacity'
            $table->unsignedBigInteger('entity_id')->nullable(); // user_id or null for tenant-wide
            $table->decimal('prediction_value', 10, 2);
            $table->decimal('confidence', 5, 2); // 0-100
            $table->json('factors')->nullable(); // Contributing factors
            $table->timestamp('predicted_at');
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'prediction_type']);
            $table->index(['entity_id', 'prediction_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_predictions');
    }
};
