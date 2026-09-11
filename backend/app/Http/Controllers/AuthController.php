<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\ApiTokenAuth;
use App\Support\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private readonly ApiTokenAuth $auth) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'regex:/^\+9639\d{8}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        $result = DB::transaction(function () use ($validated): array {
            $user = User::create([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'phone' => $validated['phone'],
                'password' => $validated['password'],
                'is_active' => true,
            ]);

            DB::table('profiles')->updateOrInsert(
                ['id' => (string) $user->id],
                ['full_name' => $user->name, 'phone' => $user->phone, 'created_at' => now(), 'updated_at' => now()]
            );
            DB::table('user_roles')->updateOrInsert(
                ['user_id' => (string) $user->id],
                ['role' => 'customer', 'created_at' => now(), 'updated_at' => now()]
            );

            $plainToken = $this->auth->issueToken($user->id);
            return [$user, $plainToken, 'customer', []];
        });

        [$user, $plainToken, $role, $permissions] = $result;
        $response = response()->json([
            'ok' => true,
            ...$this->userPayload($user, (string) $role, $permissions),
        ], 201);

        return $this->attachAuthCookie($response, $plainToken);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim($validated['identifier']);
        $email = strtolower($identifier);
        $phone = $this->normalizeSyrianPhone($identifier);
        $looksLikePhone = (bool) preg_match('/^\+?\d[\d\s\-()]*$/', $identifier);

        if ($looksLikePhone && !$phone) {
            return response()->json([
                'ok' => false,
                'message' => 'Phone number must start with +963 and use a valid Syrian format.',
            ], 422);
        }

        $user = User::query()
            ->when($phone, fn ($query) => $query->where('phone', $phone))
            ->when(!$phone, fn ($query) => $query->where('email', $email))
            ->first();
        if (!$user || !(bool) ($user->is_active ?? true) || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['ok' => false, 'message' => 'Invalid credentials'], 401);
        }

        $role = $this->auth->role((string) $user->id) ?? 'customer';
        $permissions = PermissionService::forUser((string) $user->id, $role);
        $plainToken = $this->auth->issueToken($user->id);

        AuditLog::create([
            'user_id' => $user->id,
            'user_role' => $role,
            'action' => 'login',
            'method' => 'POST',
            'route' => 'api/auth/login',
            'entity_type' => 'auth',
            'entity_id' => (string) $user->id,
            'status' => 'success',
            'status_code' => 200,
            'request_data' => ['email' => $user->email],
            'response_data' => ['ok' => true],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'occurred_at' => now(),
        ]);

        $response = response()->json([
            'ok' => true,
            ...$this->userPayload($user, $role, $permissions),
        ]);

        return $this->attachAuthCookie($response, $plainToken);
    }

    private function userPayload(User $user, string $role, array $permissions): array
    {
        $legacyToken = null;
        if (filter_var(env('ROUH_LEGACY_BEARER_RESPONSE', false), FILTER_VALIDATE_BOOL)) {
            // The plain token is intentionally not included here by default.
            // Legacy API consumers should opt in explicitly; browser clients use the HttpOnly cookie.
            $legacyToken = null;
        }

        return array_filter([
            'token' => $legacyToken,
            'user' => [
                'id' => (string) $user->id,
                'email' => $user->email,
                'phone' => $user->phone,
                'name' => $user->name,
                'role' => $role,
            ],
            'role' => $role,
            'permissions' => $permissions,
            'isAdmin' => $this->auth->isStaff((string) $user->id),
        ], static fn ($value) => $value !== null);
    }

    private function attachAuthCookie(JsonResponse $response, string $plainToken): JsonResponse
    {
        return $response->withCookie(cookie(
            (string) config('rouh.auth.cookie_name', 'rouh_auth'),
            $plainToken,
            max(5, (int) config('rouh.auth.cookie_minutes', 43200)),
            '/',
            config('rouh.auth.cookie_domain'),
            app()->environment('production'),
            true,
            false,
            (string) config('rouh.auth.cookie_same_site', 'lax'),
        ));
    }

    private function normalizeSyrianPhone(string $value): ?string
    {
        $phone = preg_replace('/[\s\-()]/', '', trim($value));
        if (!$phone) return null;
        return preg_match('/^\+9639\d{8}$/', $phone) ? $phone : null;
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->auth->resolveUser($request);
        if (!$user || !isset($user->token_hash)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $this->auth->touchToken((string) $user->token_hash);
        $role = $this->auth->role((string) $user->id) ?? 'customer';
        $permissions = PermissionService::forUser((string) $user->id, $role);

        return response()->json([
            'ok' => true,
            'user' => [
                'id' => (string) $user->id,
                'email' => $user->email,
                'phone' => $user->phone,
                'name' => $user->name,
                'role' => $role,
            ],
            'role' => $role,
            'permissions' => $permissions,
            'isAdmin' => $this->auth->isStaff((string) $user->id),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $this->auth->resolveUser($request);
        if ($user && isset($user->token_hash)) {
            $role = $this->auth->role((string) $user->id) ?? 'customer';
            $this->auth->deleteToken((string) $user->token_hash);
            AuditLog::create([
                'user_id' => $user->id,
                'user_role' => $role,
                'action' => 'logout',
                'method' => 'POST',
                'route' => 'api/auth/logout',
                'entity_type' => 'auth',
                'entity_id' => (string) $user->id,
                'status' => 'success',
                'status_code' => 200,
                'request_data' => null,
                'response_data' => ['ok' => true],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'occurred_at' => now(),
            ]);
        }

        return response()->json(['ok' => true])
            ->withCookie(cookie(
                (string) config('rouh.auth.cookie_name', 'rouh_auth'),
                '',
                -1,
                '/',
                config('rouh.auth.cookie_domain'),
                app()->environment('production'),
                true,
                false,
                (string) config('rouh.auth.cookie_same_site', 'lax'),
            ));
    }
}
