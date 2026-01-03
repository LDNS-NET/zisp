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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general'); // e.g., general, mail, sms
            $table->string('type')->default('string'); // string, boolean, integer, json
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Seed default settings
        DB::table('system_settings')->insert([
            [
                'key' => 'app_name',
                'value' => 'ZISP',
                'group' => 'general',
                'type' => 'string',
                'label' => 'Application Name',
                'description' => 'The name of the application displayed to users.',
            ],
            [
                'key' => 'support_email',
                'value' => 'support@zisp.net',
                'group' => 'general',
                'type' => 'string',
                'label' => 'Support Email',
                'description' => 'Email address for support inquiries.',
            ],
            [
                'key' => 'support_phone',
                'value' => '+254700000000',
                'group' => 'general',
                'type' => 'string',
                'label' => 'Support Phone',
                'description' => 'Phone number for support inquiries.',
            ],
            [
                'key' => 'maintenance_mode',
                'value' => '0',
                'group' => 'general',
                'type' => 'boolean',
                'label' => 'Maintenance Mode',
                'description' => 'Put the application in maintenance mode.',
            ],
            [
                'key' => 'default_trial_days',
                'value' => '14',
                'group' => 'billing',
                'type' => 'integer',
                'label' => 'Default Trial Days',
                'description' => 'Number of days for the free trial.',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
