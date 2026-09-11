<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FavoriteController — DB-backed user favorites (wishlist).
 *
 * All routes are protected by the `auth.token` middleware. The authenticated
 * user is resolved from the bearer token (request attribute `authUser`) — we
 * NEVER trust a client-supplied user id. Favorites are always scoped to the
 * authenticated user.
 *
 * Endpoints:
 *   GET    /api/favorites            → list the user's favorited products
 *   POST   /api/favorites/{productId} → add a product to favorites
 *   DELETE /api/favorites/{productId} → remove a product from favorites
 */
class FavoriteController extends Controller
{
    /**
     * List the authenticated user's favorite products with enough detail for
     * the wishlist UI (name, price, image, sizes).
     */
    public function index(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('authUser');
        $userId = (int) $authUser->id;

        $products = DB::table('favorites')
            ->join('products', 'products.id', '=', 'favorites.product_id')
            ->select([
                'products.id',
                'products.name',
                'products.name_ar',
                'products.price',
                'products.image_url',
                'products.sizes',
                'products.fragrance',
                'products.fragrance_family',
                'products.is_new',
                'products.best_seller',
                'products.featured',
                'favorites.created_at as favorited_at',
            ])
            ->where('favorites.user_id', $userId)
            ->orderByDesc('favorites.created_at')
            ->get();

        // Normalise image URL + sizes for the storefront (same logic as
        // ProductController so the wishlist renders consistently).
        $products = $products->map(function ($p) {
            if (isset($p->image_url) && is_string($p->image_url) && $p->image_url !== '' && str_starts_with($p->image_url, '/')) {
                $p->image_url = url($p->image_url);
            }
            $sizes = $p->sizes;
            if (is_string($sizes)) {
                $decoded = json_decode($sizes, true);
                $sizes = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $sizes)));
            }
            if (!is_array($sizes)) {
                $sizes = [];
            }
            $p->sizes = array_values(array_filter(array_map('strval', $sizes), fn ($v) => trim($v) !== ''));
            $p->available_sizes = $p->sizes;
            return $p;
        });

        return response()->json(['ok' => true, 'favorites' => $products]);
    }

    /**
     * Add a product to the authenticated user's favorites. Idempotent: if the
     * product is already favorited we return success without duplicating.
     */
    public function store(Request $request, string $productId): JsonResponse
    {
        $authUser = $request->attributes->get('authUser');
        $userId = (int) $authUser->id;

        // Verify the product exists.
        $exists = DB::table('products')->where('id', $productId)->exists();
        if (!$exists) {
            return response()->json(['ok' => false, 'message' => 'Product not found'], 404);
        }

        DB::table('favorites')->updateOrInsert(
            ['user_id' => $userId, 'product_id' => $productId],
            ['created_at' => now(), 'updated_at' => now()]
        );

        return response()->json(['ok' => true, 'product_id' => $productId], 201);
    }

    /**
     * Remove a product from the authenticated user's favorites. Idempotent: a
     * missing favorite is treated as success (DELETE should be safe to repeat).
     */
    public function destroy(Request $request, string $productId): JsonResponse
    {
        $authUser = $request->attributes->get('authUser');
        $userId = (int) $authUser->id;

        DB::table('favorites')
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->delete();

        return response()->json(['ok' => true, 'product_id' => $productId]);
    }
}
