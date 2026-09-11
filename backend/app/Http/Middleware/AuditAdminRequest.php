<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuditAdminRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->attributes->get('authUser');

        if (!$user || !str_starts_with($request->path(), 'api/admin/')) {
            return $response;
        }

        // Audit only meaningful state-changing admin operations. Read-only GETs are intentionally excluded
        // to avoid a noisy activity log full of empty/view entries. Authentication events are recorded by AuthController.
        if ($request->isMethod('GET')) {
            return $response;
        }

        try {
            $role = $request->attributes->get('authRole')
                ?: \DB::table('user_roles')->where('user_id', $user->id)->value('role');
            $route = optional($request->route())->uri() ?: $request->path();
            $params = $request->except([
                'password', 'password_confirmation', 'token', 'api_token',
                'unit_cost', 'cost_per_unit', 'avg_unit_cost', 'total_cost',
                'total_cost_syp', 'amount', 'paid_amount', 'card_number',
            ]);

            $responseData = null;
            if (str_contains((string) $response->headers->get('Content-Type'), 'application/json')) {
                $decoded = json_decode($response->getContent(), true);
                if (is_array($decoded)) {
                    $responseData = collect($decoded)->only(['ok', 'message', 'id', 'order_id', 'user_id'])->all();
                }
            }

            $before = $request->attributes->get('audit.before');
            $after = $request->attributes->get('audit.after');
            $changes = null;
            if (is_array($before) && is_array($after)) {
                $changes = [];
                foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
                    $old = $before[$key] ?? null;
                    $new = $after[$key] ?? null;
                    if ($old !== $new) {
                        $changes[$key] = ['before' => $old, 'after' => $new];
                    }
                }
            }

            AuditLog::create([
                'user_id' => $user->id,
                'user_role' => $role,
                'action' => match ($request->method()) {
                    'POST' => 'create',
                    'PUT', 'PATCH' => 'update',
                    'DELETE' => 'delete',
                    default => 'view',
                },
                'method' => $request->method(),
                'route' => $route,
                'entity_type' => $this->entityType($route),
                'entity_id' => $request->route('id') ?? $request->route('productId') ?? $request->route('assetId'),
                'status' => $response->getStatusCode() < 400 ? 'success' : 'failed',
                'status_code' => $response->getStatusCode(),
                'reason' => $request->attributes->get('audit.reason') ?? $request->input('reason'),
                'before_data' => $before,
                'after_data' => $after,
                'changes' => $changes,
                'request_data' => $request->isMethod('GET') ? null : $params,
                'response_data' => $responseData,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Auditing must never turn a successful business operation into a 500 response.
            Log::warning('Audit log failed', ['message' => $e->getMessage()]);
        }

        return $response;
    }

    private function entityType(string $route): ?string
    {
        $parts = explode('/', trim($route, '/'));
        $i = array_search('admin', $parts, true);
        return $i === false || !isset($parts[$i + 1])
            ? null
            : implode('.', array_slice($parts, $i + 1, 2));
    }
}
