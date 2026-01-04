<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'superadmin:create 
                            {--email= : The email address of the superadmin}
                            {--name= : The name of the superadmin}
                            {--password= : The password for the superadmin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new SuperAdmin user or update existing user to SuperAdmin';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔐 SuperAdmin Account Creator');
        $this->newLine();

        // Get email
        $email = $this->option('email') ?: $this->ask('Email address');
        
        // Validate email
        $validator = Validator::make(['email' => $email], [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            $this->error('Invalid email address!');
            return Command::FAILURE;
        }

        // Check if user exists
        $user = User::where('email', $email)->first();

        if ($user) {
            $this->warn("User with email {$email} already exists.");
            
            if (!$this->confirm('Do you want to convert this user to SuperAdmin?', true)) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }

            // Update existing user to superadmin
            $user->update([
                'role' => 'superadmin',
                'is_super_admin' => true,
                'tenant_id' => null,
                'email_verified_at' => now(),
            ]);

            $this->info("✅ User {$user->name} ({$email}) has been converted to SuperAdmin!");
            
            if ($this->confirm('Do you want to reset the password?', false)) {
                $password = $this->secret('New password');
                $passwordConfirmation = $this->secret('Confirm password');

                if ($password !== $passwordConfirmation) {
                    $this->error('Passwords do not match!');
                    return Command::FAILURE;
                }

                $user->update(['password' => Hash::make($password)]);
                $this->info('✅ Password updated successfully!');
            }

        } else {
            // Create new superadmin
            $name = $this->option('name') ?: $this->ask('Full name');
            $phone = $this->ask('Phone number');
            $password = $this->option('password') ?: $this->secret('Password');
            
            if (!$this->option('password')) {
                $passwordConfirmation = $this->secret('Confirm password');
                
                if ($password !== $passwordConfirmation) {
                    $this->error('Passwords do not match!');
                    return Command::FAILURE;
                }
            }

            // Validate password
            if (strlen($password) < 8) {
                $this->error('Password must be at least 8 characters!');
                return Command::FAILURE;
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make($password),
                'role' => 'superadmin',
                'is_super_admin' => true,
                'tenant_id' => null,
                'email_verified_at' => now(),
                'username' => explode('@', $email)[0] . '_superadmin',
            ]);

            $this->newLine();
            $this->info('✅ SuperAdmin account created successfully!');
        }

        // Display account details
        $this->newLine();
        $this->table(
            ['Field', 'Value'],
            [
                ['Name', $user->name],
                ['Email', $user->email],
                ['Role', $user->role],
                ['Tenant ID', $user->tenant_id ?? 'null (✓)'],
                ['Email Verified', $user->email_verified_at ? 'Yes (✓)' : 'No'],
            ]
        );

        $this->newLine();
        $this->info('🎉 You can now login at: ' . route('admin.login'));
        $this->info('📧 Email: ' . $user->email);

        return Command::SUCCESS;
    }
}
