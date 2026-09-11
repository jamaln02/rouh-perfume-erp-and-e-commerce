<?php
namespace App\Http\Controllers;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class AuditLogController extends Controller {
    public function index(Request $request): JsonResponse {
        $query = AuditLog::with('user:id,name,email')->orderByDesc('occurred_at');
        if ($request->filled('user_id')) $query->where('user_id', $request->string('user_id'));
        if ($request->filled('action')) $query->where('action', $request->string('action'));
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('date_from')) $query->whereDate('occurred_at', '>=', $request->date('date_from'));
        if ($request->filled('date_to')) $query->whereDate('occurred_at', '<=', $request->date('date_to'));
        return response()->json($query->paginate(min(max((int)$request->input('per_page', 50), 1), 100)));
    }
}
