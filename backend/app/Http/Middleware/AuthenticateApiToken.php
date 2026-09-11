<?php

namespace App\Http\Middleware;

use App\Support\ApiTokenAuth;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function __construct(private readonly ApiTokenAuth $auth)
    {
    }

    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $usedBearer = (bool) $request->bearerToken();
        if (!$usedBearer && $request->hasCookie((string) config('rouh.auth.cookie_name', 'rouh_auth')) && in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $origin = trim((string) $request->headers->get('Origin', ''));
            $allowedOrigins = array_values(array_filter(array_map('trim', (array) config('cors.allowed_origins', []))));
            if ($origin === '' || !in_array($origin, $allowedOrigins, true)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Untrusted request origin.',
                ], 403);
            }
        }

        $user = $this->auth->resolveUser($request);

        if (!$user) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $request->attributes->set('authUser', $user);

        return $next($request);
    }
}
