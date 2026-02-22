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
        Schema::table('tenant_equipments', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('name');
            $table->string('mac_address')->nullable()->unique()->after('serial_number');
            $table->enum('status', ['in_stock', 'assigned', 'faulty', 'retired', 'lost'])->default('in_stock')->after('mac_address');
            $table->enum('condition', ['new', 'used', 'refurbished'])->default('new')->after('status');
            $table->unsignedBigInteger('assigned_user_id')->nullable()->after('assigned_to');
            $table->date('purchase_date')->nullable()->after('total_price');
            $table->date('warranty_expiry')->nullable()->after('purchase_date');
            $table->text('notes')->nullable()->after('warranty_expiry');
            $table->string('qbo_id')->nullable()->after('notes');

            // Foreign key for assigned_user_id
            $table->foreign('assigned_user_id')->references('id')->on('network_users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_equipments', function (Blueprint $table) {
            $table->dropForeign(['assigned_user_id']);
            $table->dropColumn([
                'brand',
                'mac_address',
                'status',
                'condition',
                'assigned_user_id',
                'purchase_date',
                'warranty_expiry',
                'notes',
                'qbo_id'
            ]);
        });
    }
};
