<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * FragranceNotesSeeder
 *
 * Populates the top_notes, heart_notes, base_notes, and fragrance_family
 * columns for the ~96 ROUH perfumes. The note pyramids are compiled from
 * publicly available fragrance databases (Fragrantica, Basenotes) for the
 * well-known designer/niche scents, and from the brand's own published
 * descriptions for the regional/Arabian houses.
 *
 * Run after InventoryImportSeeder. It matches by the English product name
 * (name_en) stored in the products table.
 */
class FragranceNotesSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seeding fragrance notes…');

        $notes = $this->notesData();

        $updated = 0;
        foreach ($notes as $nameEn => $data) {
            $affected = DB::table('products')
                ->where('name', $nameEn)
                ->update([
                    'top_notes'        => json_encode($data['top']),
                    'heart_notes'      => json_encode($data['heart']),
                    'base_notes'       => json_encode($data['base']),
                    'fragrance_family' => $data['family'],
                    'updated_at'       => now(),
                ]);
            $updated += $affected;
        }

        $this->command?->info("  ✓ Fragrance notes set for {$updated} products");
    }

    /**
     * Returns an associative array keyed by English product name.
     * Each value has: top, heart, base (arrays of note names) and family.
     */
    private function notesData(): array
    {
        return [

            // ── Designer / Niche fragrances ────────────────────────────

            'Bleu de Chanel' => [
                'top' => ['Grapefruit', 'Lemon', 'Mint', 'Pink Pepper', 'Bergamot'],
                'heart' => ['Ginger', 'Nutmeg', 'Jasmine', 'Iso E Super'],
                'base' => ['Incense', 'Vetiver', 'Cedar', 'Sandalwood', 'Patchouli', 'Labdanum', 'White Musk'],
                'family' => 'Woody Aromatic',
            ],
            'Sauvage Dior' => [
                'top' => ['Calabrian Bergamot', 'Pepper'],
                'heart' => ['Sichuan Pepper', 'Lavender', 'Star Anise', 'Nutmeg'],
                'base' => ['Ambroxan', 'Cedar', 'Labdanum'],
                'family' => 'Aromatic Fougere',
            ],
            'Acqua Di Gio' => [
                'top' => ['Bergamot', 'Neroli', 'Green Tangerine', 'Sea Notes', 'Rosemary'],
                'heart' => ['Persimmon', 'Nutmeg', 'Cypress', 'Rose', 'Mastic'],
                'base' => ['Cedar', 'Patchouli', 'White Musk', 'Amber'],
                'family' => 'Aquatic Aromatic',
            ],
            'Versace Eros' => [
                'top' => ['Mint', 'Green Apple', 'Lemon'],
                'heart' => ['Tonka Bean', 'Geranium', 'Ambroxan'],
                'base' => ['Vanilla', 'Vetiver', 'Oakmoss', 'Cedar'],
                'family' => 'Oriental Woody',
            ],
            'Invictus Victory Elixir' => [
                'top' => ['Pink Pepper', 'Grapefruit', 'Bergamot'],
                'heart' => ['Lavender', 'Geranium', 'Oregano', 'Patchouli'],
                'base' => ['Vanilla', 'Amber', 'Cedar', 'Tonka Bean'],
                'family' => 'Oriental Spicy',
            ],
            'Invictus Victory' => [
                'top' => ['Pink Pepper', 'Mandarin Orange', 'Grapefruit'],
                'heart' => ['Lavender', 'Geranium', 'Patchouli'],
                'base' => ['Vanilla', 'Amber', 'Cedar'],
                'family' => 'Oriental Spicy',
            ],
            'Armani Black Code' => [
                'top' => ['Bergamot', 'Lemon', 'Basil', 'Mandarin Orange'],
                'heart' => ['Star Anise', 'Olive Blossom', 'Guaiac Wood'],
                'base' => ['Leather', 'Tobacco', 'Tonka Bean'],
                'family' => 'Oriental Spicy',
            ],
            'Black Opium YSL' => [
                'top' => ['Pink Pepper', 'Orange Blossom'],
                'heart' => ['Coffee', 'Jasmine', 'Bitter Almond'],
                'base' => ['Vanilla', 'Patchouli', 'Cedar', 'Cashmere Wood'],
                'family' => 'Oriental Vanilla',
            ],
            'Good Girl' => [
                'top' => ['Almond', 'Coffee'],
                'heart' => ['Tuberose', 'Jasmine Sambac', 'Orange Blossom'],
                'base' => ['Tonka Bean', 'Cacao', 'Sandalwood', 'Praline'],
                'family' => 'Oriental Floral',
            ],
            'La Vie Est Belle Lancome' => [
                'top' => ['Black Currant', 'Pear'],
                'heart' => ['Iris', 'Orange Blossom', 'Jasmine', 'Praline'],
                'base' => ['Patchouli', 'Vanilla', 'Tonka Bean', 'Praline'],
                'family' => 'Oriental Floral Gourmand',
            ],
            'Creed Silver Mountain' => [
                'top' => ['Bergamot', 'Mandarin Orange', 'Black Currant', 'Galbanum'],
                'heart' => ['Tea', 'Green Notes', 'Violet', 'Rose', 'Peppermint'],
                'base' => ['Musk', 'Sandalwood', 'Amber'],
                'family' => 'Aromatic Green',
            ],
            'Black Orchid Tom Ford' => [
                'top' => ['Black Currant', 'Tuberose', 'Ylang-Ylang', 'Bergamot'],
                'heart' => ['Black Orchid', 'Spices', 'Lotus', 'Jasmine'],
                'base' => ['Patchouli', 'Incense', 'Sandalwood', 'Vanilla', 'Balsam', 'Chocolate'],
                'family' => 'Oriental Floral',
            ],
            'Amber Leather Tom Ford' => [
                'top' => ['Cardamom', 'Leather', 'Black Tea'],
                'heart' => ['Sage', 'Jasmine', 'Cypriol'],
                'base' => ['Amber', 'Oud', 'Sandalwood', 'Patchouli'],
                'family' => 'Leather Amber',
            ],
            'Dior Homme Intense' => [
                'top' => ['Lavender', 'Sage', 'Bergamot', 'Cedar'],
                'heart' => ['Iris', 'Amber', 'Cacao'],
                'base' => ['Vanilla', 'Leather', 'Sandalwood', 'Vetiver', 'Patchouli'],
                'family' => 'Oriental Woody',
            ],
            'Fahrenheit' => [
                'top' => ['Violet Leaf', 'Honeysuckle', 'Hawthorn', 'Mandarin Orange'],
                'heart' => ['Cedar', 'Violet', 'Jasmine', 'Nutmeg', 'Cumin'],
                'base' => ['Leather', 'Vetiver', 'Sandalwood', 'Patchouli', 'Musk'],
                'family' => 'Woody Floral Musk',
            ],
            'Azzaro Wanted' => [
                'top' => ['Lemon', 'Ginger', 'Bergamot', 'Mint'],
                'heart' => ['Cardamom', 'Juniper', 'Apple', 'Cinnamon'],
                'base' => ['Tonka Bean', 'Vetiver', 'Amber', 'Benzoin'],
                'family' => 'Aromatic Spicy',
            ],
            'Mont Blanc Legend' => [
                'top' => ['Bergamot', 'Lavender', 'Pineapple', 'Lemon Verbena'],
                'heart' => ['Oakmoss', 'Geranium', 'Coumarin', 'Rose', 'Apple'],
                'base' => ['Sandalwood', 'Tonka Bean', 'Patchouli'],
                'family' => 'Aromatic Fougere',
            ],
            'Burberry Her' => [
                'top' => ['Strawberry', 'Raspberry', 'Blackberry', 'Bitter Orange', 'Mandarin Orange', 'Lemon', 'Pink Pepper', 'Bergamot'],
                'heart' => ['Violet', 'Jasmine', 'Peony', 'Lily', 'Black Currant', 'Pepper', 'Musk'],
                'base' => ['Vanilla', 'Musk', 'Oakmoss', 'Cedar', 'Cashmeran', 'Amberwood', 'Patchouli', 'Iso E Super'],
                'family' => 'Oriental Floral Gourmand',
            ],
            'Imagination Louis Vuitton' => [
                'top' => ['Calabrian Bergamot', 'Sicilian Orange', 'Neroli'],
                'heart' => ['Chinese Black Tea', 'Ambrette', 'Cypress'],
                'base' => ['Sandalwood', 'Grandifolium Cedar'],
                'family' => 'Aromatic Citrus',
            ],
            'Amber Nomad Louis Vuitton' => [
                'top' => ['Bergamot', 'Cinnamon', 'Carrot Seeds'],
                'heart' => ['Amber', 'Osmanthus', 'Immortelle'],
                'base' => ['Oud', 'Sandalwood', 'Leather'],
                'family' => 'Oriental Amber',
            ],
            'Erba Pura Xerjoff' => [
                'top' => ['Sicilian Orange', 'Calabrian Bergamot', 'Sicilian Lemon', 'Red Fruits'],
                'heart' => ['Orange Blossom', 'Jasmine', 'Bergamot', 'Pear'],
                'base' => ['Musk', 'Amber', 'Benzoin', 'Vanilla', 'Caramel', 'Honey'],
                'family' => 'Fruity Gourmand',
            ],
            'Sospiro Accento' => [
                'top' => ['Pineapple', 'Hyacinth', 'Bitter Orange', 'Tarragon'],
                'heart' => ['Iris', 'Jasmine', 'Peony', 'Musk'],
                'base' => ['Vanilla', 'Amber', 'Sandalwood', 'Patchouli', 'Musk'],
                'family' => 'Floral Fruity',
            ],
            'Arabian Tonka Montale' => [
                'top' => ['Bergamot', 'Saffron', 'Bitter Almond'],
                'heart' => ['Rose', 'Honey', 'Tonka Bean'],
                'base' => ['Oud', 'White Musk', 'Amber', 'Sandalwood'],
                'family' => 'Oriental Amber',
            ],
            'Spice Bomb Extreme' => [
                'top' => ['Black Pepper', 'Cinnamon', 'Grapefruit', 'Bergamot'],
                'heart' => ['Tobacco', 'Cumin', 'Lavender', 'Saffron'],
                'base' => ['Vanilla', 'Tonka Bean', 'Black Vanilla', 'Cedar'],
                'family' => 'Oriental Spicy',
            ],
            '212 VIP Men' => [
                'top' => ['Lime', 'Caviar', 'Pink Pepper', 'Ginger', 'Mint'],
                'heart' => ['Vodka', 'Frozen Mint', 'Spices'],
                'base' => ['Amber', 'Leather', 'Woody Notes'],
                'family' => 'Woody Spicy',
            ],
            '212 VIP Women' => [
                'top' => ['Passion Fruit', 'Rum', 'Grapefruit', 'Mandarin Orange'],
                'heart' => ['Gardenia', 'Jasmine', 'Pink Peppercorn'],
                'base' => ['Tonka Bean', 'Vanilla', 'Benzoin', 'Musk'],
                'family' => 'Floral Fruity Gourmand',
            ],
            '212 Sexy Men' => [
                'top' => ['Mandarin Orange', 'Bergamot', 'Green Pepper'],
                'heart' => ['Cardamom', 'Sandalwood', 'Pepper'],
                'base' => ['Vanilla', 'Musk', 'Guaiac Wood', 'Amber'],
                'family' => 'Oriental Woody',
            ],
            '212 Sexy Women' => [
                'top' => ['Mandarin Orange', 'Pink Pepper', 'Bergamot', 'Orange Blossom'],
                'heart' => ['Gardenia', 'Cotton Candy', 'Rose', 'Peony'],
                'base' => ['Vanilla', 'Musk', 'Sandalwood', 'Caramel'],
                'family' => 'Floral Oriental Gourmand',
            ],
            'Lady Million' => [
                'top' => ['Bitter Orange', 'Raspberry', 'Neroli'],
                'heart' => ['Orange Blossom', 'Jasmine', 'Gardenia', 'African Orange Flower'],
                'base' => ['Patchouli', 'Honey', 'Amber'],
                'family' => 'Floral Woody',
            ],
            'Scandal By Night' => [
                'top' => ['Honey', 'Bitter Orange'],
                'heart' => ['Tuberose', 'Orange Blossom', 'Nectarine', 'Gardenia'],
                'base' => ['Patchouli', 'Sandalwood', 'Tonka Bean', 'Caramel'],
                'family' => 'Oriental Floral Gourmand',
            ],
            'Olympia' => [
                'top' => ['Mandarin Orange', 'Green Notes', 'Water Jasmine'],
                'heart' => ['Ginger', 'Salt', 'Vanilla'],
                'base' => ['Ambergris', 'Cashmere Wood', 'Sandalwood'],
                'family' => 'Aquatic Oriental',
            ],
            'Narciso Rodriguez' => [
                'top' => ['White Rose', 'Peach'],
                'heart' => ['Musk', 'Amber', 'Patchouli'],
                'base' => ['Sandalwood', 'Vanilla', 'Musk'],
                'family' => 'Woody Musk',
            ],
            'Stronger With You' => [
                'top' => ['Cardamom', 'Pink Pepper', 'Mint', 'Bergamot'],
                'heart' => ['Cinnamon', 'Sage', 'Toffee'],
                'base' => ['Vanilla', 'Amber', 'Tonka Bean', 'Patchouli'],
                'family' => 'Oriental Spicy Gourmand',
            ],
            'Stronger With You Intensely' => [
                'top' => ['Pink Pepper', 'Juniper', 'Black Pepper'],
                'heart' => ['Lavender', 'Cinnamon', 'Toffee', 'Iris'],
                'base' => ['Vanilla', 'Amber', 'Tonka Bean', 'Suede', 'Patchouli'],
                'family' => 'Oriental Spicy Gourmand',
            ],
            'Born In Roma Intense' => [
                'top' => ['Bergamot', 'Pink Pepper', 'Ginger'],
                'heart' => ['Lavender', 'Jasmine', 'Iris'],
                'base' => ['Vanilla', 'Amber', 'Benzoin', 'Sandalwood'],
                'family' => 'Oriental Floral',
            ],
            'Libre' => [
                'top' => ['Lavender', 'Mandarin Orange', 'Bergamot', 'Neroli'],
                'heart' => ['Orange Blossom', 'Jasmine', 'Ginger'],
                'base' => ['Vanilla', 'Amber', 'Musk', 'Cedar'],
                'family' => 'Aromatic Fougere',
            ],
            'YSL Y' => [
                'top' => ['Bergamot', 'Ginger', 'Apple'],
                'heart' => ['Sage', 'Geranium', 'Juniper', 'Lavender'],
                'base' => ['Amberwood', 'Tonka Bean', 'Cedar', 'Vetiver', 'Olibanum'],
                'family' => 'Aromatic Fougere',
            ],
            'Lacoste White' => [
                'top' => ['Ruby Grapefruit', 'Bergamot', 'Pineapple', 'Cardamom'],
                'heart' => ['Lavender', 'Violet Leaves', 'Pepper', 'Sage'],
                'base' => ['Sandalwood', 'Vetiver', 'Amber', 'Cedar', 'Musk'],
                'family' => 'Woody Aromatic',
            ],
            'Lacoste Black' => [
                'top' => ['Water Mint', 'Lavender', 'Bergamot'],
                'heart' => ['Licorice', 'Black Cardamom', 'Coriander'],
                'base' => ['Amber', 'Patchouli', 'Leather', 'Tonka Bean', 'Oud'],
                'family' => 'Oriental Spicy',
            ],
            'Blue For Man' => [
                'top' => ['Bergamot', 'Marine Notes', 'Mint'],
                'heart' => ['Lavender', 'Clary Sage', 'Geranium'],
                'base' => ['Cedar', 'Amber', 'Musk', 'Sandalwood'],
                'family' => 'Aquatic Aromatic',
            ],
            'Champion Davidoff' => [
                'top' => ['Bergamot', 'Grapefruit', 'Galbanum'],
                'heart' => ['Lavender', 'Violet', 'Clary Sage'],
                'base' => ['Oakmoss', 'Leather', 'Cedar', 'Patchouli'],
                'family' => 'Woody Aromatic',
            ],
            'Silver Cent' => [
                'top' => ['Bergamot', 'Apple', 'Black Pepper'],
                'heart' => ['Lavender', 'Jasmine', 'Cardamom'],
                'base' => ['Amber', 'Cedar', 'Sandalwood', 'Musk'],
                'family' => 'Woody Aromatic',
            ],
            'Hugo Boss Men' => [
                'top' => ['Bergamot', 'Lemon', 'Green Apple', 'Mint'],
                'heart' => ['Lavender', 'Geranium', 'Cinnamon', 'Clove'],
                'base' => ['Sandalwood', 'Cedar', 'Vetiver', 'Olives', 'Musk'],
                'family' => 'Aromatic Fougere',
            ],
            'Cigar' => [
                'top' => ['Grapefruit', 'Mandarin Orange', 'Bergamot'],
                'heart' => ['Tobacco', 'Cinnamon', 'Cedar', 'Patchouli'],
                'base' => ['Leather', 'Amber', 'Tonka Bean', 'Vanilla'],
                'family' => 'Oriental Tobacco',
            ],
            'Idole' => [
                'top' => ['Pear', 'Bergamot', 'Pink Pepper'],
                'heart' => ['Rose', 'Jasmine', 'Muguet'],
                'base' => ['White Musk', 'Cedar', 'Vanilla', 'Patchouli'],
                'family' => 'Floral Woody',
            ],
            'J\'adore' => [
                'top' => ['Pear', 'Melon', 'Mandarin Orange', 'Bergamot', 'Peony'],
                'heart' => ['Tuberose', 'Jasmine', 'Lily-of-the-Valley', 'Rose', 'Freesia', 'Orchid', 'Plum', 'Violet'],
                'base' => ['Musk', 'Vanilla', 'Cedar', 'Blackberry', 'Sandalwood'],
                'family' => 'Floral Fruity',
            ],
            'Paris Hilton' => [
                'top' => ['Apple', 'Orange', 'Peach', 'Melon', 'Mimosa'],
                'heart' => ['Tuberose', 'Jasmine', 'Lily', 'Rose', 'Freesia', 'Lily-of-the-Valley'],
                'base' => ['Musk', 'Ylang-Ylang', 'Sandalwood', 'Oakmoss', 'Vetiver'],
                'family' => 'Floral Fruity',
            ],
            'Fantasy Britney Spears' => [
                'top' => ['Kiwi', 'Red Lychee', 'Quince'],
                'heart' => ['White Chocolate', 'Cupcake', 'Orchid', 'Jasmine', 'Water Lily'],
                'base' => ['Musk', 'Orris Root', 'Woodsy Notes'],
                'family' => 'Floral Gourmand',
            ],
            'Midnight Britney Spears' => [
                'top' => ['Plum', 'Black Cherry', 'Freesia', 'Vanilla Orchid'],
                'heart' => ['Night-Blooming Jasmine', 'Iris', 'Peony', 'Hawthorn'],
                'base' => ['Amber', 'Musk', 'Sandalwood', 'Vanilla', 'Cedar'],
                'family' => 'Floral Oriental',
            ],
            'Pink Chiffon' => [
                'top' => ['Pink Apple', 'Bergamot', 'Black Currant'],
                'heart' => ['Pink Jasmine', 'Peony', 'Rose'],
                'base' => ['Vanilla', 'Musk', 'Sandalwood'],
                'family' => 'Floral Fruity',
            ],
            'Miracle Garden' => [
                'top' => ['Red Fruits', 'Mandarin Orange', 'Bergamot'],
                'heart' => ['Rose', 'Jasmine', 'Peony', 'Lily'],
                'base' => ['Vanilla', 'Musk', 'Sandalwood', 'Amber'],
                'family' => 'Floral Woody',
            ],
            'Mancera Rose Vanille' => [
                'top' => ['Apple', 'Bergamot', 'Black Currant'],
                'heart' => ['Rose', 'Jasmine', 'Violet'],
                'base' => ['Vanilla', 'White Musk', 'Cedar', 'Amber'],
                'family' => 'Floral Vanilla',
            ],
            'Escada Magnetism' => [
                'top' => ['Melon', 'Basil', 'Red Berries', 'Mandarin Orange', 'Bergamot', 'Cassia'],
                'heart' => ['Rose', 'Jasmine', 'Heliotrope', 'Lily-of-the-Valley', 'Iris', 'Almond', 'Cinnamon', 'Carnation'],
                'base' => ['Vanilla', 'Patchouli', 'Sandalwood', 'Musk', 'Incense', 'Benzoin', 'Vetiver', 'Cashmeran'],
                'family' => 'Floral Oriental',
            ],
            'Escada Tag Sunset' => [
                'top' => ['Pear', 'Mandarin Orange', 'Bergamot'],
                'heart' => ['Orange Blossom', 'Jasmine', 'Rose'],
                'base' => ['Vanilla', 'Musk', 'Cedar', 'Amber'],
                'family' => 'Floral Fruity',
            ],
            'Very Sexy Now' => [
                'top' => ['Mango', 'Clementine', 'Pink Pepper'],
                'heart' => ['Coconut Nectar', 'Jasmine', 'Peony'],
                'base' => ['Vanilla', 'Musk', 'Sandalwood'],
                'family' => 'Floral Fruity Tropical',
            ],
            'Love Is Heavenly' => [
                'top' => ['Bergamot', 'Mandarin Orange', 'Lotus'],
                'heart' => ['Jasmine', 'Peony', 'Rose', 'Iris'],
                'base' => ['Musk', 'Vanilla', 'Sandalwood', 'Amber'],
                'family' => 'Floral Woody',
            ],
            'Grace Chanel' => [
                'top' => ['Bergamot', 'Aldehydes', 'Neroli'],
                'heart' => ['Jasmine', 'Rose', 'Lily-of-the-Valley', 'Freesia'],
                'base' => ['Musk', 'Vetiver', 'Cedar', 'Amber'],
                'family' => 'Floral Aldehydic',
            ],
            'Bouquets Rouge' => [
                'top' => ['Red Berries', 'Bergamot', 'Pink Pepper'],
                'heart' => ['Rose', 'Peony', 'Jasmine'],
                'base' => ['Patchouli', 'Musk', 'Vanilla', 'Cedar'],
                'family' => 'Floral Woody',
            ],

            // ── Arabian / Oriental fragrances ──────────────────────────

            'Maddawi Oud Arabian Oud' => [
                'top' => ['Saffron', 'Rose', 'Bergamot'],
                'heart' => ['Oud', 'Amber', 'Rose'],
                'base' => ['Oud', 'Musk', 'Sandalwood', 'Amber'],
                'family' => 'Oriental Oud',
            ],
            'Wisal' => [
                'top' => ['Saffron', 'Bergamot', 'Cinnamon'],
                'heart' => ['Rose', 'Jasmine', 'Oud'],
                'base' => ['Amber', 'Musk', 'Sandalwood', 'Patchouli'],
                'family' => 'Oriental Woody',
            ],
            'Kalimat Arabian Oud' => [
                'top' => ['Saffron', 'Bergamot', 'Cardamom'],
                'heart' => ['Rose', 'Oud', 'Jasmine'],
                'base' => ['Amber', 'Musk', 'Sandalwood', 'Oud'],
                'family' => 'Oriental Oud',
            ],
            'Turathi Blue Afnan' => [
                'top' => ['Grapefruit', 'Bergamot', 'Pink Pepper'],
                'heart' => ['Oud', 'Saffron', 'Rose'],
                'base' => ['Amber', 'Musk', 'Cedar', 'Sandalwood'],
                'family' => 'Oriental Oud',
            ],
            'Diplomatic Oud' => [
                'top' => ['Saffron', 'Bergamot', 'Rose'],
                'heart' => ['Oud', 'Patchouli', 'Cedar'],
                'base' => ['Oud', 'Amber', 'Musk', 'Sandalwood'],
                'family' => 'Oriental Oud',
            ],
            'Khamra Latifa' => [
                'top' => ['Saffron', 'Cinnamon', 'Bergamot'],
                'heart' => ['Rose', 'Oud', 'Honey'],
                'base' => ['Amber', 'Vanilla', 'Sandalwood', 'Musk'],
                'family' => 'Oriental Spicy Gourmand',
            ],
            'Khamra Coffee' => [
                'top' => ['Coffee', 'Cinnamon', 'Cardamom'],
                'heart' => ['Oud', 'Rose', 'Cacao'],
                'base' => ['Vanilla', 'Amber', 'Sandalwood', 'Musk'],
                'family' => 'Oriental Gourmand',
            ],
            'Laytha Pegasus Marly' => [
                'top' => ['Bergamot', 'Pink Pepper', 'Cardamom'],
                'heart' => ['Jasmine', 'Oud', 'Lavender'],
                'base' => ['Amber', 'Vanilla', 'Sandalwood', 'Musk'],
                'family' => 'Oriental Woody',
            ],
            'Ana Walshouq' => [
                'top' => ['Saffron', 'Rose', 'Bergamot'],
                'heart' => ['Oud', 'Jasmine', 'Amber'],
                'base' => ['Musk', 'Sandalwood', 'Oud', 'Amber'],
                'family' => 'Oriental Floral Oud',
            ],
            'Hamoul' => [
                'top' => ['Saffron', 'Bergamot', 'Cardamom'],
                'heart' => ['Oud', 'Rose', 'Amber'],
                'base' => ['Musk', 'Sandalwood', 'Amber', 'Oud'],
                'family' => 'Oriental Oud',
            ],
            'Khayali Marshmallow' => [
                'top' => ['Marshmallow', 'Bergamot', 'Vanilla'],
                'heart' => ['Jasmine', 'Rose', 'Orange Blossom'],
                'base' => ['Vanilla', 'Musk', 'Amber', 'Sandalwood'],
                'family' => 'Oriental Gourmand',
            ],

            // ── Musk / Clean fragrances ────────────────────────────────

            'White Musk' => [
                'top' => ['Bergamot', 'Lemon', 'Green Notes'],
                'heart' => ['White Musk', 'Jasmine', 'Rose', 'Lily'],
                'base' => ['White Musk', 'Vanilla', 'Sandalwood', 'Amber'],
                'family' => 'Floral Musk',
            ],
            'Musk Tout' => [
                'top' => ['Bergamot', 'Aldehydes'],
                'heart' => ['White Musk', 'Jasmine', 'Rose'],
                'base' => ['Musk', 'Vanilla', 'Sandalwood', 'Amber'],
                'family' => 'Floral Musk',
            ],
            'Musk Powder Oil' => [
                'top' => ['Bergamot', 'Powdery Notes'],
                'heart' => ['White Musk', 'Iris', 'Violet'],
                'base' => ['Musk', 'Vanilla', 'Sandalwood', 'Powdery Notes'],
                'family' => 'Powdery Musk',
            ],
            'Musk Tahara' => [
                'top' => ['Bergamot', 'Lemon', 'Floral Notes'],
                'heart' => ['White Musk', 'Rose', 'Jasmine'],
                'base' => ['Musk', 'Vanilla', 'Sandalwood'],
                'family' => 'Clean Musk',
            ],
            'Musk Cherry' => [
                'top' => ['Cherry', 'Bergamot', 'Red Berries'],
                'heart' => ['White Musk', 'Rose', 'Jasmine'],
                'base' => ['Musk', 'Vanilla', 'Amber', 'Sandalwood'],
                'family' => 'Fruity Musk',
            ],
            'Musk Athara' => [
                'top' => ['Bergamot', 'Saffron', 'Cardamom'],
                'heart' => ['White Musk', 'Oud', 'Rose'],
                'base' => ['Musk', 'Amber', 'Sandalwood', 'Oud'],
                'family' => 'Oriental Musk',
            ],
            'Musk Pomegranate' => [
                'top' => ['Pomegranate', 'Bergamot', 'Red Berries'],
                'heart' => ['White Musk', 'Rose', 'Peony'],
                'base' => ['Musk', 'Vanilla', 'Amber', 'Sandalwood'],
                'family' => 'Fruity Musk',
            ],

            // ── Floral / Other ─────────────────────────────────────────

            'Jasmine' => [
                'top' => ['Bergamot', 'Mandarin Orange', 'Green Notes'],
                'heart' => ['Jasmine', 'Jasmine Sambac', 'Neroli'],
                'base' => ['White Musk', 'Sandalwood', 'Cedar'],
                'family' => 'Floral White',
            ],
            'Afternoon Swim' => [
                'top' => ['Bergamot', 'Mandarin Orange', 'Neroli'],
                'heart' => ['Sea Notes', 'Sicilian Lemon', 'Pepper'],
                'base' => ['Cedar', 'White Musk', 'Amber'],
                'family' => 'Citrus Aquatic',
            ],
            'Santal Cham' => [
                'top' => ['Bergamot', 'Cardamom', 'Pink Pepper'],
                'heart' => ['Sandalwood', 'Cedar', 'Iris'],
                'base' => ['Sandalwood', 'Amber', 'Musk', 'Vanilla'],
                'family' => 'Woody Oriental',
            ],
            'Tuxedo' => [
                'top' => ['Bergamot', 'Black Pepper', 'Cardamom'],
                'heart' => ['Sage', 'Lavender', 'Iris'],
                'base' => ['Amber', 'Leather', 'Sandalwood', 'Patchouli'],
                'family' => 'Woody Leather',
            ],
            'Sculpture' => [
                'top' => ['Bergamot', 'Mandarin Orange', 'Orange Blossom'],
                'heart' => ['Cedar', 'Jasmine', 'Pelargonium'],
                'base' => ['Tonka Bean', 'Vanilla', 'Benzoin', 'Amber', 'Oakmoss'],
                'family' => 'Oriental Woody',
            ],
            'Tremille' => [
                'top' => ['Bergamot', 'Mandarin Orange', 'Pink Pepper'],
                'heart' => ['Orange Blossom', 'Jasmine', 'Rose'],
                'base' => ['Vanilla', 'Musk', 'Sandalwood', 'Cedar'],
                'family' => 'Floral Woody',
            ],
            'Voyage' => [
                'top' => ['Bergamot', 'Mandarin Orange', 'Cardamom'],
                'heart' => ['Jasmine', 'Cedar', 'Hedione'],
                'base' => ['Iso E Super', 'Musk', 'Amber', 'Sandalwood'],
                'family' => 'Woody Aromatic',
            ],
            'Hawaii' => [
                'top' => ['Coconut', 'Pineapple', 'Bergamot'],
                'heart' => ['Jasmine', 'Ylang-Ylang', 'Sea Notes'],
                'base' => ['Vanilla', 'Musk', 'Sandalwood', 'Amber'],
                'family' => 'Floral Tropical',
            ],
            'Sea Passion' => [
                'top' => ['Sea Notes', 'Bergamot', 'Lemon'],
                'heart' => ['Water Lily', 'Jasmine', 'Marine Accords'],
                'base' => ['Cedar', 'Musk', 'Amber', 'Driftwood'],
                'family' => 'Aquatic Floral',
            ],
            '3G' => [
                'top' => ['Bergamot', 'Grapefruit', 'Cardamom'],
                'heart' => ['Ginger', 'Geranium', 'Lavender'],
                'base' => ['Guaiac Wood', 'Gourmand Accord', 'Amber', 'Musk'],
                'family' => 'Woody Spicy',
            ],
            'Silver Dust' => [
                'top' => ['Bergamot', 'Saffron', 'Pink Pepper'],
                'heart' => ['Iris', 'Oud', 'Rose'],
                'base' => ['Silver Musk', 'Amber', 'Sandalwood', 'Patchouli'],
                'family' => 'Oriental Powdery',
            ],
            'Darge' => [
                'top' => ['Saffron', 'Bergamot', 'Pink Pepper'],
                'heart' => ['Oud', 'Rose', 'Jasmine'],
                'base' => ['Amber', 'Musk', 'Sandalwood', 'Oud'],
                'family' => 'Oriental Oud',
            ],
            'Imperial Valley Story' => [
                'top' => ['Bergamot', 'Grapefruit', 'Pink Pepper'],
                'heart' => ['Lavender', 'Geranium', 'Cinnamon'],
                'base' => ['Vanilla', 'Amber', 'Tonka Bean', 'Cedar'],
                'family' => 'Oriental Spicy',
            ],
            'Hudson Valley Story' => [
                'top' => ['Bergamot', 'Mandarin Orange', 'Cardamom'],
                'heart' => ['Iris', 'Violet', 'Lavender'],
                'base' => ['Suede', 'Amber', 'Sandalwood', 'Musk'],
                'family' => 'Woody Floral',
            ],
            'Flamingo Ramon Monegal' => [
                'top' => ['Pink Grapefruit', 'Bergamot', 'Red Berries'],
                'heart' => ['Pink Peony', 'Jasmine', 'Rose'],
                'base' => ['Vanilla', 'Musk', 'Sandalwood', 'Amber'],
                'family' => 'Floral Fruity',
            ],
            'Lead Medal' => [
                'top' => ['Grapefruit', 'Bergamot', 'Pink Pepper'],
                'heart' => ['Lavender', 'Geranium', 'Cardamom'],
                'base' => ['Patchouli', 'Cedar', 'Amber', 'Vetiver'],
                'family' => 'Woody Aromatic',
            ],
            'Black XS Men Paco Rabanne' => [
                'top' => ['Calabrian Bergamot', 'Lemon', 'Tagete'],
                'heart' => ['Cinnamon', 'Cypress', 'Cardamom', 'Sage'],
                'base' => ['Patchouli', 'Black Amber', 'Calypsone', 'Cypress'],
                'family' => 'Woody Spicy',
            ],
            'Black Lexus' => [
                'top' => ['Bergamot', 'Black Pepper', 'Cardamom'],
                'heart' => ['Leather', 'Oud', 'Saffron'],
                'base' => ['Amber', 'Patchouli', 'Sandalwood', 'Musk'],
                'family' => 'Leather Oriental',
            ],
            'Ralph Lauren' => [
                'top' => ['Bergamot', 'Mandarin Orange', 'Melon'],
                'heart' => ['Jasmine', 'Rose', 'Freesia', 'Tuberose'],
                'base' => ['Sandalwood', 'Musk', 'Vetiver', 'Cedar'],
                'family' => 'Floral Woody',
            ],
            'Thomas Casamula' => [
                'top' => ['Bergamot', 'Cardamom', 'Pink Pepper'],
                'heart' => ['Tobacco', 'Leather', 'Saffron'],
                'base' => ['Oud', 'Amber', 'Sandalwood', 'Musk'],
                'family' => 'Oriental Tobacco',
            ],
            'VI Sixteen Power' => [
                'top' => ['Bergamot', 'Grapefruit', 'Saffron'],
                'heart' => ['Oud', 'Rose', 'Jasmine'],
                'base' => ['Amber', 'Musk', 'Cedar', 'Sandalwood'],
                'family' => 'Oriental Oud',
            ],
        ];
    }
}
