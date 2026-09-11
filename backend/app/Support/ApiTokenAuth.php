<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiTokenAuth
{
    public function resolveUser(Request $request): ?object
    {
        $token = $request->bearerToken();
        if (!$token) {
            $token = $request->cookie((string) config('rouh.auth.cookie_name', 'rouh_auth'));
        }
        if (!$token) {
            return null;
        }

        $tokenHash = hash('sha256', $token);

        $row = DB::table('api_tokens')
            ->join('users', 'users.id', '=', 'api_tokens.user_id')
            ->select([
                'users.id',
                'users.email',
                'users.name',
                'users.phone',
                'users.is_active',
                'api_tokens.token_hash',
                'api_tokens.expires_at',
            ])
            ->where('api_tokens.token_hash', $tokenHash)
            ->first();

        if (!$row || !((bool) ($row->is_active ?? true))) {
            return null;
        }

        if ($row->expires_at && now()->greaterThan($row->expires_at)) {
            DB::table('api_tokens')->where('token_hash', $tokenHash)->delete();
            return null;
        }

        return $row;
    }

    public function issueToken(int|string $userId): string
    {
        $plainToken = bin2hex(random_bytes(48));
        $expiresAt = now()->addMinutes(max(5, (int) config('rouh.auth.cookie_minutes', 43200)));

        DB::table('api_tokens')->insert([
            'user_id' => $userId,
            'token_hash' => hash('sha256', $plainToken),
            'last_used_at' => now(),
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $plainToken;
    }

    public function touchToken(string $tokenHash): void
    {
        DB::table('api_tokens')->where('token_hash', $tokenHash)->update([
            'last_used_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function deleteToken(string $tokenHash): void
    {
        DB::table('api_tokens')->where('token_hash', $tokenHash)->delete();
    }

    public function role(string $userId): ?string
    {
        $role = DB::table('user_roles')
            ->where('user_id', $userId)
            ->value('role');

        return $role ? (string) $role : null;
    }

    public function isAdmin(string $userId): bool
    {
        return $this->role($userId) === 'admin';
    }

    public function isStaff(string $userId): bool
    {
        return in_array($this->role($userId), ['admin', 'manager', 'employee'], true);
    }
}
