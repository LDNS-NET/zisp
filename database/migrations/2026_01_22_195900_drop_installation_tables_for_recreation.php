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
        // Drop tables in reverse order of dependencies
        Schema::dropIfExists('tenant_technician_locations');
        Schema::dropIfExists('tenant_installation_photos');
        Schema::dropIfExists('tenant_installation_checklists');
        Schema::dropIfExists('tenant_installations');
        Schema::dropIfExists('tenant_technicians');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Nothing to do - the tables will be recreated by the main migrations
    }
};
