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
            if (!Schema::hasColumn('tenant_equipments', 'quantity')) {
                $table->integer('quantity')->default(1)->after('condition');
            }
            
            // Supporting bulk items by making serial number and mac address nullable
            $table->string('serial_number')->nullable()->change();
            $table->string('mac_address')->nullable()->change();
        });

        Schema::create('tenant_equipment_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('tenant_equipments')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Staff who used it
            $table->integer('quantity');
            $table->string('details')->nullable();
            $table->timestamp('used_at')->useCurrent();
            $table->timestamps();
            
            // Tenant isolation is usually handled by the database schema or a global scope, 
            // but for consistency with other tables in this project:
            $table->unsignedBigInteger('tenant_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_equipment_usages');

        Schema::table('tenant_equipments', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_equipments', 'quantity')) {
                $table->dropColumn('quantity');
            }
            
            // Reverting nullability might be tricky if data exists, but typical:
            $table->string('serial_number')->nullable(false)->change();
        });
    }
};
