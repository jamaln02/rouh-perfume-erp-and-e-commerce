<?php
namespace App\Http\Middleware;
use App\Support\ApiTokenAuth;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class RequireRole {
    public function __construct(private readonly ApiTokenAuth $auth) {}
    public function handle(Request $request, Closure $next, ...$roles): Response|JsonResponse {
        $user = $request->attributes->get('authUser');
        $role = $user ? $this->auth->role((string)$user->id) : null;
        if (!$user || !$role || !in_array($role, $roles, true)) {
            return response()->json(['ok'=>false,'message'=>'Forbidden','required_roles'=>$roles], 403);
        }
        $request->attributes->set('authRole', $role);
        return $next($request);
    }
}
