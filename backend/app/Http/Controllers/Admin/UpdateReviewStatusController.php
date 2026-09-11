<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateReviewStatusController extends Controller
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'approved' => ['required', 'boolean'],
        ]);

        DB::table('reviews')->where('id', $id)->update([
            'approved' => $validated['approved'],
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
