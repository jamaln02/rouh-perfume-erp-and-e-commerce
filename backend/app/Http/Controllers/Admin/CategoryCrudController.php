<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryCrudController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = DB::table('categories')->orderBy('created_at')->get();
        return response()->json(['categories' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'name_ar' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100'],
        ]);

        $id = (string) Str::uuid();
        DB::table('categories')->insert([
            'id' => $id,
            'name' => $validated['name'],
            'name_ar' => $validated['name_ar'],
            'slug' => $validated['slug'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'name_ar' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100'],
        ]);

        $updated = DB::table('categories')->where('id', $id)->update([
            'name' => $validated['name'],
            'name_ar' => $validated['name_ar'],
            'slug' => $validated['slug'],
            'updated_at' => now(),
        ]);

        if (!$updated) {
            return response()->json(['ok' => false, 'message' => 'Category not found'], 404);
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(string $id): JsonResponse
    {
        $exists = DB::table('categories')->where('id', $id)->exists();
        if (!$exists) {
            return response()->json(['ok' => false, 'message' => 'Category not found'], 404);
        }

        if (DB::table('products')->where('category_id', $id)->exists()) {
            return response()->json([
                'ok' => false,
                'message' => 'This category still contains products. Move the products to another category before deleting it.',
            ], 409);
        }

        DB::table('categories')->where('id', $id)->delete();
        return response()->json(['ok' => true]);
    }
}
