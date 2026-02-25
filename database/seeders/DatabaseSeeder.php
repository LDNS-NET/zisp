<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Commented out - UserFactory doesn't exist and isn't needed for production
        // Use RolesAndPermissionsSeeder instead
        // User::factory()->create([
        //     'username' => 'admin',
        //     'email' => 'test@example.com',
        //     'role' => 'superadmin',
        //     'password' => bcrypt('passwordqwertyuiopasdfghjkl'),
        // ]);
        
        // Run the roles and permissions seeder
        $this->call(RolesAndPermissionsSeeder::class);
    }
}
