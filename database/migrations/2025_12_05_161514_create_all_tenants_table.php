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
        Schema::create('all_tenants', function (Blueprint $table) {
            $table->id();

            // Basic tenant info
            $table->string('name');
            $table->string('email')->nullable();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('username')->nullable();
            $table->string('phone')->nullable();

            // Status & Access
            $table->string('status')->default('active'); // active, inactive, suspended
            $table->string('domain')->nullable();
            $table->string('role')->nullable(); // admin, user, technician, etc.
            $table->string('logo')->nullable();

            // Counts
            $table->unsignedInteger('all_subscribers')->default(0);
            $table->unsignedInteger('mikrotik_count')->default(0);
            $table->unsignedInteger('users_count')->default(0);

            // Location & Contact
            $table->string('address')->nullable();
            $table->string('country')->nullable();
            $table->string('timezone')->nullable();
            $table->string('language')->nullable();

            // Suspension
            $table->boolean('suspended')->default(false);

            // Creator / Staff tracking
            $table->unsignedBigInteger('created_by')->nullable()->index();

            // Banking information
            $table->string('bank_name')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('paybill_number')->nullable();
            $table->string('till_number')->nullable();
            $table->string('mpesa_number')->nullable();

            // Analytics & Business data
            $table->bigInteger('lifetime_traffic')->default(0); // in MB/GB depending how you calculate
            $table->decimal('user_value', 12, 2)->default(0);
            $table->date('prunning_date')->nullable();
            $table->decimal('wallet_balance', 12, 2)->default(0);

            // Subscription & Lifecycle
            $table->date('expiry_date')->nullable();
            $table->date('joining_date')->nullable();

            // Verification & Security
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret')->nullable();
            $table->timestamp('account_locked_until')->nullable();

            // Legal / Registration
            $table->string('business_registration_number')->nullable();

            // JSON metadata
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('all_tenants');
    }
};
