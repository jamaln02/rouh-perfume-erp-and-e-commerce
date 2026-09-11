<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DeleteReviewController extends Controller
{
    public function __invoke(string $id): JsonResponse
    {
        DB::table('reviews')->where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }
}
