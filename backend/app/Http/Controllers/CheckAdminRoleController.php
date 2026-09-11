<?php

namespace App\Http\Controllers;

use App\Support\ApiTokenAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckAdminRoleController extends Controller
{
    public function __construct(private readonly ApiTokenAuth $auth)
    {
    }

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $user = $request->attributes->get('authUser');

        if (!$user || (string) $user->id !== $id) {
            return response()->json([
                'ok' => false,
                'message' => 'Forbidden',
            ], 403);
        }

        return response()->json(['isAdmin' => $this->auth->isAdmin($id)]);
    }
}
