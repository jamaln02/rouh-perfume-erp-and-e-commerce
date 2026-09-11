<?php

namespace App\Http\Middleware;

use App\Support\ApiTokenAuth;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminApi
{
    public function __construct(private readonly ApiTokenAuth $auth)
    {
    }

    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $user = $request->attributes->get('authUser');

        if (!$user || !isset($user->id)) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        if (!$this->auth->isStaff((string) $user->id)) {
            return response()->json([
                'ok' => false,
                'message' => 'Staff access required',
            ], 403);
        }

        return $next($request);
    }
}
