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
        // STEP 1: Consolidate existing data - merge multiple rows per tenant into one
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        $tenants = DB::table('tenant_sms_gateways')
            ->select('tenant_id')
            ->groupBy('tenant_id')
            ->get();
        
        foreach ($tenants as $tenant) {
            $rows = DB::table('tenant_sms_gateways')
                ->where('tenant_id', $tenant->tenant_id)
                ->get();
            
            if ($rows->count() > 1) {
                // Keep the first row, merge data from others
                $firstRow = $rows->first();
                $mergedData = [
                    'tenant_id' => $tenant->tenant_id,
                    'provider' => $firstRow->provider,
                    'is_active' => $firstRow->is_active,
                    'label' => $firstRow->label,
                    'is_default' => $firstRow->is_default ?? false,
                ];
                
                // Delete all rows for this tenant
                DB::table('tenant_sms_gateways')
                    ->where('tenant_id', $tenant->tenant_id)
                    ->delete();
                
                // Insert the consolidated row
                DB::table('tenant_sms_gateways')->insert($mergedData);
            }
        }
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        
        // STEP 2: Drop foreign key and constraints
        Schema::table('tenant_sms_gateways', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropUnique(['tenant_id', 'provider']);
            
            // Drop old generic columns
            $table->dropColumn(['username', 'api_key', 'sender_id', 'api_secret']);
        });
        
        // STEP 3: Add new provider-specific columns
        Schema::table('tenant_sms_gateways', function (Blueprint $table) {
            // Talksasa
            $table->text('talksasa_api_key')->nullable();
            $table->string('talksasa_sender_id')->nullable();
            
            // Celcom
            $table->text('celcom_partner_id')->nullable();
            $table->text('celcom_api_key')->nullable();
            $table->string('celcom_sender_id')->nullable();
            
            // Africa's Talking
            $table->string('africastalking_username')->nullable();
            $table->text('africastalking_api_key')->nullable();
            $table->string('africastalking_sender_id')->nullable();
            
            // Twilio
            $table->text('twilio_account_sid')->nullable();
            $table->text('twilio_auth_token')->nullable();
            $table->string('twilio_from_number')->nullable();
            
            // Add tenant_id unique constraint (one row per tenant)
            $table->unique('tenant_id');
            
            // Re-add foreign key constraint
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_sms_gateways', function (Blueprint $table) {
            // Drop foreign key and unique constraint
            $table->dropForeign(['tenant_id']);
            $table->dropUnique(['tenant_id']);
            
            // Drop provider-specific columns
            $table->dropColumn([
                'talksasa_api_key',
                'talksasa_sender_id',
                'celcom_partner_id',
                'celcom_api_key',
                'celcom_sender_id',
                'africastalking_username',
                'africastalking_api_key',
                'africastalking_sender_id',
                'twilio_account_sid',
                'twilio_auth_token',
                'twilio_from_number',
            ]);
            
            // Restore old columns
            $table->string('username')->nullable();
            $table->string('api_key')->nullable();
            $table->string('sender_id')->nullable();
            $table->string('api_secret')->nullable();
            
            // Restore the old unique constraint
            $table->unique(['tenant_id', 'provider']);
            
            // Restore foreign key
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }
};
