<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * InventoryImportSeeder
 *
 * Seeds the database with the real inventory provided by the owner:
 *   - Perfume oils  → materials (material_category = perfume_oil)
 *   - Musk oils     → materials (material_category = perfume_oil, subcategory = musk)
 *   - Alcohol       → materials (material_category = alcohol)
 *   - Bottles/boxes → materials (material_category = packaging)
 *   - Equipment     → fixed_assets (depreciation-tracked operational tools)
 *   - Finished perfumes → products + product_variants + finished_products_inventory
 *
 * All prices are in SYP (Syrian Pounds) as given in the inventory sheet.
 * Stock quantities use the unit specified (grams, ml, pieces, etc.).
 */
class InventoryImportSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seeding real inventory…');

        // Ensure categories exist (the CategorySeeder already ran, but be safe).
        $catMen    = $this->ensureCategory('men',    'Men',    'رجالي');
        $catWomen  = $this->ensureCategory('women',  'Women',  'نسائي');
        $catUnisex = $this->ensureCategory('unisex', 'Unisex', 'للجنسين');

        // 1) Perfume oils & musks  → materials table
        $this->seedPerfumeOils();

        // 2) Alcohol → materials table
        $this->seedAlcohol();

        // 3) Packaging (bottles, boxes, testers) → materials table
        $this->seedPackaging();

        // 4) Equipment (syringes, presses, shelves) → fixed_assets table
        $this->seedEquipment();

        // 5) Finished perfumes → products + variants + inventory
        $this->seedFinishedProducts($catMen, $catWomen, $catUnisex);

        // Deliberately do not create/post an opening balance here.
        // This seeder is for local catalog/demo data only; opening balances are
        // entered and posted manually through the controlled admin workflow.

        $this->command?->info('✓ Real inventory seeded successfully.');
    }

    /* -----------------------------------------------------------------
     |  Helpers
     |---------------------------------------------------------------- */

    private function ensureCategory(string $slug, string $name, string $nameAr): string
    {
        $cat = DB::table('categories')->where('slug', $slug)->first();
        if ($cat) {
            return $cat->id;
        }
        $id = (string) Str::uuid();
        DB::table('categories')->insert([
            'id'         => $id,
            'name'       => $name,
            'name_ar'    => $nameAr,
            'slug'       => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $id;
    }

    /**
     * Insert a material if it doesn't already exist (matched by name_ar).
     * Returns the material id.
     */
    private function upsertMaterial(array $data): int
    {
        $existing = DB::table('materials')
            ->where('name_ar', $data['name_ar'])
            ->orWhere('name', $data['name'])
            ->first();

        if ($existing) {
            // Idempotent catalog seeding must NEVER erase live inventory/cost balances.
            // Opening inventory and subsequent stock movements own these fields.
            DB::table('materials')->where('id', $existing->id)->update([
                'supplier_name'  => $data['supplier_name'] ?? DB::raw('supplier_name'),
                'min_stock'      => $data['min_stock'] ?? DB::raw('min_stock'),
                'is_active'      => true,
                'updated_at'     => now(),
            ]);
            return $existing->id;
        }

        // New master records intentionally start at zero; opening stock is posted
        // through the controlled opening-balance workflow.
        $data['current_stock'] = 0;
        $data['avg_unit_cost'] = 0;
        return DB::table('materials')->insertGetId(array_merge($data, [
            'currency'        => $data['currency']        ?? 'SYP',
            'exchange_rate'   => $data['exchange_rate']   ?? 1,
            'track_fractional'=> $data['track_fractional']?? true,
            'is_active'       => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]));
    }

    /* -----------------------------------------------------------------
     |  1) Perfume oils & musks
     |---------------------------------------------------------------- */

    private function seedPerfumeOils(): void
    {
        // [name_ar, name_en, size_label, price_per_gram, supplier, remaining]
        // price_per_gram is the "سعر الجرام" from the sheet.
        $oils = [
            ['فلامينجو رامون مونيجال',     'Flamingo Ramon Monegal',      '100g',  21.6783, 'الغبرة',    87],
            ['مانسيرا روز فانيل',           'Mancera Rose Vanille',         '100g',  11.8735, 'الغبرة',    18],
            ['الوسام الرصاصي',              'Lead Medal',                   '100g',  17.3426, 'الغبرة',    69],
            ['بلاك كود ارماني',             'Armani Black Code',            '100g',  10.48,   'الغبرة',    99],
            ['ميراكل جاردن',                'Miracle Garden',               '100g',  20.19,   'الغبرة',    95],
            ['بينك شيفون',                  'Pink Chiffon',                 '100g',  7.4,     'أهل الثقة', 100],
            ['انفكتوس فيكتوري الكسير',      'Invictus Victory Elixir',      '100g',  9.67,    'أهل الثقة', 385],
            ['رالف لورين',                  'Ralph Lauren',                 '100g',  7.14,    'أهل الثقة', 55],
            ['جادور',                       'J\'adore',                     '100g',  12.4,    'أهل الثقة', 100],
            ['انا والشوق',                  'Ana Walshouq',                 '100g',  7.01,    'أهل الثقة', 110],
            ['عود مضاوي العربية للعود',     'Maddawi Oud Arabian Oud',      '100g',  16.8,    'أهل الثقة', 165],
            ['وصال',                        'Wisal',                        '100g',  13.73,   'أهل الثقة', 130],
            ['بلاك اكس اس رجالي باكوربان',  'Black XS Men Paco Rabanne',    '100g',  8.4,     'أهل الثقة', 100],
            ['تراثي بلو افنان',             'Turathi Blue Afnan',           '100g',  17.5,    'الغبرة',    63],
            ['افتر نون سويم',               'Afternoon Swim',               '100g',  17.5,    'الغبرة',    99],
            ['ميدنايت بريتني سبيرز',        'Midnight Britney Spears',      '100g',  10.13,   'الغبرة',    70],
            ['مون بلان ليجيند',             'Mont Blanc Legend',            '100g',  17.5,    'الغبرة',    92],
            ['اسكادا مغنتزيم',              'Escada Magnetism',             '100g',  16.2,    'الغبرة',    92],
            ['ايماجينيشن لوي فيتون',        'Imagination Louis Vuitton',    '100g',  27.0,    'الغبرة',    28],
            ['بربري هير',                   'Burberry Her',                 '100g',  16.2,    'الغبرة',    52],
            ['لافي بيل لانكوم',             'La Vie Est Belle Lancome',     '100g',  17.5,    'الغبرة',    100],
            ['لومال الكسير',                'L\'Homme Elixir',              '100g',  17.5,    'الغبرة',    83],
            ['اربيان تونكا مونتال',         'Arabian Tonka Montale',        '100g',  29.7,    'الغبرة',    80],
            ['كريد سيلفر ماونتن',           'Creed Silver Mountain',        '100g',  20.25,   'الغبرة',    86],
            ['ازارو وانتد',                 'Azzaro Wanted',                '100g',  17.5,    'الغبرة',    81],
            ['لوف اذ هفنلي',                'Love Is Heavenly',             '100g',  11.5,    'الغبرة',    75],
            ['ايربابورا زيرجوف',            'Erba Pura Xerjoff',            '150g',  18.0,    'الغبرة',    122],
            ['الثائر دو مارلي',             'Laytha Pegasus Marly',         '100g',  16.2,    'الغبرة',    100],
            ['بلاك اوبيوم اف سان لوران',    'Black Opium YSL',              '100g',  12.15,   'الغبرة',    97],
            ['سوسبيرو اكسنتو',              'Sospiro Accento',              '100g',  25.26,   'الغبرة',    60],
            ['اسكادا تاج سانسيت',           'Escada Tag Sunset',            '100g',  15.53,   'الغبرة',    96],
            ['في سكستين باور',              'VI Sixteen Power',             '100g',  18.9,    'الغبرة',    42],
            ['سانتال شام',                  'Santal Cham',                  '100g',  18.9,    'الغبرة',    100],
            ['مسك توت',                     'Musk Tout',                    '50g',   13.5,    'أهل الثقة', 29],
            ['غبار الذهب',                  'Gold Dust',                    '50g',   6.4,     'أهل الثقة', 50],
            ['فنتازيا بريتني سبيرز',        'Fantasy Britney Spears',       '50g',   6.0,     'أهل الثقة', 50],
            ['الترميل',                     'Tremille',                     '100g',  9.6,     'أهل الثقة', 80],
            ['جود جيرل',                    'Good Girl',                    '100g',  9.3,     'أهل الثقة', 118],
            ['لاكوست وايت',                 'Lacoste White',                '50g',   8.91,    'أهل الثقة', 50],
            ['لاكوست بلاك',                 'Lacoste Black',                '50g',   8.91,    'أهل الثقة', 50],
            ['سكاندل باي نايت',             'Scandal By Night',             '100g',  11.88,   'أهل الثقة', 75],
            ['بلو فور مان',                 'Blue For Man',                 '50g',   8.91,    'أهل الثقة', 50],
            ['بلو دو شانيل',                'Bleu de Chanel',               '50g',   8.91,    'أهل الثقة', 44],
            ['تشامبيون دافيدوف',            'Champion Davidoff',            '50g',   7.3,     'أهل الثقة', 42],
            ['كلمات العربية للعود',         'Kalimat Arabian Oud',          '50g',   10.13,   'أهل الثقة', 37],
            ['سيلفر سنت',                   'Silver Cent',                  '50g',   7.02,    'أهل الثقة', 50],
            ['سبايس بومب اكستريم',          'Spice Bomb Extreme',           '50g',   10.53,   'أهل الثقة', 50],
            ['اكوا دي جيو',                 'Acqua Di Gio',                 '50g',   15.12,   'أهل الثقة', 50],
            ['فيرزاتشي ايروس',              'Versace Eros',                 '100g',  10.4,    'أهل الثقة', 65],
            ['212 في اي بي رجالي',          '212 VIP Men',                  '100g',  11.5,    'أهل الثقة', 70],
            ['212 في اي بي نسائي',          '212 VIP Women',                '100g',  7.0,     'أهل الثقة', 99],
            ['212 سكسي رجالي',              '212 Sexy Men',                 '100g',  16.0,    'أهل الثقة', 100],
            ['212 سكسي نسائي',              '212 Sexy Women',               '100g',  9.8,     'أهل الثقة', 90],
            ['نيرسيسو رودريغيز',            'Narciso Rodriguez',            '100g',  12.42,   'أهل الثقة', 150],
            ['اولمبيا',                     'Olympia',                      '100g',  9.0,     'أهل الثقة', 63],
            ['باريس هيلتون',                'Paris Hilton',                 '100g',  8.4,     'أهل الثقة', 100],
            ['خمرة لطافة',                  'Khamra Latifa',                '100g',  15.55,   'أهل الثقة', 68],
            ['فيري سكسي ناو',               'Very Sexy Now',                '100g',  13.1,    'أهل الثقة', 96],
            ['ليدي ميليون',                 'Lady Million',                 '100g',  10.15,   'أهل الثقة', 100],
            ['انفكتوس فيكتوري',             'Invictus Victory',             '100g',  11.5,    'أهل الثقة', 100],
            ['سوفاج ديور',                  'Sauvage Dior',                 '100g',  9.5,     'أهل الثقة', 100],
            ['امبريال فالي قصة',            'Imperial Valley Story',        '100g',  15.55,   'أهل الثقة', 77],
            ['خمرة قهوة',                   'Khamra Coffee',                '100g',  13.2,    'أهل الثقة', 83],
            ['بلاك اوركيد توم فورد',        'Black Orchid Tom Ford',        '100g',  9.72,    'أهل الثقة', 100],
            ['امبر ليذر توم فورد',          'Amber Leather Tom Ford',       '100g',  20.0,    'أهل الثقة', 84],
            ['عود دبلوماسي',                'Diplomatic Oud',               '100g',  11.2,    'أهل الثقة', 100],
            ['هودسون فالي قصة',             'Hudson Valley Story',          '100g',  13.1,    'أهل الثقة', 69],
            ['بورن ان روما انتنس',          'Born In Roma Intense',         '50g',   17.0,    'أهل الثقة', 50],
            ['مسك باودر زيت',               'Musk Powder Oil',              '50g',   6.5,     'أهل الثقة', 50],
            ['مسك طهارة',                   'Musk Tahara',                  '150g',  9.2,     'أهل الثقة', 88],
            ['مسك كرز',                     'Musk Cherry',                  '50g',   8.9,     'أهل الثقة', 25],
            ['مسك اثارة',                   'Musk Athara',                  '50g',   10.0,    'أهل الثقة', 5],
            ['هامول',                       'Hamoul',                       '50g',   5.0,     'أهل الثقة', 45],
            ['مسك رمان',                    'Musk Pomegranate',             '50g',   8.1,     'أهل الثقة', 0],
            ['سكلبشر',                      'Sculpture',                    '50g',   15.0,    'أهل الثقة', 50],
            ['امبر نوماد لوي فيتون',        'Amber Nomad Louis Vuitton',    '50g',   40.0,    'أهل الثقة', 38],
            ['هاواي',                       'Hawaii',                       '50g',   7.0,     'أهل الثقة', 50],
            ['واي اف سان لوران',            'YSL Y',                        '50g',   11.6,    'أهل الثقة', 50],
            ['خيالي مارشميلو',              'Khayali Marshmallow',          '50g',   11.6,    'أهل الثقة', 45],
            ['توماس كاسامولا',              'Thomas Casamula',              '50g',   21.2,    'أهل الثقة', 50],
            ['ديور هوم انتنس',              'Dior Homme Intense',           '50g',   12.0,    'أهل الثقة', 20],
            ['استرونجر ويذ يو',             'Stronger With You',            '100g',  10.8,    'أهل الثقة', 87],
            ['ايدول',                       'Idole',                        '50g',   11.0,    'أهل الثقة', 50],
            ['بلاك ليكزس',                  'Black Lexus',                  '100g',  8.8,     'أهل الثقة', 90],
            ['سي باشن',                     'Sea Passion',                  '100g',  11.0,    'أهل الثقة', 100],
            ['ثري جي',                      '3G',                           '50g',   7.0,     'أهل الثقة', 42],
            ['ليبر',                        'Libre',                        '100g',  8.4,     'أهل الثقة', 75],
            ['دارج',                        'Darge',                        '100g',  13.0,    'أهل الثقة', 100],
            ['غبار الفضة',                  'Silver Dust',                  '50g',   6.4,     'أهل الثقة', 47],
            ['بكرات روج',                   'Bouquets Rouge',               '100g',  19.2,    'الغبرة',    77],
            ['فوياج',                       'Voyage',                       '50g',   12.05,   'الغبرة',    50],
            ['فهرنهايت',                    'Fahrenheit',                   '50g',   12.0,    'أهل الثقة', 45],
            ['مسك ابيض',                    'White Musk',                   '50g',   5.0,     'أهل الثقة', 42],
            ['هوجو بوس رجالي',              'Hugo Boss Men',                '50g',   13.0,    'أهل الثقة', 48],
            ['سيجار',                       'Cigar',                        '50g',   8.6,     'أهل الثقة', 50],
            ['ياسمين',                      'Jasmine',                      '50g',   6.0,     'أهل الثقة', 50],
            ['توكسيدو',                     'Tuxedo',                       '50g',   19.0,    'أهل الثقة', 50],
            ['غريس شارنيل',                 'Grace Chanel',                 '50g',   22.45,   'الغبرة',    50],
            ['استرونجر ويذ يو انتنسلي',     'Stronger With You Intensely',  '50g',   9.0,     'أهل الثقة', 50],
        ];

        foreach ($oils as $oil) {
            [$nameAr, $nameEn, $sizeLabel, $pricePerGram, $supplier, $remaining] = $oil;

            $isMusk = str_contains($nameAr, 'مسك') || str_contains($nameEn, 'Musk');

            $this->upsertMaterial([
                'code'              => 'OIL-' . Str::slug($nameEn, '-'),
                'name'              => $nameEn,
                'name_ar'           => $nameAr,
                'material_category' => 'perfume_oil',
                'subcategory'       => $isMusk ? 'musk' : 'perfume',
                'base_unit'         => 'g',
                'current_stock'     => $remaining,
                'min_stock'         => 10,
                'avg_unit_cost'     => $pricePerGram,
                'supplier_name'     => $supplier,
                'notes'             => "حجم: {$sizeLabel} — سعر الجرام: {$pricePerGram} ل.س",
            ]);
        }

        $this->command?->info('  ✓ ' . count($oils) . ' perfume oils & musks seeded');
    }

    /* -----------------------------------------------------------------
     |  2) Alcohol
     |---------------------------------------------------------------- */

    private function seedAlcohol(): void
    {
        // كحول ايثانول: 1000 مل، تكلفة 0.36 ل.س/مل، المتبقي 1000 مل
        $this->upsertMaterial([
            'code'              => 'ALC-ETHANOL-1L',
            'name'              => 'Ethanol Alcohol 1L',
            'name_ar'           => 'كحول ايثانول',
            'material_category' => 'alcohol',
            'subcategory'       => 'ethanol',
            'base_unit'         => 'mL',
            'track_fractional'  => true,
            'current_stock'     => 1000, // 1 litre opening quantity represented in millilitres; actual opening count is recorded later through the controlled opening-balance workflow
            'min_stock'         => 2,
            'avg_unit_cost'     => 0.36,  // per mL
            'supplier_name'     => 'أهل الثقة',
            'notes'             => 'إيثانول 96% لإنتاج العطور — أساس الوحدة mL، 1 L = 1000 mL، تكلفة الافتتاح 0.36 ل.س/mL',
        ]);

        $this->command?->info('  ✓ Alcohol seeded');
    }

    /* -----------------------------------------------------------------
     |  3) Packaging (bottles, boxes, testers)
     |---------------------------------------------------------------- */

    private function seedPackaging(): void
    {
        // [name_ar, name_en, unit, price_per_unit, supplier, remaining, subcategory]
        $packaging = [
            ['فواحة سيارات ماركات',          'Car Diffuser Branded',        'pcs', 370,  'أهل الثقة',     8, 'car_diffuser'],
            ['حنجور زيت 12 جرام',           'Oil Vial 12g',                 'pcs',   12.0,    'أهل الثقة',    13, 'vial'],
            ['حنجور زيت 6 جرام',            'Oil Vial 6g',                  'pcs',   10.0,    'أهل الثقة',    11, 'vial'],
            ['قلم تستر شفاف 10 مل',           'Clear Tester Pen 10ml',       'pcs', 11.75,'أهل الثقة',    24, 'tester'],
            ['زجاجة يم يم زهر مع صندوق',     'Yum Yum Flower Bottle+Box',   'pcs', 880,  'أهل الثقة',     1, 'bottle'],
            ['زجاجة يم يم ازرق صندوق فتح',   'Yum Yum Blue Bottle Open Box','pcs', 810,  'أهل الثقة',     1, 'bottle'],
            ['زجاجة كريستال دهن 3 مل',       'Crystal Bottle 3ml',          'pcs', 135,  'أهل الثقة',     3, 'bottle'],
            ['زجاجة زارا نسائي مربعة 30 مل', 'Zara Women Square 30ml',      'pcs', 54,   'أهل الثقة',     4, 'bottle'],
            ['زجاجة قلم ميل حمرا 5 مل',      'Red Pen Bottle 5ml',          'pcs', 40,   'أهل الثقة',     8, 'bottle'],
            ['زجاجة تيستر شفاف 5 مل',        'Clear Tester 5ml',            'pcs', 9,    'أهل الثقة',    43, 'tester'],
            ['زجاجة ليزر 15 مل',             'Laser Bottle 15ml',           'pcs', 79,   'أهل الثقة',     6, 'bottle'],
            ['زجاجة ليزر مذهبة 50 مل',       'Gold Laser Bottle 50ml',      'pcs', 3,    'أهل الثقة',     3, 'bottle'],
            ['زجاجة ليزر مذهبة 30 مل',       'Gold Laser Bottle 30ml',      'pcs', 1,    'أهل الثقة',     1, 'bottle'],
            ['زجاجة شفافة 100 مل',           'Clear Bottle 100ml',          'pcs', 12.05,'بحر',         164, 'bottle'],
            ['زجاجة شفافة 50 مل',            'Clear Bottle 50ml',           'pcs', 90,   'بحر',         420, 'bottle'],
            ['كيس روح كرتون',                'ROUH Paper Bag',              'pcs', 65,   'غير معروف',   390, 'bag'],
            ['علبة روح كرتون',               'ROUH Cardboard Box',          'pcs', 35,   'غير معروف',   431, 'box'],
            // Opening-count 250g bottles are packaging inventory, not PPE.
            ['فارغة زجاج 250 جرام',            'Empty Glass Bottle 250g',     'pcs', 150,  null,             1, 'bottle'],
            ['فارغة المنيوم 250 جرام',         'Empty Aluminum Bottle 250g', 'pcs', 130,  null,            60, 'bottle'],
        ];

        foreach ($packaging as $item) {
            [$nameAr, $nameEn, $unit, $price, $supplier, $remaining, $subcat] = $item;

            $this->upsertMaterial([
                'code'              => 'PKG-' . Str::slug($nameEn, '-'),
                'name'              => $nameEn,
                'name_ar'           => $nameAr,
                'material_category' => 'packaging',
                'subcategory'       => $subcat,
                'base_unit'         => $unit,
                'track_fractional'  => false,
                'current_stock'     => $remaining,
                'min_stock'         => 10,
                'avg_unit_cost'     => $price,
                'supplier_name'     => $supplier,
                'notes'             => null,
            ]);
        }

        $this->command?->info('  ✓ ' . count($packaging) . ' packaging items seeded');
    }

    /* -----------------------------------------------------------------
     |  4) Equipment (operational tools)
     |---------------------------------------------------------------- */

    private function seedEquipment(): void
    {
        // Equipment is not created as an owned asset during catalog seeding.
        // It is recognized only through the controlled opening-balance workflow.
        $this->command?->info('  ✓ Equipment catalog deferred to opening balance');
    }

    /* -----------------------------------------------------------------
     |  5) Finished products → products + variants + inventory
     |---------------------------------------------------------------- */

    private function seedFinishedProducts(string $catMen, string $catWomen, string $catUnisex): void
    {
        // For each perfume oil that has a recognizable brand, also create a
        // sellable "finished product" so it appears in the storefront.
        // We pick the ones with stock > 0 and a clear gender hint.
        //
        // [name_ar, name_en, gender, sizes_and_prices]
        // sizes_and_prices: ['50ml' => price, '100ml' => price, ...]
        // Price = base_price (we use the oil's gram price × a markup factor
        // to produce a reasonable selling price in SYP).
        $products = [
            ['فلامينجو رامون مونيجال',   'Flamingo Ramon Monegal',     'unisex', ['100ml' => 22000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQeooxqfea6RAAIaHq0apRI2zG3LTZNnQNWwPpuT3fWOw&s=10'],
            ['مانسيرا روز فانيل',         'Mancera Rose Vanille',       'women',  ['100ml' => 14000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTrFYtuO5speGzpo-Nu1NxxeLgwt9yJPzJFRtZF8YgOiQ&s=10'],
            ['الوسام الرصاصي',            'Lead Medal',                 'men',    ['100ml' => 19000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRx-sVszXVJG2Pgd6FO8CmhhAcmM3GlX8Mljs-vkQqzww&s=10'],
            ['بلاك كود ارماني',           'Armani Black Code',          'men',    ['100ml' => 12000], null],
            ['ميراكل جاردن',              'Miracle Garden',             'women',  ['100ml' => 22000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSooiS3dl7P7GbSrNigRLUfMPmAh6GpHI_Vv4c5Ysexog&s=10'],
            ['بينك شيفون',                'Pink Chiffon',               'women',  ['100ml' => 9000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTLLtnlLu6Zwn1QWYmdXVUewtnoM64KTig0BfaqSooBiA&s=10'],
            ['انفكتوس فيكتوري الكسير',    'Invictus Victory Elixir',    'men',    ['50ml' => 11000, '100ml' => 16000, '250ml' => 22000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRiT-kzYEWyhBN5Sk5nZgPhzBgJ8_ZYYHgM_1PhxaMWyQ&s=10'],
            ['رالف لورين',                'Ralph Lauren',               'unisex', ['50ml' => 8000, '100ml' => 12000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSY4Bw24T3niyO5viDWtw37UcIO_prIqR6xxj4Za_J0HA&s=10'],
            ['جادور',                     'J\'adore',                   'women',  ['100ml' => 14000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS3hlzb491gOCzHnu3fbd9Ypmj1asFLqxH8pm15GP8rJw&s=10'],
            ['انا والشوق',                'Ana Walshouq',               'unisex', ['100ml' => 8000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSrUJm2fQzp4JjNz-SCKTr19CZn5gf36cCwCA2fif9Ghg&s=10'],
            ['عود مضاوي العربية للعود',   'Maddawi Oud Arabian Oud',    'unisex', ['100ml' => 18000], null],
            ['وصال',                      'Wisal',                      'unisex', ['100ml' => 15000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRzeaqYRvLO7g54FP-_37S4P_WWjtGt-EvosuvBenXtXw&s=10'],
            ['بلاك اكس اس رجالي باكوربان','Black XS Men Paco Rabanne',  'men',    ['100ml' => 9500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRZPlutXGQQzhBJnRJsEocvyXJGfRiVu9h6ek9hmoXfeSfCL4meeKkQ0gO4&s=10'],
            ['تراثي بلو افنان',           'Turathi Blue Afnan',         'unisex', ['100ml' => 19000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTtO75y5keeLATbm_p3p5ENJdiguoHZ1FypOS0Yx8Dbfg&s=10'],
            ['افتر نون سويم',             'Afternoon Swim',             'unisex', ['100ml' => 19000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQBfhLtRuFkMNq1YCM4Lp1pYGQe2EJRAIM1vL29-t5FlQ&s=10'],
            ['ميدنايت بريتني سبيرز',      'Midnight Britney Spears',    'women',  ['100ml' => 11000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQHbTM4WTKMW-7I5X1L5eBbG6nkFukj4f7OYY3YJeAlGQ&s'],
            ['مون بلان ليجيند',           'Mont Blanc Legend',          'men',    ['100ml' => 19000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQduF5uKBx5bjEqFhjErMq_XLkLOhY31UUTfMaG93X6sw&s'],
            ['اسكادا مغنتزيم',            'Escada Magnetism',           'women',  ['100ml' => 17000], null],
            ['ايماجينيشن لوي فيتون',      'Imagination Louis Vuitton',  'unisex', ['100ml' => 28000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSHwL6P5dsgt7KWUdzTn1mLJSLFVBI7qH3aoXcwyfNGXw&s'],
            ['بربري هير',                 'Burberry Her',               'women',  ['100ml' => 17000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR7soDzBQCYJfOowDqKg1lGSQJoMGxt6ZGES5ieCRnpLg&s=10'],
            ['لافي بيل لانكوم',           'La Vie Est Belle Lancome',   'women',  ['100ml' => 19000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS8PMqCcit8FliHR_8M2AZrdPn1RgaXT92D3cM39FdE5A&s=10'],
            ['لومال الكسير',              'L\'Homme Elixir',            'men',    ['100ml' => 19000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSY-j0ob8NlvY2t_cMYS7rwnr4DFfGoCYUAmq2_17OsFg&s=10'],
            ['اربيان تونكا مونتال',       'Arabian Tonka Montale',      'unisex', ['100ml' => 30000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTz2kHUg4nArRLyy6Oj3vh7teMmqnAYMwMoXWDk_J7dLA&s=10'],
            ['كريد سيلفر ماونتن',         'Creed Silver Mountain',      'unisex', ['100ml' => 22000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTVN6_2sMQ3M7GFqWshx7wgzKl44msy9iHiiqS6K5negw&s=10'],
            ['ازارو وانتد',               'Azzaro Wanted',              'men',    ['100ml' => 19000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRADrqSTyiiUeQPT8iEsiFPqelG3ZLr-miDUqJPJ-LwIQ&s=10'],
            ['لوف اذ هفنلي',              'Love Is Heavenly',           'women',  ['100ml' => 12500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQWGiYu1dGd9_fkLC3YpqLURqUCts9j-Zb_inD6zs2QEg&s=10'],
            ['ايربابورا زيرجوف',         'Erba Pura Xerjoff',          'unisex', ['150ml' => 27000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTdG34UjbVvKwrSBjf2qlylL4B3i10VL4pENSWDh2rlVA&s=10'],
            ['الثائر دو مارلي',           'Laytha Pegasus Marly',       'unisex', ['100ml' => 17000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT6-bFCWxMJk2HdBZpdc-gVtoI6qaY_-IENrJ4rr1Bljg&s=10'],
            ['بلاك اوبيوم اف سان لوران',  'Black Opium YSL',            'women',  ['100ml' => 13500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRGfGwEfNZBAeqPyivL8Pu_ChDLqD1zpFDC4QzgOfhGVw&s=10'],
            ['سوسبيرو اكسنتو',            'Sospiro Accento',            'unisex', ['100ml' => 27000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRt670qwg5Cb-J62jPvy7QfXzsoudPGa5KWxIcS4yInhg&s=10'],
            ['اسكادا تاج سانسيت',         'Escada Tag Sunset',          'women',  ['100ml' => 16000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSm9qE8ZTHT_ffdEC70rkhJByKMko4q_gPbLBIH4W2YQA&s=10'],
            ['في سكستين باور',            'VI Sixteen Power',           'unisex', ['100ml' => 20000], null],
            ['سانتال شام',                'Santal Cham',                'unisex', ['100ml' => 20000], null],
            ['فنتازيا بريتني سبيرز',      'Fantasy Britney Spears',     'women',  ['50ml' => 7000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT817Tau_TzXz-HunlKdgjfXcJk5dbzcDQLyblpYAs3Fg&s=10'],
            ['الترميل',                   'Tremille',                   'unisex', ['100ml' => 10500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTCBDeIOY8LL1RCQtu-FUDn087nb3d4nAEtrXWACIdRFw&s'],
            ['جود جيرل',                  'Good Girl',                  'women',  ['100ml' => 10000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR8kotU8RsIM6RaHGp_lJawh9Z5g4pvnQe0nHZUT3ASow&s=10'],
            ['لاكوست وايت',               'Lacoste White',              'men',    ['50ml' => 9500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSQWZ0gjJn2BtaOE65pnEJwciNf2LWTG3ht06MxX1jBCg&s=10'],
            ['لاكوست بلاك',               'Lacoste Black',              'men',    ['50ml' => 9500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRT-0kT-nqRB7ltm2wDwCVQ8PB5e2tUP2Yl3frBjXbDOw&s=10'],
            ['سكاندل باي نايت',           'Scandal By Night',           'women',  ['100ml' => 13000], null],
            ['بلو فور مان',               'Blue For Man',               'men',    ['50ml' => 9500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQbhL-DTIHqSqj4PgcwHCYvxLBXWDsFgeolFC7V62QVqg&s=10'],
            ['بلو دو شانيل',              'Bleu de Chanel',             'men',    ['50ml' => 9500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQ6LhqJX5RukO4izulinWO2bouVYYeTBsdLsqAtQE7I_YIhsYxn8kt-T7Qa&s=10'],
            ['تشامبيون دافيدوف',          'Champion Davidoff',          'men',    ['50ml' => 8000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSmLolUl0_V9JjKghM4dR0d-Or4DYmuejKQtuP019OXpg&s'],
            ['كلمات العربية للعود',       'Kalimat Arabian Oud',        'unisex', ['50ml' => 11000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQwI_qiINGgVD3ekHsoO1N-eNw22LDRlbarTWvSttzE1w&s=10'],
            ['سيلفر سنت',                 'Silver Cent',                'men',    ['50ml' => 7500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQQgC3caXErZr90FQyXe5pqW7fKC-IPhKjJ5pyjxZBY8w&s'],
            ['سبايس بومب اكستريم',        'Spice Bomb Extreme',         'men',    ['50ml' => 11500], null],
            ['اكوا دي جيو',               'Acqua Di Gio',               'men',    ['50ml' => 16000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRKUSsoTGtuLU1bs0e8hWlZ1DSYQwCX2kONNxkMTDs58A&s=10'],
            ['فيرزاتشي ايروس',            'Versace Eros',               'men',    ['100ml' => 11500], null],
            ['212 في اي بي رجالي',        '212 VIP Men',                'men',    ['50ml' => 12500, '100ml' => 13000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQCucDGcqaC1EWaoXAGrq9V0Q4oEeXyttmj34qDQKfuwA&s=10'],
            ['212 في اي بي نسائي',        '212 VIP Women',              'women',  ['100ml' => 8000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQxcHNti7ZJj8s3FbbIZRGYnX_E4g1OlfbZayANp7ynbQ&s=10'],
            ['212 سكسي رجالي',            '212 Sexy Men',               'men',    ['100ml' => 17000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQtyTNsbNiGzsd_LUyEguYMEAOvGwNo7L_Gk4IlYYQDOA&s=10'],
            ['212 سكسي نسائي',            '212 Sexy Women',             'women',  ['100ml' => 10500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRdrFUnoPX74-G03qjl70jXFH1l1x0LaK_wnpof--FSk-9K791g6kPuir8&s=10'],
            ['نيرسيسو رودريغيز',          'Narciso Rodriguez',          'unisex', ['100ml' => 13500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQgIbkB-wu6iI13tR_epKiaBOTq1wDb8bjmOKjKKMrw3A&s=10'],
            ['اولمبيا',                   'Olympia',                    'women',  ['100ml' => 10000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRaMo5NEF0-oS718OpkDjHeUwpAgYcGoyeWa8yrBMIg9w&s=10'],
            ['باريس هيلتون',              'Paris Hilton',               'women',  ['100ml' => 9000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSgFwwVyrx8yaVlLWRstImAsVxec5Y2o3ShMWvZA5aMcA&s=10'],
            ['خمرة لطافة',                'Khamra Latifa',              'unisex', ['100ml' => 16500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS2a_BYOu0gMFKYsx18nKVYeV4_uasalV6SQEcOtQFO1A&s=10'],
            ['فيري سكسي ناو',             'Very Sexy Now',              'women',  ['100ml' => 14000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR9IlTQoX5VeTyItZPkWJB4S3pj0Jih5hl0JFJtVFwDSg&s=10'],
            ['ليدي ميليون',               'Lady Million',               'women',  ['100ml' => 11000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQ3BNW5qpdmexWzRLEMNrpS03WAD3LI64gWRPlcG0yzPw&s=10'],
            ['انفكتوس فيكتوري',           'Invictus Victory',           'men',    ['100ml' => 12500], null],
            ['سوفاج ديور',                'Sauvage Dior',               'men',    ['100ml' => 10500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQ0pWahUT_qLbCCdn0IOd0sL9tRZ2ZqoPEwLB4gs6t47A&s=10'],
            ['امبريال فالي قصة',          'Imperial Valley Story',      'unisex', ['100ml' => 16500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQdEp6SiFfhYJLtRQol2roER_GrcYWBZbDsYDsuYYVeuQ&s=10'],
            ['خمرة قهوة',                 'Khamra Coffee',              'unisex', ['100ml' => 14000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS39WynnTZT07dOB7E3TLiUMHi7kEt_M1jUIJQDAC4vqA&s=10'],
            ['بلاك اوركيد توم فورد',      'Black Orchid Tom Ford',      'unisex', ['100ml' => 10500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR0sFcY4auH8dR3w9XfShHT9XC6XcOHhG50QlFXJBfm6Q&s=10'],
            ['امبر ليذر توم فورد',        'Amber Leather Tom Ford',     'unisex', ['100ml' => 21000], 'https://orisdi.com/cdn/shop/files/CATHYDOLLWHITETOFUBODYBATHCLEANSER-2025-09-30T101640.935_800x.jpg?v=1759230961'],
            ['عود دبلوماسي',              'Diplomatic Oud',             'unisex', ['100ml' => 12000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR0dZ6lowMXBLHN0JBP22MhOc9nHlpZf-6S8cULczpjRA&s=10'],
            ['هودسون فالي قصة',           'Hudson Valley Story',        'unisex', ['100ml' => 14000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTKWcDaDCqcSJ9LLUcRat2EnNRx2QHA0EykjQx7gRm9jQ&s=10'],
            ['بورن ان روما انتنس',        'Born In Roma Intense',       'men',    ['50ml' => 18000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTS6uguOjns9FnxUd2WJsQ-tD5LVI7IYYQrnudlnVSiyw&s=10'],
            ['سكلبشر',                    'Sculpture',                  'unisex', ['50ml' => 16000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR4AOlmkIZDa2E2PN4PfMr3uhy7Q_C7CMjebVlpAmgn3Q&s=10'],
            ['امبر نوماد لوي فيتون',      'Amber Nomad Louis Vuitton',  'unisex', ['50ml' => 42000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS1gJe0i_VNL4sN5JFDPLaBsOrX7mxA-sST7utUQ0mlfQ&s=10'],
            ['هاواي',                     'Hawaii',                     'unisex', ['50ml' => 7500], null],
            ['واي اف سان لوران',          'YSL Y',                      'men',    ['50ml' => 12500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRtoXy9bmQJMIp8abNF8JB5n6dFzIdIi0K1uw-oW2stfg&s=10'],
            ['خيالي مارشميلو',            'Khayali Marshmallow',        'unisex', ['50ml' => 12500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTdRwfjEuWt6pQ6qa9yNkVaPBDofkYY0qyK4ZcNZtKynA&s=10'],
            ['توماس كاسامولا',            'Thomas Casamula',            'unisex', ['50ml' => 22000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRoxj8NN6_X-vb_-x32jIYzaRT5RPweD0RV-XAehwJZig&s=10'],
            ['ديور هوم انتنس',            'Dior Homme Intense',         'men',    ['50ml' => 13000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSHrJNQdzn7MoXKn-K43X-zuwoF19kgLg6RF51YpHnk0A&s=10'],
            ['استرونجر ويذ يو',           'Stronger With You',          'men',    ['100ml' => 11500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQRFniaJGeWfHZnvFpo-rkJuh3Mcuu_j7JeXSNQFp34rw&s=10'],
            ['ايدول',                     'Idole',                      'women',  ['50ml' => 12000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR2emgnnT0g06wf2vtdwsAJTNjiVtiXSJhzrqePDNcRM3GCGWohGk23EZEp&s=10'],
            ['بلاك ليكزس',                'Black Lexus',                'men',    ['100ml' => 9500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSTl_heydo0K0vD5GPn60xlKx7Bmj_YsbipM6yUKAtLaQ&s=10'],
            ['سي باشن',                   'Sea Passion',                'unisex', ['100ml' => 12000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQHZGvJ3_4YoIF0naZSPwduX-4qD0-_gTNuUmOxTLa7-Q&s=10'],
            ['ثري جي',                    '3G',                         'unisex', ['50ml' => 7500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRAuxBvNIrXt_7AjPx9WsJnMVkWIcGw1JG6wZ721GjwTQ&s=10'],
            ['ليبر',                      'Libre',                      'women',  ['100ml' => 9000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRl_sKYxb3yo6cq2xUNUebX4OW5fUDhfHIugMrCwngLZA&s=10'],
            ['دارج',                      'Darge',                      'unisex', ['100ml' => 14000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSMQLJkCf1E49DlcqPP27mB9gRmDAMzHKu5C76rM5UFWQ&s=10'],
            ['غبار الفضة',                'Silver Dust',                'unisex', ['50ml' => 7000], null],
            ['بكرات روج',                 'Bouquets Rouge',             'women',  ['100ml' => 20000], 'https://orisdi.com/cdn/shop/products/Untitleddesign-2022-07-20T164432.759.jpg?v=1658324684'],
            ['فوياج',                     'Voyage',                     'unisex', ['50ml' => 13000], 'https://cdn.mart.ps/204259-thickbox_default/nautica-voyage-perfume.jpg'],
            ['فهرنهايت',                  'Fahrenheit',                 'men',    ['50ml' => 13000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQ8zCPewetfvbh-U-JpNy6PBafZZjM4IozdcjOv5wxeoA&s=10'],
            ['مسك ابيض',                  'White Musk',                 'unisex', ['50ml' => 5500], 'https://qatar.afnan.com/cdn/shop/files/Abiyad-Musk-CPO-Product-02.jpg?v=1728582727&width=1080'],
            ['هوجو بوس رجالي',            'Hugo Boss Men',              'men',    ['50ml' => 14000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQkA54JXxWmCQib33abgIUgcdUU9em7V5f4RsGjXcY-eQ&s=10'],
            ['سيجار',                     'Cigar',                      'men',    ['50ml' => 9500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSR38ohmTSCzIFze-vXP7BYAmdN0sHKdl69mcB0Zi3knN-x78usLFXeOOI&s=10'],
            ['توكسيدو',                   'Tuxedo',                     'unisex', ['50ml' => 20000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQ6IQ1wTYhX1bYcbyLc8AMt5TQMg35eGX5WCzRy56dMMPRVF2jTR4QXPmU&s=10'],
            ['غريس شارنيل',               'Grace Chanel',               'women',  ['50ml' => 23000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSQCcCrgaDfUF3lHsL-iLASw2pDe9NtXcrhHOvKQki1LQ&s=10'],
            ['استرونجر ويذ يو انتنسلي',   'Stronger With You Intensely','men',    ['50ml' => 10000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTAFFtobOuxgSKxwJ7FQUaS8M2L5FkOxdh2BxAuHsN-Mg&s=10'],
            // Musk products (sellable)
            ['مسك توت',                   'Musk Tout',                  'unisex', ['50ml' => 14000], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQPgSyXEOSdQGe9HbjxuIVwCUKl1bis0qMj7K7G-puXLg&s=10'],
            ['مسك باودر زيت',             'Musk Powder Oil',            'unisex', ['50ml' => 7000], 'https://media.zid.store/cdn-cgi/image/w=750,q=90,f=auto/https://media.zid.store/thumbs/c6e2c941-1196-44b9-b86a-849b92a01a48/b0935d4c-3083-4da7-8e5a-be72143'],
            ['مسك طهارة',                 'Musk Tahara',                'unisex', ['150ml' => 9500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSSX7iCoK84OptFMznbGtxyD4H7PkJUQg9J4u5rXOAUIA&s=10'],
            ['مسك كرز',                   'Musk Cherry',                'unisex', ['50ml' => 9500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQyzEW9XPcCtYWLXv_9-9RKini95T_HAKfKjHHg2MSco8huMDdC-tCUO5sQ&s=10'],
            ['مسك اثارة',                 'Musk Athara',                'unisex', ['50ml' => 10500], null],
            ['هامول',                     'Hamoul',                     'unisex', ['50ml' => 5500], null],
            ['مسك رمان',                  'Musk Pomegranate',           'unisex', ['50ml' => 8500], 'https://cdn.salla.sa/mENzl/5acc2b67-27b6-42dd-82fa-aa81aaca1277-1000x1000-u78fSenuL9b5fUgZu6Jpfu4bwmYzvt9sIvDYlL6Z.png'],
            ['ياسمين',                    'Jasmine',                    'women',  ['50ml' => 6500], 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTr2Gd3rrFqHSG_qpKYMn7904_glIse6f2ioN-whnySgg&s=10'],
        ];

        $categoryIdMap = ['men' => $catMen, 'women' => $catWomen, 'unisex' => $catUnisex];
        $count = 0;

        foreach ($products as $prod) {
            [$nameAr, $nameEn, $gender, $sizePrices, $imageUrl] = array_pad($prod, 5, null);

            // Skip if product already exists
            $existing = DB::table('products')->where('name', $nameEn)->first();
            if ($existing) {
                continue;
            }

            $productId   = (string) Str::uuid();
            $categoryId  = $categoryIdMap[$gender] ?? $catUnisex;
            $sizes       = array_keys($sizePrices);
            $basePrice   = (float) reset($sizePrices);

            DB::table('products')->insert([
                'id'            => $productId,
                'name'          => $nameEn,
                'name_ar'       => $nameAr,
                'description'   => null,
                'description_ar'=> null,
                'price'         => $basePrice,
                'size_prices'   => json_encode($sizePrices),
                'image_url'     => $imageUrl ?: '/placeholder.svg',
                'category_id'   => $categoryId,
                'fragrance'     => 'oriental',
                'sizes'         => json_encode($sizes),
                'featured'      => false,
                'is_new'        => true,
                'best_seller'   => false,
                'stock'         => 0,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // Create variants for each size
            foreach ($sizePrices as $sizeLabel => $price) {
                $volumeMl = $this->extractVolumeMl($sizeLabel);

                $variantId = (string) Str::uuid();
                DB::table('product_variants')->insert([
                    'id'                    => $variantId,
                    'product_id'            => $productId,
                    'sku'                   => Str::slug($nameEn) . '-' . $sizeLabel,
                    'name'                  => $nameEn . ' ' . $sizeLabel,
                    'size_label'            => $sizeLabel,
                    'volume_ml'             => $volumeMl,
                    'selling_price_default' => $price,
                    'is_active'             => true,
                    'notes'                 => null,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);

                // finished_products_inventory — initial stock 0 (production fills it)
                DB::table('finished_products_inventory')->insert([
                    'product_id'      => $productId,
                    'size_type'       => $sizeLabel,
                    'current_stock'   => 0,
                    'min_stock'       => 5,
                    'cost_per_unit'   => 0,
                    'selling_price'   => $price,
                    'notes'           => null,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

            $count++;
        }

        $this->command?->info('  ✓ ' . $count . ' finished products with variants seeded');
    }

    /**
     * Extract numeric volume from a size label like "100ml", "50ml", "150ml".
     */
    private function extractVolumeMl(string $label): ?float
    {
        if (preg_match('/(\d+(?:\.\d+)?)/', $label, $m)) {
            return (float) $m[1];
        }
        return null;
    }
}
