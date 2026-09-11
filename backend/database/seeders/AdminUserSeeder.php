<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\PermissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if ((string) env('ROUH_OWNER_PASSWORD', '') === '') { throw new \RuntimeException('ROUH_OWNER_PASSWORD is required; demo/default admin credentials are disabled.'); }
        // Never ship hard-coded production credentials. Demo users are opt-in for
        // local development; production users must be provided through .env.
        if (app()->environment('local') && filter_var(env('SEED_DEMO_USERS', false), FILTER_VALIDATE_BOOL)) {
            $this->upsertAccount('admin@rouh.local', 'ROUH Owner', '+963900000001', 'admin', (string) env('ROUH_OWNER_PASSWORD', ''));
            $this->upsertAccount('manager@rouh.local', 'ROUH Manager', '+963900000002', 'manager', (string) env('ROUH_OWNER_PASSWORD', ''));
            $this->upsertAccount('employee@rouh.local', 'ROUH Employee', '+963900000003', 'employee', (string) env('ROUH_OWNER_PASSWORD', ''));
        }

        $ownerPassword = (string) env('ROUH_OWNER_PASSWORD', '');
        $ownerEmail = trim((string) env('ROUH_OWNER_EMAIL', ''));
        if ($ownerEmail !== '' && $ownerPassword !== '') {
            $this->upsertAccount(
                $ownerEmail,
                (string) env('ROUH_OWNER_NAME', 'ROUH Owner'),
                (string) env('ROUH_OWNER_PHONE', ''),
                'admin',
                $ownerPassword
            );
        }
    }

    private function upsertAccount(string $email, string $name, string $phone, string $role, string $password): void
    {
        $user = User::query()->updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => $password,
            'is_active' => true,
        ]);

        DB::table('profiles')->updateOrInsert(['id' => (string) $user->id], [
            'full_name' => $name,
            'phone' => $phone,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_roles')->updateOrInsert(['user_id' => (string) $user->id], [
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($role !== 'admin' && DB::table('user_permissions')->where('user_id', (string) $user->id)->count() === 0) {
            foreach (PermissionService::defaultsForRole($role) as $permission) {
                DB::table('user_permissions')->insertOrIgnore([
                    'user_id' => (string) $user->id,
                    'permission' => $permission,
                    'allowed' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
