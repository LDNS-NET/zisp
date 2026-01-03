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
        Schema::create('pricing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('country_code')->unique();
            $table->string('currency')->default('KES');
            $table->decimal('pppoe_price_per_month', 10, 2)->default(0);
            $table->decimal('hotspot_price_percentage', 5, 2)->default(3.00); // e.g. 3.00%
            $table->decimal('minimum_pay', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_plans');
    }
};
