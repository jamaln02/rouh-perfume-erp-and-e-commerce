<?php

namespace App\Http\Middleware;

use App\Support\PermissionService;
use App\Support\ApiTokenAuth;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function __construct(private readonly ApiTokenAuth $auth) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response|JsonResponse
    {
        $user = $request->attributes->get('authUser');
        if (!$user || !isset($user->id)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $role = $this->auth->role((string) $user->id);
        $request->attributes->set('authRole', $role);
        $granted = PermissionService::forUser((string) $user->id, $role);
        foreach ($permissions as $permission) {
            if (in_array($permission, $granted, true)) {
                return $next($request);
            }
        }

        return response()->json([
            'ok' => false,
            'message' => 'Forbidden',
            'required_permissions' => $permissions,
        ], 403);
    }
}
