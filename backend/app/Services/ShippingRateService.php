<?php

namespace App\Services;

class ShippingRateService
{
    public function __construct(private readonly StoreSettingsService $settings) {}

    private const CITY_ALIASES = [
        'دمشق' => 'damascus', 'damascus' => 'damascus',
        'حلب' => 'aleppo', 'aleppo' => 'aleppo',
        'حمص' => 'homs', 'homs' => 'homs',
        'اللاذقية' => 'latakia', 'latakia' => 'latakia',
        'طرطوس' => 'tartus', 'tartus' => 'tartus',
        'حماة' => 'hama', 'hama' => 'hama',
        'السويداء' => 'as-suwayda', 'as-suwayda' => 'as-suwayda',
        'درعا' => 'daraa', 'daraa' => 'daraa',
        'دير الزور' => 'deir ez-zor', 'deir ez-zor' => 'deir ez-zor',
        'الرقة' => 'raqqa', 'raqqa' => 'raqqa',
        'الحسكة' => 'al-hasakah', 'al-hasakah' => 'al-hasakah',
        'إدلب' => 'idlib', 'idlib' => 'idlib',
        'القنيطرة' => 'quneitra', 'quneitra' => 'quneitra',
    ];

    public function normalizeCity(string $city): string
    {
        $key = mb_strtolower(trim($city));
        if (!isset(self::CITY_ALIASES[$key])) {
            throw new \InvalidArgumentException('Unsupported delivery city.');
        }
        return self::CITY_ALIASES[$key];
    }

    public function getCityRates(): array
    {
        return $this->settings->getJson('shipping_city_rates', $this->settings->shippingDefaults());
    }

    public function getFreeShippingThreshold(): float
    {
        return max(0, $this->settings->getFloat('shipping_free_threshold', 5000));
    }

    public function calculate(string $city, float $subtotal): float
    {
        if ($subtotal < 0) throw new \InvalidArgumentException('Subtotal cannot be negative.');
        $normalized = $this->normalizeCity($city);
        $rates = $this->getCityRates();
        $rate = array_key_exists($normalized, $rates) ? (float) $rates[$normalized] : null;
        if ($rate === null || $rate < 0) throw new \InvalidArgumentException('Unsupported delivery city.');
        return round($subtotal > $this->getFreeShippingThreshold() ? 0.0 : $rate, 2);
    }

    public function calculateForOrder(string $city, float $subtotal): float
    {
        return $this->calculate($city, $subtotal);
    }
}
