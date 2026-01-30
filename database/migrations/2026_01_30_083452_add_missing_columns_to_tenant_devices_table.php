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
        Schema::table('tenant_devices', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('tenant_devices', 'mikrotik_id')) {
                $table->unsignedBigInteger('mikrotik_id')->nullable()->after('subscriber_id')->comment('Associated MikroTik router');
                
                // Add foreign key inline since column doesn't exist yet
                $table->foreign('mikrotik_id')
                      ->references('id')->on('tenant_mikrotiks')
                      ->onDelete('set null');
            }
            
            if (!Schema::hasColumn('tenant_devices', 'firmware')) {
                $table->string('firmware')->nullable()->after('manufacturer');
            }
            
            if (!Schema::hasColumn('tenant_devices', 'raw_parameters')) {
                $table->json('raw_parameters')->nullable()->after('last_contact_at')->comment('Full device data model from GenieACS');
            }
            
            if (!Schema::hasColumn('tenant_devices', 'connection_request_url')) {
                $table->string('connection_request_url')->nullable()->after('raw_parameters');
            }
            
            // Add indexes (Laravel will skip if they already exist)
            $table->index(['tenant_id', 'online'], 'tenant_devices_tenant_id_online_index');
            $table->index('last_contact_at', 'tenant_devices_last_contact_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_devices', function (Blueprint $table) {
            // Drop foreign key
            $table->dropForeign(['mikrotik_id']);
            
            // Drop indexes
            $table->dropIndex(['tenant_id', 'online']);
            $table->dropIndex(['last_contact_at']);
            
            // Drop columns
            $table->dropColumn([
                'mikrotik_id',
                'firmware',
                'raw_parameters',
                'connection_request_url',
            ]);
        });
    }
};
