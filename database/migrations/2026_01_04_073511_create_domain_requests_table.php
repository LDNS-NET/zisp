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
        Schema::create('domain_requests', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('type'); // 'transfer' or 'custom'
            $table->string('requested_domain');
            $table->string('status')->default('pending'); // 'pending', 'accepted', 'rejected'
            $table->text('rejection_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_requests');
    }
};
