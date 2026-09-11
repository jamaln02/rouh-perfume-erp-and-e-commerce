<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductReviewsController extends Controller
{
    public function index(string $id): JsonResponse
    {
        $reviews = DB::table('reviews')
            ->leftJoin('profiles', 'profiles.id', '=', 'reviews.user_id')
            ->select([
                'reviews.id',
                'reviews.rating',
                'reviews.comment',
                'reviews.created_at',
                'profiles.full_name as profile_full_name',
            ])
            ->where('reviews.product_id', $id)
            ->where('reviews.approved', true)
            ->orderByDesc('reviews.created_at')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'rating' => (int) $r->rating,
                'comment' => $r->comment,
                'created_at' => $r->created_at,
                'profile' => ['full_name' => $r->profile_full_name],
            ]);

        return response()->json(['reviews' => $reviews]);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
        ]);

        $user = $request->attributes->get('authUser');
        if (!$user) return response()->json(['message' => 'Authentication required.'], 401);
        $userId = (string) $user->id;

        DB::table('profiles')->updateOrInsert(
            ['id' => $userId],
            ['updated_at' => now(), 'created_at' => now()]
        );

        $existing = DB::table('reviews')
            ->where('product_id', $id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            DB::table('reviews')->where('id', $existing->id)->update([
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'approved' => false,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('reviews')->insert([
                'id' => (string) Str::uuid(),
                'product_id' => $id,
                'user_id' => $userId,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'approved' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
