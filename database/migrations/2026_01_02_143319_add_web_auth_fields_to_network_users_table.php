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
            $table->string('web_password')->nullable()->after('password');
            $table->rememberToken()->after('web_password');
        });

        // Backfill existing users
        $users = \Illuminate\Support\Facades\DB::table('network_users')->get();
        foreach ($users as $user) {
            if ($user->password && !$user->web_password) {
                \Illuminate\Support\Facades\DB::table('network_users')
                    ->where('id', $user->id)
                    ->update(['web_password' => \Illuminate\Support\Facades\Hash::make($user->password)]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('network_users', function (Blueprint $table) {
            $table->dropColumn(['web_password', 'remember_token']);
        });
    }
};
