<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateUserRoleController extends Controller
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['role'=>['required','in:admin,manager,employee,customer,owner']]);
        $actorRole = $request->attributes->get('authRole') ?: 'admin';
        if (in_array($data['role'], ['admin','owner'], true) && $actorRole !== 'admin') {
            return response()->json(['ok'=>false,'message'=>'Only the owner can assign the owner role'], 403);
        }
        DB::table('user_roles')->updateOrInsert(
            ['user_id'=>(string)$id],
            ['role'=>($data['role'] === 'owner' ? 'admin' : $data['role']),'created_at'=>now(),'updated_at'=>now()]
        );
        return response()->json(['ok'=>true]);
    }
}
