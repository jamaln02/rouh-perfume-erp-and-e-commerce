<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\VariantSizeMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VariantSizeMediaController extends Controller
{
    public function index(): JsonResponse
    {
        $labels = ProductVariant::query()
            ->where('is_active', true)
            ->orderBy('size_label')
            ->pluck('size_label')
            ->map(fn ($label) => trim((string) $label))
            ->filter()
            ->unique(fn ($label) => $this->sizeKey($label))
            ->values();

        $media = VariantSizeMedia::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('size_label')
            ->get();

        $byKey = $media->keyBy('size_key');
        foreach ($labels as $label) {
            $key = $this->sizeKey($label);
            if (!$byKey->has($key)) {
                $byKey->put($key, new VariantSizeMedia([
                    'size_key' => $key,
                    'size_label' => $label,
                    'image_url' => null,
                    'image_is_reference' => false,
                    'is_active' => true,
                    'sort_order' => $byKey->count(),
                ]));
            }
        }

        $result = $byKey->sortBy(fn (VariantSizeMedia $item) => sprintf('%010d:%s', (int) $item->sort_order, mb_strtolower($item->size_label)))->values()->map(function (VariantSizeMedia $item) {
            $data = $item->toArray();
            if (!empty($data['image_url']) && Str::startsWith($data['image_url'], '/')) {
                $data['image_url'] = url($data['image_url']);
            }
            return $data;
        });

        return response()->json(['ok' => true, 'sizes' => $result]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'size_label' => ['required', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'image_is_reference' => ['sometimes', 'boolean'],
        ]);

        $label = trim($validated['size_label']);
        if ($label === '') {
            throw ValidationException::withMessages(['size_label' => ['Size label cannot be empty.']]);
        }

        $media = VariantSizeMedia::updateOrCreate(
            ['size_key' => $this->sizeKey($label)],
            [
                'size_label' => $label,
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_active' => true,
                ...(array_key_exists('image_is_reference', $validated) ? ['image_is_reference' => (bool) $validated['image_is_reference']] : []),
            ]
        );

        return response()->json(['ok' => true, 'size' => $this->serialise($media)]);
    }

    public function uploadImage(Request $request, string $sizeKey): JsonResponse
    {
        $validated = $request->validate([
            'size_label' => ['required', 'string', 'max:100'],
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'image_is_reference' => ['sometimes', 'boolean'],
        ]);

        $label = trim($validated['size_label']);
        $normalisedKey = $this->sizeKey($label);
        if ($normalisedKey !== $sizeKey) {
            throw ValidationException::withMessages(['size_label' => ['Size key does not match the supplied label.']]);
        }

        $media = VariantSizeMedia::firstOrNew(['size_key' => $sizeKey]);
        $oldPath = $this->storagePathFromUrl($media->image_url);
        $path = $request->file('image')->store('products/variants-by-size', 'public');
        $url = Storage::disk('public')->url($path);

        $media->fill([
            'size_label' => $label,
            'image_url' => $url,
            'image_is_reference' => array_key_exists('image_is_reference', $validated)
                ? (bool) $validated['image_is_reference']
                : false,
            'is_active' => true,
        ]);
        $media->save();

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json(['ok' => true, 'size' => $this->serialise($media)]);
    }

    public function deleteImage(string $sizeKey): JsonResponse
    {
        $media = VariantSizeMedia::where('size_key', $sizeKey)->firstOrFail();
        $oldPath = $this->storagePathFromUrl($media->image_url);

        $media->update([
            'image_url' => null,
            'image_is_reference' => false,
        ]);

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json(['ok' => true, 'size' => $this->serialise($media)]);
    }

    private function serialise(VariantSizeMedia $media): array
    {
        $data = $media->toArray();
        if (!empty($data['image_url']) && Str::startsWith($data['image_url'], '/')) {
            $data['image_url'] = url($data['image_url']);
        }
        return $data;
    }

    private function storagePathFromUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $marker = '/storage/';
        $position = strpos($path, $marker);
        if ($position === false) {
            return null;
        }
        return ltrim(substr($path, $position + strlen($marker)), '/');
    }

    private function sizeKey(string $label): string
    {
        $normalised = mb_strtolower(trim($label));
        $normalised = preg_replace('/\s+/u', '', $normalised) ?? $normalised;
        return $normalised;
    }
}
