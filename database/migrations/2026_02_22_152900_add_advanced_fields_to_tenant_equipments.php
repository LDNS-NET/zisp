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
            if (!Schema::hasColumn('tenant_equipments', 'brand')) {
                $table->string('brand')->nullable()->after('name');
            }
            if (!Schema::hasColumn('tenant_equipments', 'mac_address')) {
                $table->string('mac_address')->nullable()->unique()->after('serial_number');
            }
            if (!Schema::hasColumn('tenant_equipments', 'status')) {
                $table->enum('status', ['in_stock', 'assigned', 'faulty', 'retired', 'lost'])->default('in_stock')->after('mac_address');
            }
            if (!Schema::hasColumn('tenant_equipments', 'condition')) {
                $table->enum('condition', ['new', 'used', 'refurbished'])->default('new')->after('status');
            }
            if (!Schema::hasColumn('tenant_equipments', 'assigned_user_id')) {
                $table->unsignedBigInteger('assigned_user_id')->nullable()->after('assigned_to');
            }
            if (!Schema::hasColumn('tenant_equipments', 'purchase_date')) {
                $table->date('purchase_date')->nullable()->after('total_price');
            }
            if (!Schema::hasColumn('tenant_equipments', 'warranty_expiry')) {
                $table->date('warranty_expiry')->nullable()->after('purchase_date');
            }
            if (!Schema::hasColumn('tenant_equipments', 'notes')) {
                $table->text('notes')->nullable()->after('warranty_expiry');
            }

            // check if fk already exists is harder, but we can check the column
            if (Schema::hasColumn('tenant_equipments', 'assigned_user_id')) {
                 // Try to add foreign key if not already present (simplified check)
                 $table->foreign('assigned_user_id')->references('id')->on('network_users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_equipments', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_equipments', 'assigned_user_id')) {
                try {
                    $table->dropForeign(['assigned_user_id']);
                } catch (\Exception $e) {}
            }
            
            $columnsToDrop = [
                'brand',
                'mac_address',
                'status',
                'condition',
                'assigned_user_id',
                'purchase_date',
                'warranty_expiry',
                'notes'
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('tenant_equipments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
