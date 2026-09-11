<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StoreSettingsService
{
    public function get(string $key, mixed $default = null): mixed
    {
        $value = DB::table('store_settings')->where('key', $key)->value('value');
        return $value === null ? $default : $value;
    }

    public function getInt(string $key, int $default): int
    {
        return (int) $this->get($key, (string) $default);
    }

    public function getFloat(string $key, float $default): float
    {
        return (float) $this->get($key, (string) $default);
    }

    public function getBool(string $key, bool $default): bool
    {
        return filter_var($this->get($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOL);
    }

    public function getJson(string $key, array $default): array
    {
        $value = $this->get($key, null);
        if (!is_string($value) || trim($value) === '') return $default;
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $default;
    }

    public function set(string $key, mixed $value): void
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        } else {
            $value = (string) $value;
        }

        $exists = DB::table('store_settings')->where('key', $key)->exists();
        if ($exists) {
            DB::table('store_settings')->where('key', $key)->update([
                'value' => $value,
                'updated_at' => now(),
            ]);
            return;
        }

        DB::table('store_settings')->insert([
            'key' => $key,
            'value' => $value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function setMany(array $settings): void
    {
        DB::transaction(function () use ($settings): void {
            foreach ($settings as $key => $value) {
                $this->set((string) $key, $value);
            }
        });
    }

    public function shippingDefaults(): array
    {
        return [
            'damascus' => 250,
            'aleppo' => 80,
            'homs' => 50,
            'latakia' => 70,
            'tartus' => 70,
            'hama' => 60,
            'as-suwayda' => 50,
            'daraa' => 50,
            'deir ez-zor' => 100,
            'raqqa' => 100,
            'al-hasakah' => 120,
            'idlib' => 90,
            'quneitra' => 60,
        ];
    }
}
