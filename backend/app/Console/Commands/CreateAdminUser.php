<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAdminUser extends Command
{
    protected $signature = 'rouh:admin-create {--email= : Admin email address} {--name=ROUH Owner : Display name}';
    protected $description = 'Create the first ROUH admin user safely in any environment, including production.';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->option('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid email address is required.');
            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();
        if ($existing) {
            $this->error("A user with {$email} already exists. Use admin:reset-password only when a reset is explicitly required.");
            return self::FAILURE;
        }

        $password = (string) $this->secret('Admin password (minimum 12 characters)');
        if (strlen($password) < 12) {
            $this->error('Admin password must contain at least 12 characters.');
            return self::FAILURE;
        }

        $confirmation = (string) $this->secret('Confirm admin password');
        if (!hash_equals($password, $confirmation)) {
            $this->error('Passwords do not match. No user was created.');
            return self::FAILURE;
        }

        $name = trim((string) $this->option('name')) ?: 'ROUH Owner';

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        UserRole::updateOrCreate(
            ['user_id' => (string) $user->id],
            ['role' => 'admin']
        );

        $this->info("Admin user created successfully: {$email}");
        return self::SUCCESS;
    }
}
