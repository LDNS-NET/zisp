<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tenant_general_settings', function (Blueprint $table) {
            $table->string('business_name')->nullable()->after('logo');
            $table->text('business_description')->nullable()->after('business_name');
            $table->string('registration_number')->nullable()->after('business_description');
            $table->string('tax_number')->nullable()->after('registration_number');
            $table->string('primary_email')->nullable()->after('tax_number');
            $table->string('primary_phone')->nullable()->after('primary_email');
            $table->string('whatsapp_number')->nullable()->after('support_phone');
            $table->string('address_street')->nullable()->after('whatsapp_number');
            $table->string('address_city')->nullable()->after('address_street');
            $table->string('address_state')->nullable()->after('address_city');
            $table->string('address_postal_code')->nullable()->after('address_state');
            $table->string('address_country')->nullable()->after('address_postal_code');
            $table->string('website_url')->nullable()->after('address_country');
            $table->string('facebook_url')->nullable()->after('website_url');
            $table->string('twitter_url')->nullable()->after('facebook_url');
            $table->string('instagram_url')->nullable()->after('twitter_url');
        });
    }

    public function down(): void {
        Schema::table('tenant_general_settings', function (Blueprint $table) {
            $table->dropColumn([
                'business_name',
                'business_description',
                'registration_number',
                'tax_number',
                'primary_email',
                'primary_phone',
                'whatsapp_number',
                'address_street',
                'address_city',
                'address_state',
                'address_postal_code',
                'address_country',
                'website_url',
                'facebook_url',
                'twitter_url',
                'instagram_url',
            ]);
        });
    }
};
