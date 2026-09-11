<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ListUsersController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $profiles = DB::table('profiles')
            ->select(['id', 'full_name', 'phone', 'created_at'])
            ->orderByDesc('created_at')
            ->get();

        $roles = DB::table('user_roles')->select(['user_id', 'role'])->get()->keyBy('user_id');

        $users = $profiles->map(function ($p) use ($roles) {
            $role = $roles->get($p->id);

            return [
                'id' => $p->id,
                'full_name' => $p->full_name,
                'phone' => $p->phone,
                'email' => DB::table('users')->where('id', $p->id)->value('email'),
                'is_active' => (bool) (DB::table('users')->where('id', $p->id)->value('is_active') ?? true),
                'created_at' => $p->created_at,
                'role' => $role?->role ?? 'customer',
            ];
        })->values();

        return response()->json(['users' => $users]);
    }
}
