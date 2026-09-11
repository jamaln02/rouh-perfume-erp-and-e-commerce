<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetAdminPassword extends Command
{
    protected $signature = 'admin:reset-password {email} {password}';
    protected $description = 'Reset admin password';

    public function handle()
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        if (strlen($password) < 12) {
            $this->error('Admin password must contain at least 12 characters.');
            return 1;
        }

        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $this->error("User not found: {$email}");
            return 1;
        }

        $user->password = Hash::make($password);
        $user->save();

        $this->info("Password updated for: {$email}");
        return 0;
    }
}
