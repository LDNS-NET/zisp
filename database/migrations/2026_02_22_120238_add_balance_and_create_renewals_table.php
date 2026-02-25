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
        Schema::table('network_users', function (Blueprint $table) {
            $table->decimal('wallet_balance', 10, 2)->default(0.00)->after('expires_at');
        });

        Schema::create('package_renewals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('package_id');
            $table->decimal('amount_paid', 10, 2);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('active'); // active, expired
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_renewals');
        
        Schema::table('network_users', function (Blueprint $table) {
            $table->dropColumn('wallet_balance');
        });
    }
};
