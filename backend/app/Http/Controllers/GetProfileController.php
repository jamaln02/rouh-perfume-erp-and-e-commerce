<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GetProfileController extends Controller
{
    public function __invoke(string $id): JsonResponse
    {
        $authUser = request()->attributes->get('authUser');
        if (!$authUser || (string)$authUser->id !== (string)$id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $profile = DB::table('profiles')
            ->select(['id', 'full_name', 'phone', 'loyalty_points'])
            ->where('id', $id)
            ->first();

        if (!$profile) {
            return response()->json([
                'profile' => [
                    'id' => $id,
                    'full_name' => null,
                    'phone' => null,
                    'loyalty_points' => 0,
                ],
            ]);
        }

        return response()->json(['profile' => $profile]);
    }
}
