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
        Schema::create('country_sms_settings', function (Blueprint $table) {
            $table->id();
            $table->string('country_code');
            $table->string('gateway'); // e.g., 'africastalking', 'twilio', 'infobip'
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['country_code', 'gateway']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('country_sms_settings');
    }
};
