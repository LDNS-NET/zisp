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
        Schema::create('all_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index(); //tenant id
            $table->string('paid_to'); //tenants name
            $table->decimal('amount', 15, 2);
            $table->string('payer_phone')->nullable(); //phone number used to make the payment
            $table->string('currency')->default('KES'); // as displayed currency in the tenants database
            $table->string('payer_name')->nullable();  // the wifi subscriber who made the payment
            $table->timestamp('paid_at')->nullable();
            $table->string('status')->default('pending'); //e.g., 'completed', 'pending', 'failed', 'cancelled'  
            $table->boolean('checked')->default(false); //boolean to indicate if payment is verified 
            $table->string('receipt_number')->nullable();
            $table->string('payment_method')->nullable(); //check the transfer method the wifi user used to make the payment
            $table->string('transaction_id')->nullable(); //from the payment gateway or mobile money platform       
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamp('disbursed_at')->nullable();
            $table->string('disbursement_label')->nullable(); // e.g., 'mpesa', 'bank transfer', 'paypal', etc.
            $table->enum('disbursement_status', ['pending', 'completed', 'failed'])->default('pending'); // e.g., 'pending', 'completed', 'failed'
            $table->json('metadata')->nullable(); // JSON field for any additional data
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('all_payments');
    }
};
