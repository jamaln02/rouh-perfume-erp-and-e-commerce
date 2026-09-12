<?php

namespace App\Http\Controllers;

use App\Services\ShippingRateService;
use App\Services\StoreSettingsService;
use Illuminate\Http\JsonResponse;

class StorefrontConfigController extends Controller
{
    public function __construct(
        private readonly StoreSettingsService $settings,
        private readonly ShippingRateService $shipping,
    ) {}

    public function __invoke(): JsonResponse
    {
        $cityLabels = [
            ['key' => 'damascus', 'ar' => 'دمشق', 'en' => 'Damascus'],
            ['key' => 'aleppo', 'ar' => 'حلب', 'en' => 'Aleppo'],
            ['key' => 'homs', 'ar' => 'حمص', 'en' => 'Homs'],
            ['key' => 'latakia', 'ar' => 'اللاذقية', 'en' => 'Latakia'],
            ['key' => 'tartus', 'ar' => 'طرطوس', 'en' => 'Tartus'],
            ['key' => 'hama', 'ar' => 'حماة', 'en' => 'Hama'],
            ['key' => 'as-suwayda', 'ar' => 'السويداء', 'en' => 'As-Suwayda'],
            ['key' => 'daraa', 'ar' => 'درعا', 'en' => 'Daraa'],
            ['key' => 'deir ez-zor', 'ar' => 'دير الزور', 'en' => 'Deir ez-Zor'],
            ['key' => 'raqqa', 'ar' => 'الرقة', 'en' => 'Raqqa'],
            ['key' => 'al-hasakah', 'ar' => 'الحسكة', 'en' => 'Al-Hasakah'],
            ['key' => 'idlib', 'ar' => 'إدلب', 'en' => 'Idlib'],
            ['key' => 'quneitra', 'ar' => 'القنيطرة', 'en' => 'Quneitra'],
        ];

        $rates = $this->shipping->getCityRates();

        $cities = array_map(function (array $city) use ($rates): array {
            return [
                ...$city,
                'shipping' => (float) ($rates[$city['key']] ?? 0),
            ];
        }, $cityLabels);

        return response()->json([
            'shipping' => [
                'free_threshold' => $this->shipping->getFreeShippingThreshold(),
                'cities' => $cities,
            ],

            'loyalty' => [
                'enabled' => $this->settings->getBool('loyalty_enabled', true),
                'earn_amount' => $this->settings->getFloat('loyalty_earn_amount', 1000),
                'earn_points' => $this->settings->getInt('loyalty_earn_points', 1),
                'redeem_points' => $this->settings->getInt('loyalty_redeem_points', 100),
                'redeem_discount' => $this->settings->getFloat('loyalty_redeem_discount', 500),
            ],

            'quiz' => [
                'discount_percent' => $this->settings->getInt('quiz_discount_percent', 10),
            ],

            'social' => [
                'instagram' => 'rouh_.parfum',
            ],

            'offers' => [
                'title_ar' => (string) $this->settings->get(
                    'offers_title_ar',
                    'العروض والمجموعات'
                ),
                'title_en' => (string) $this->settings->get(
                    'offers_title_en',
                    'Bundles & Offers'
                ),
                'subtitle_ar' => (string) $this->settings->get(
                    'offers_subtitle_ar',
                    'وفر أكثر مع مجموعاتنا الحصرية'
                ),
                'subtitle_en' => (string) $this->settings->get(
                    'offers_subtitle_en',
                    'Save more with our exclusive bundles'
                ),
            ],
        ]);
    }
}