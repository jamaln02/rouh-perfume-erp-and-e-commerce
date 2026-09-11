<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = DB::table('categories')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'categories' => $categories,
        ]);
    }
}
