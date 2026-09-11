<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BulkImportProductsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file = $request->file('file');
        if ($file === null) {
            return response()->json(['message' => 'CSV file is required'], 422);
        }

        $content = file_get_contents($file->getRealPath());
        if ($content === false) {
            return response()->json(['message' => 'Unable to read CSV file'], 422);
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if ($lines === false || count($lines) < 2) {
            return response()->json(['message' => 'CSV file is empty or missing data rows'], 422);
        }

        $headers = array_map(
            static fn ($h) => strtolower(trim((string) $h)),
            str_getcsv((string) array_shift($lines))
        );

        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line);
            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = isset($values[$i]) ? trim((string) $values[$i]) : null;
            }

            $validation = Validator::make($row, [
                'name' => ['required', 'string', 'max:200'],
                'name_ar' => ['required', 'string', 'max:200'],
                'description' => ['nullable', 'string'],
                'description_ar' => ['nullable', 'string'],
                'price' => ['required', 'numeric', 'min:0'],
                'stock' => ['nullable', 'integer', 'min:0'],
                'fragrance' => ['nullable', 'string', 'max:50'],
                'image_url' => ['nullable', 'string', 'max:1000'],
                'category_id' => ['nullable', 'string', 'max:64'],
                'category_slug' => ['nullable', 'string', 'max:100'],
                'is_new' => ['nullable', 'boolean'],
                'best_seller' => ['nullable', 'boolean'],
                'featured' => ['nullable', 'boolean'],
            ]);

            if ($validation->fails()) {
                $failed++;
                $errors[] = 'Row '.($index + 2).': '.$validation->errors()->first();
                continue;
            }

            $data = $validation->validated();
            $categoryId = $this->resolveCategoryId(
                $data['category_id'] ?? null,
                $data['category_slug'] ?? null
            );

            if (($data['category_id'] ?? null) || ($data['category_slug'] ?? null)) {
                if ($categoryId === null) {
                    $failed++;
                    $errors[] = 'Row '.($index + 2).': category not found for provided category_id/category_slug.';
                    continue;
                }
            }

            try {
                $basePrice = (float) $data['price'];
                $sizes = ['50ml', '100ml'];
                $sizePrices = [
                    '50ml' => $basePrice,
                    '100ml' => $basePrice,
                ];

                DB::table('products')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => $data['name'],
                    'name_ar' => $data['name_ar'],
                    'description' => $data['description'] ?? null,
                    'description_ar' => $data['description_ar'] ?? null,
                    'price' => $basePrice,
                    'image_url' => $data['image_url'] ?? null,
                    'category_id' => $categoryId,
                    'fragrance' => $data['fragrance'] ?? 'oriental',
                    'sizes' => json_encode($sizes),
                    'size_prices' => json_encode($sizePrices),
                    'featured' => $this->toBool($data['featured'] ?? false),
                    'is_new' => $this->toBool($data['is_new'] ?? false),
                    'best_seller' => $this->toBool($data['best_seller'] ?? false),
                    'stock' => isset($data['stock']) ? (int) $data['stock'] : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = 'Row '.($index + 2).': '.$e->getMessage();
            }
        }

        return response()->json([
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ]);
    }

    private function resolveCategoryId(?string $categoryId, ?string $categorySlug): ?string
    {
        if ($categoryId !== null && $categoryId !== '') {
            $exists = DB::table('categories')->where('id', $categoryId)->exists();
            if ($exists) {
                return $categoryId;
            }
        }

        if ($categorySlug !== null && $categorySlug !== '') {
            $category = DB::table('categories')->where('slug', $categorySlug)->first();
            if ($category !== null) {
                return (string) $category->id;
            }
        }

        return null;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['1', 'true', 'yes', 'y'], true);
        }

        return (bool) $value;
    }
}
