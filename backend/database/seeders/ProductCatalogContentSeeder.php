<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Keeps customer-facing product copy populated for the catalog.
 *
 * The Arabic and English descriptions are editorial summaries based on the
 * fragrance note pyramids already maintained by FragranceNotesSeeder. They are
 * intentionally written as fresh catalog copy rather than copied long-form text.
 */
class ProductCatalogContentSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            'Flamingo Ramon Monegal' => [
                'description_ar' => 'فلامينجو رامون مونيجال هو عطر للجنسين بطابع زهري فاكهي. تبدأ الرائحة بـالجريب فروت الوردي، البرغموت والتوت الأحمر، ثم تتطور إلى قلب من الفاوانيا الوردية، الياسمين والورد، وتستقر على قاعدة من الفانيلا، المسك، خشب الصندل والعنبر.',
                'description' => 'Flamingo Ramon Monegal is a floral fruity fragrance built around a luminous floral-fruity profile. It opens with pink grapefruit, bergamot and red berries, develops through a heart of pink peony, jasmine and rose, and settles into a lasting base of vanilla, musk, sandalwood and amber.',
            ],
            'Mancera Rose Vanille' => [
                'description_ar' => 'مانسيرا روز فانيل هو عطر نسائي بطابع Floral Vanilla. تبدأ الرائحة بـالتفاح، البرغموت والكشمش الأسود، ثم تتطور إلى قلب من الورد، الياسمين والبنفسج، وتستقر على قاعدة من الفانيلا، المسك الأبيض، خشب الأرز والعنبر.',
                'description' => 'Mancera Rose Vanille is a floral vanilla fragrance built around a distinctive balanced scent. It opens with apple, bergamot and black currant, develops through a heart of rose, jasmine and violet, and settles into a lasting base of vanilla, white musk, cedar and amber.',
            ],
            'Lead Medal' => [
                'description_ar' => 'الوسام الرصاصي هو عطر رجالي بطابع خشبي عطري. تبدأ الرائحة بـالجريب فروت، البرغموت والفلفل الوردي، ثم تتطور إلى قلب من الخزامى، Geranium والهيل، وتستقر على قاعدة من الباتشولي، خشب الأرز، العنبر ونجيل الهند.',
                'description' => 'Lead Medal is a woody aromatic fragrance built around clean woody freshness. It opens with grapefruit, bergamot and pink pepper, develops through a heart of lavender, geranium and cardamom, and settles into a lasting base of patchouli, cedar, amber and vetiver.',
            ],
            'Armani Black Code' => [
                'description_ar' => 'بلاك كود ارماني هو عطر رجالي بطابع شرقي متبّل. تبدأ الرائحة بـالبرغموت، الليمون، الريحان واليوسفي، ثم تتطور إلى قلب من اليانسون النجمي، زهر الزيتون وخشب الغاياك، وتستقر على قاعدة من الجلد، التبغ وحبوب التونكا.',
                'description' => 'Armani Black Code is a oriental spicy fragrance built around warm spicy depth. It opens with bergamot, lemon, basil and mandarin orange, develops through a heart of star anise, olive blossom and guaiac wood, and settles into a lasting base of leather, tobacco and tonka bean.',
            ],
            'Miracle Garden' => [
                'description_ar' => 'ميراكل جاردن هو عطر نسائي بطابع زهري خشبي. تبدأ الرائحة بـالفواكه الحمراء، اليوسفي والبرغموت، ثم تتطور إلى قلب من الورد، الياسمين، الفاوانيا والزنبق، وتستقر على قاعدة من الفانيلا، المسك، خشب الصندل والعنبر.',
                'description' => 'Miracle Garden is a floral woody fragrance built around a balanced floral-woody profile. It opens with red fruits, mandarin orange and bergamot, develops through a heart of rose, jasmine, peony and lily, and settles into a lasting base of vanilla, musk, sandalwood and amber.',
            ],
            'Pink Chiffon' => [
                'description_ar' => 'بينك شيفون هو عطر نسائي بطابع زهري فاكهي. تبدأ الرائحة بـالتفاح الوردي، البرغموت والكشمش الأسود، ثم تتطور إلى قلب من الياسمين الوردي، الفاوانيا والورد، وتستقر على قاعدة من الفانيلا، المسك وخشب الصندل.',
                'description' => 'Pink Chiffon is a floral fruity fragrance built around a luminous floral-fruity profile. It opens with pink apple, bergamot and black currant, develops through a heart of pink jasmine, peony and rose, and settles into a lasting base of vanilla, musk and sandalwood.',
            ],
            'Invictus Victory Elixir' => [
                'description_ar' => 'انفكتوس فيكتوري الكسير هو عطر رجالي بطابع شرقي متبّل. تبدأ الرائحة بـالفلفل الوردي، الجريب فروت والبرغموت، ثم تتطور إلى قلب من الخزامى، Geranium، الأوريغانو والباتشولي، وتستقر على قاعدة من الفانيلا، العنبر، خشب الأرز وحبوب التونكا.',
                'description' => 'Invictus Victory Elixir is a oriental spicy fragrance built around warm spicy depth. It opens with pink pepper, grapefruit and bergamot, develops through a heart of lavender, geranium, oregano and patchouli, and settles into a lasting base of vanilla, amber, cedar and tonka bean.',
            ],
            'Ralph Lauren' => [
                'description_ar' => 'رالف لورين هو عطر للجنسين بطابع زهري خشبي. تبدأ الرائحة بـالبرغموت، اليوسفي والبطيخ، ثم تتطور إلى قلب من الياسمين، الورد، الفريزيا ومسك الروم، وتستقر على قاعدة من خشب الصندل، المسك، نجيل الهند وخشب الأرز.',
                'description' => 'Ralph Lauren is a floral woody fragrance built around a balanced floral-woody profile. It opens with bergamot, mandarin orange and melon, develops through a heart of jasmine, rose, freesia and tuberose, and settles into a lasting base of sandalwood, musk, vetiver and cedar.',
            ],
            'Ana Walshouq' => [
                'description_ar' => 'انا والشوق هو عطر للجنسين بطابع Oriental Floral Oud. تبدأ الرائحة بـالزعفران، الورد والبرغموت، ثم تتطور إلى قلب من العود، الياسمين والعنبر، وتستقر على قاعدة من المسك، خشب الصندل، العود والعنبر.',
                'description' => 'Ana Walshouq is a oriental floral oud fragrance built around a distinctive balanced scent. It opens with saffron, rose and bergamot, develops through a heart of oud, jasmine and amber, and settles into a lasting base of musk, sandalwood, oud and amber.',
            ],
            'Maddawi Oud Arabian Oud' => [
                'description_ar' => 'عود مضاوي العربية للعود هو عطر للجنسين بطابع شرقي عودي. تبدأ الرائحة بـالزعفران، الورد والبرغموت، ثم تتطور إلى قلب من العود، العنبر والورد، وتستقر على قاعدة من العود، المسك، خشب الصندل والعنبر.',
                'description' => 'Maddawi Oud Arabian Oud is a oriental oud fragrance built around deep oud and amber. It opens with saffron, rose and bergamot, develops through a heart of oud, amber and rose, and settles into a lasting base of oud, musk, sandalwood and amber.',
            ],
            'Wisal' => [
                'description_ar' => 'وصال هو عطر للجنسين بطابع شرقي خشبي. تبدأ الرائحة بـالزعفران، البرغموت والقرفة، ثم تتطور إلى قلب من الورد، الياسمين والعود، وتستقر على قاعدة من العنبر، المسك، خشب الصندل والباتشولي.',
                'description' => 'Wisal is a oriental woody fragrance built around warm polished woods. It opens with saffron, bergamot and cinnamon, develops through a heart of rose, jasmine and oud, and settles into a lasting base of amber, musk, sandalwood and patchouli.',
            ],
            'Black XS Men Paco Rabanne' => [
                'description_ar' => 'بلاك اكس اس رجالي باكوربان هو عطر رجالي بطابع خشبي متبّل. تبدأ الرائحة بـبرغموت كالابريا، الليمون وتاغيت، ثم تتطور إلى قلب من القرفة، السرو، الهيل والمريمية، وتستقر على قاعدة من الباتشولي، العنبر الأسود، كاليبْسون والسرو.',
                'description' => 'Black XS Men Paco Rabanne is a woody spicy fragrance built around a distinctive balanced scent. It opens with calabrian bergamot, lemon and tagete, develops through a heart of cinnamon, cypress, cardamom and sage, and settles into a lasting base of patchouli, black amber, calypsone and cypress.',
            ],
            'Turathi Blue Afnan' => [
                'description_ar' => 'تراثي بلو افنان هو عطر للجنسين بطابع شرقي عودي. تبدأ الرائحة بـالجريب فروت، البرغموت والفلفل الوردي، ثم تتطور إلى قلب من العود، الزعفران والورد، وتستقر على قاعدة من العنبر، المسك، خشب الأرز وخشب الصندل.',
                'description' => 'Turathi Blue Afnan is a oriental oud fragrance built around deep oud and amber. It opens with grapefruit, bergamot and pink pepper, develops through a heart of oud, saffron and rose, and settles into a lasting base of amber, musk, cedar and sandalwood.',
            ],
            'Afternoon Swim' => [
                'description_ar' => 'افتر نون سويم هو عطر للجنسين بطابع حمضي مائي. تبدأ الرائحة بـالبرغموت، اليوسفي وNeroli، ثم تتطور إلى قلب من نفحات بحرية، الليمون الصقلي وPepper، وتستقر على قاعدة من خشب الأرز، المسك الأبيض والعنبر.',
                'description' => 'Afternoon Swim is a citrus aquatic fragrance built around citrus freshness with a marine edge. It opens with bergamot, mandarin orange and neroli, develops through a heart of sea notes, sicilian lemon and pepper, and settles into a lasting base of cedar, white musk and amber.',
            ],
            'Midnight Britney Spears' => [
                'description_ar' => 'ميدنايت بريتني سبيرز هو عطر نسائي بطابع Floral Oriental. تبدأ الرائحة بـالبرقوق، الكرز الأسود، الفريزيا وأوركيد الفانيلا، ثم تتطور إلى قلب من الياسمين الليلي، السوسن، الفاوانيا والزعرور، وتستقر على قاعدة من العنبر، المسك، خشب الصندل والفانيلا.',
                'description' => 'Midnight Britney Spears is a floral oriental fragrance built around a distinctive balanced scent. It opens with plum, black cherry, freesia and vanilla orchid, develops through a heart of night-blooming jasmine, iris, peony and hawthorn, and settles into a lasting base of amber, musk, sandalwood and vanilla.',
            ],
            'Mont Blanc Legend' => [
                'description_ar' => 'مون بلان ليجيند هو عطر رجالي بطابع فوجير عطري. تبدأ الرائحة بـالبرغموت، الخزامى، الأناناس وLemon Verbena، ثم تتطور إلى قلب من طحلب السنديان، Geranium، الكومارين والورد، وتستقر على قاعدة من خشب الصندل، حبوب التونكا والباتشولي.',
                'description' => 'Mont Blanc Legend is a aromatic fougere fragrance built around crisp aromatic freshness. It opens with bergamot, lavender, pineapple and lemon verbena, develops through a heart of oakmoss, geranium, coumarin and rose, and settles into a lasting base of sandalwood, tonka bean and patchouli.',
            ],
            'Escada Magnetism' => [
                'description_ar' => 'اسكادا مغنتزيم هو عطر نسائي بطابع Floral Oriental. تبدأ الرائحة بـالبطيخ، الريحان، التوت الأحمر واليوسفي، ثم تتطور إلى قلب من الورد، الياسمين، الهليوتروب وزنبق الوادي، وتستقر على قاعدة من الفانيلا، الباتشولي، خشب الصندل والمسك.',
                'description' => 'Escada Magnetism is a floral oriental fragrance built around a distinctive balanced scent. It opens with melon, basil, red berries and mandarin orange, develops through a heart of rose, jasmine, heliotrope and lily-of-the-valley, and settles into a lasting base of vanilla, patchouli, sandalwood and musk.',
            ],
            'Imagination Louis Vuitton' => [
                'description_ar' => 'ايماجينيشن لوي فيتون هو عطر للجنسين بطابع حمضي عطري. تبدأ الرائحة بـبرغموت كالابريا، البرتقال الصقلي وNeroli، ثم تتطور إلى قلب من الشاي الأسود الصيني، الأمبريت والسرو، وتستقر على قاعدة من خشب الصندل وأرز غرانديفوليوم.',
                'description' => 'Imagination Louis Vuitton is a aromatic citrus fragrance built around a distinctive balanced scent. It opens with calabrian bergamot, sicilian orange and neroli, develops through a heart of chinese black tea, ambrette and cypress, and settles into a lasting base of sandalwood and grandifolium cedar.',
            ],
            'Burberry Her' => [
                'description_ar' => 'بربري هير هو عطر نسائي بطابع شرقي زهري حلو. تبدأ الرائحة بـالفراولة، التوت العليق، التوت الأسود والبرتقال المر، ثم تتطور إلى قلب من البنفسج، الياسمين، الفاوانيا والزنبق، وتستقر على قاعدة من الفانيلا، المسك، طحلب السنديان وخشب الأرز.',
                'description' => 'Burberry Her is a oriental floral gourmand fragrance built around a distinctive balanced scent. It opens with strawberry, raspberry, blackberry and bitter orange, develops through a heart of violet, jasmine, peony and lily, and settles into a lasting base of vanilla, musk, oakmoss and cedar.',
            ],
            'La Vie Est Belle Lancome' => [
                'description_ar' => 'عطر نسائي حلو وأنيق، يفتتح بالكشمش الأسود والكمثرى، ثم يظهر السوسن وزهر البرتقال والياسمين، وتستقر الرائحة على الباتشولي والفانيلا والتونكا والبرالين.',
                'description' => 'La Vie Est Belle Lancome is a oriental floral gourmand fragrance built around a distinctive balanced scent. It opens with black currant and pear, develops through a heart of iris, orange blossom, jasmine and praline, and settles into a lasting base of patchouli, vanilla, tonka bean and praline.',
            ],
            'Arabian Tonka Montale' => [
                'description_ar' => 'اربيان تونكا مونتال هو عطر للجنسين بطابع شرقي عنبري. تبدأ الرائحة بـالبرغموت، الزعفران واللوز المر، ثم تتطور إلى قلب من الورد، العسل وحبوب التونكا، وتستقر على قاعدة من العود، المسك الأبيض، العنبر وخشب الصندل.',
                'description' => 'Arabian Tonka Montale is a oriental amber fragrance built around a distinctive balanced scent. It opens with bergamot, saffron and bitter almond, develops through a heart of rose, honey and tonka bean, and settles into a lasting base of oud, white musk, amber and sandalwood.',
            ],
            'Creed Silver Mountain' => [
                'description_ar' => 'كريد سيلفر ماونتن هو عطر للجنسين بطابع Aromatic Green. تبدأ الرائحة بـالبرغموت، اليوسفي، الكشمش الأسود وGalbanum، ثم تتطور إلى قلب من الشاي، Green Notes، البنفسج والورد، وتستقر على قاعدة من المسك، خشب الصندل والعنبر.',
                'description' => 'Creed Silver Mountain is a aromatic green fragrance built around a distinctive balanced scent. It opens with bergamot, mandarin orange, black currant and galbanum, develops through a heart of tea, green notes, violet and rose, and settles into a lasting base of musk, sandalwood and amber.',
            ],
            'Azzaro Wanted' => [
                'description_ar' => 'عطر رجالي عطري متبّل، يبدأ بالليمون والزنجبيل والبرغموت والنعناع، ثم ينتقل إلى الهيل والتفاح والقرفة، وينتهي بالتونكا والعنبر والبنزويين ونجيل الهند.',
                'description' => 'Azzaro Wanted is a aromatic spicy fragrance built around a distinctive balanced scent. It opens with lemon, ginger, bergamot and mint, develops through a heart of cardamom, juniper, apple and cinnamon, and settles into a lasting base of tonka bean, vetiver, amber and benzoin.',
            ],
            'Love Is Heavenly' => [
                'description_ar' => 'لوف اذ هفنلي هو عطر نسائي بطابع زهري خشبي. تبدأ الرائحة بـالبرغموت، اليوسفي وLotus، ثم تتطور إلى قلب من الياسمين، الفاوانيا، الورد والسوسن، وتستقر على قاعدة من المسك، الفانيلا، خشب الصندل والعنبر.',
                'description' => 'Love Is Heavenly is a floral woody fragrance built around a balanced floral-woody profile. It opens with bergamot, mandarin orange and lotus, develops through a heart of jasmine, peony, rose and iris, and settles into a lasting base of musk, vanilla, sandalwood and amber.',
            ],
            'Erba Pura Xerjoff' => [
                'description_ar' => 'عطر فاكهي غني يدمج الحمضيات والفاكهة الحمراء مع زهر البرتقال والياسمين، ثم يستقر على المسك والعنبر والبنزويين والفانيلا والكراميل بلمسة حلوة ومشرقة.',
                'description' => 'Erba Pura Xerjoff is a fruity gourmand fragrance built around bright fruity sweetness. It opens with sicilian orange, calabrian bergamot, sicilian lemon and red fruits, develops through a heart of orange blossom, jasmine, bergamot and pear, and settles into a lasting base of musk, amber, benzoin and vanilla.',
            ],
            'Laytha Pegasus Marly' => [
                'description_ar' => 'الثائر دو مارلي هو عطر للجنسين بطابع شرقي خشبي. تبدأ الرائحة بـالبرغموت، الفلفل الوردي والهيل، ثم تتطور إلى قلب من الياسمين، العود والخزامى، وتستقر على قاعدة من العنبر، الفانيلا، خشب الصندل والمسك.',
                'description' => 'Laytha Pegasus Marly is a oriental woody fragrance built around warm polished woods. It opens with bergamot, pink pepper and cardamom, develops through a heart of jasmine, oud and lavender, and settles into a lasting base of amber, vanilla, sandalwood and musk.',
            ],
            'Black Opium YSL' => [
                'description_ar' => 'عطر نسائي دافئ ومغري تبرز فيه القهوة والياسمين واللوز المر، مع لمسات من زهر البرتقال، وينتهي بقاعدة من الفانيلا والباتشولي والأخشاب الناعمة.',
                'description' => 'Black Opium YSL is a oriental vanilla fragrance built around rich vanilla sweetness. It opens with pink pepper and orange blossom, develops through a heart of coffee, jasmine and bitter almond, and settles into a lasting base of vanilla, patchouli, cedar and cashmere wood.',
            ],
            'Sospiro Accento' => [
                'description_ar' => 'سوسبيرو اكسنتو هو عطر للجنسين بطابع زهري فاكهي. تبدأ الرائحة بـالأناناس، الصفير، البرتقال المر والطرخون، ثم تتطور إلى قلب من السوسن، الياسمين، الفاوانيا والمسك، وتستقر على قاعدة من الفانيلا، العنبر، خشب الصندل والباتشولي.',
                'description' => 'Sospiro Accento is a floral fruity fragrance built around a luminous floral-fruity profile. It opens with pineapple, hyacinth, bitter orange and tarragon, develops through a heart of iris, jasmine, peony and musk, and settles into a lasting base of vanilla, amber, sandalwood and patchouli.',
            ],
            'Escada Tag Sunset' => [
                'description_ar' => 'اسكادا تاج سانسيت هو عطر نسائي بطابع زهري فاكهي. تبدأ الرائحة بـالكمثرى، اليوسفي والبرغموت، ثم تتطور إلى قلب من زهر البرتقال، الياسمين والورد، وتستقر على قاعدة من الفانيلا، المسك، خشب الأرز والعنبر.',
                'description' => 'Escada Tag Sunset is a floral fruity fragrance built around a luminous floral-fruity profile. It opens with pear, mandarin orange and bergamot, develops through a heart of orange blossom, jasmine and rose, and settles into a lasting base of vanilla, musk, cedar and amber.',
            ],
            'VI Sixteen Power' => [
                'description_ar' => 'في سكستين باور هو عطر للجنسين بطابع شرقي عودي. تبدأ الرائحة بـالبرغموت، الجريب فروت والزعفران، ثم تتطور إلى قلب من العود، الورد والياسمين، وتستقر على قاعدة من العنبر، المسك، خشب الأرز وخشب الصندل.',
                'description' => 'VI Sixteen Power is a oriental oud fragrance built around deep oud and amber. It opens with bergamot, grapefruit and saffron, develops through a heart of oud, rose and jasmine, and settles into a lasting base of amber, musk, cedar and sandalwood.',
            ],
            'Santal Cham' => [
                'description_ar' => 'سانتال شام هو عطر للجنسين بطابع خشبي شرقي. تبدأ الرائحة بـالبرغموت، الهيل والفلفل الوردي، ثم تتطور إلى قلب من خشب الصندل، خشب الأرز والسوسن، وتستقر على قاعدة من خشب الصندل، العنبر، المسك والفانيلا.',
                'description' => 'Santal Cham is a woody oriental fragrance built around a distinctive balanced scent. It opens with bergamot, cardamom and pink pepper, develops through a heart of sandalwood, cedar and iris, and settles into a lasting base of sandalwood, amber, musk and vanilla.',
            ],
            'Fantasy Britney Spears' => [
                'description_ar' => 'فنتازيا بريتني سبيرز هو عطر نسائي بطابع Floral Gourmand. تبدأ الرائحة بـالكيوي، الليتشي الأحمر والسفرجل، ثم تتطور إلى قلب من الشوكولاتة البيضاء، الكب كيك، الأوركيد والياسمين، وتستقر على قاعدة من المسك، جذر السوسن ونفحات خشبية.',
                'description' => 'Fantasy Britney Spears is a floral gourmand fragrance built around a distinctive balanced scent. It opens with kiwi, red lychee and quince, develops through a heart of white chocolate, cupcake, orchid and jasmine, and settles into a lasting base of musk, orris root and woodsy notes.',
            ],
            'Tremille' => [
                'description_ar' => 'الترميل هو عطر للجنسين بطابع زهري خشبي. تبدأ الرائحة بـالبرغموت، اليوسفي والفلفل الوردي، ثم تتطور إلى قلب من زهر البرتقال، الياسمين والورد، وتستقر على قاعدة من الفانيلا، المسك، خشب الصندل وخشب الأرز.',
                'description' => 'Tremille is a floral woody fragrance built around a balanced floral-woody profile. It opens with bergamot, mandarin orange and pink pepper, develops through a heart of orange blossom, jasmine and rose, and settles into a lasting base of vanilla, musk, sandalwood and cedar.',
            ],
            'Good Girl' => [
                'description_ar' => 'تركيبة زهرية شرقية تجمع اللوز والقهوة مع مسك الروم والياسمين وزهر البرتقال، ثم تستقر على التونكا والكاكاو والصندل والبرالين بطابع حلو وفخم.',
                'description' => 'Good Girl is a oriental floral fragrance built around opulent floral depth. It opens with almond and coffee, develops through a heart of tuberose, jasmine sambac and orange blossom, and settles into a lasting base of tonka bean, cacao, sandalwood and praline.',
            ],
            'Lacoste White' => [
                'description_ar' => 'لاكوست وايت هو عطر رجالي بطابع خشبي عطري. تبدأ الرائحة بـالجريب فروت الأحمر، البرغموت، الأناناس والهيل، ثم تتطور إلى قلب من الخزامى، أوراق البنفسج، Pepper والمريمية، وتستقر على قاعدة من خشب الصندل، نجيل الهند، العنبر وخشب الأرز.',
                'description' => 'Lacoste White is a woody aromatic fragrance built around clean woody freshness. It opens with ruby grapefruit, bergamot, pineapple and cardamom, develops through a heart of lavender, violet leaves, pepper and sage, and settles into a lasting base of sandalwood, vetiver, amber and cedar.',
            ],
            'Lacoste Black' => [
                'description_ar' => 'لاكوست بلاك هو عطر رجالي بطابع شرقي متبّل. تبدأ الرائحة بـالنعناع المائي، الخزامى والبرغموت، ثم تتطور إلى قلب من عرق السوس، الهيل الأسود والكزبرة، وتستقر على قاعدة من العنبر، الباتشولي، الجلد وحبوب التونكا.',
                'description' => 'Lacoste Black is a oriental spicy fragrance built around warm spicy depth. It opens with water mint, lavender and bergamot, develops through a heart of licorice, black cardamom and coriander, and settles into a lasting base of amber, patchouli, leather and tonka bean.',
            ],
            'Scandal By Night' => [
                'description_ar' => 'سكاندل باي نايت هو عطر نسائي بطابع شرقي زهري حلو. تبدأ الرائحة بـالعسل والبرتقال المر، ثم تتطور إلى قلب من مسك الروم، زهر البرتقال، النكتارين والغاردينيا، وتستقر على قاعدة من الباتشولي، خشب الصندل، حبوب التونكا والكراميل.',
                'description' => 'Scandal By Night is a oriental floral gourmand fragrance built around a distinctive balanced scent. It opens with honey and bitter orange, develops through a heart of tuberose, orange blossom, nectarine and gardenia, and settles into a lasting base of patchouli, sandalwood, tonka bean and caramel.',
            ],
            'Blue For Man' => [
                'description_ar' => 'بلو فور مان هو عطر رجالي بطابع مائي عطري. تبدأ الرائحة بـالبرغموت، نفحات بحرية والنعناع، ثم تتطور إلى قلب من الخزامى، المريمية وGeranium، وتستقر على قاعدة من خشب الأرز، العنبر، المسك وخشب الصندل.',
                'description' => 'Blue For Man is a aquatic aromatic fragrance built around fresh marine character. It opens with bergamot, marine notes and mint, develops through a heart of lavender, clary sage and geranium, and settles into a lasting base of cedar, amber, musk and sandalwood.',
            ],
            'Bleu de Chanel' => [
                'description_ar' => 'عطر أنيق ومنعش بطابع خشبي عطري، يبدأ بنفحات الحمضيات والفلفل الوردي، ثم يظهر الزنجبيل والياسمين، ويستقر على أخشاب الأرز والصندل والبخور مع لمسة مسكية دافئة.',
                'description' => 'Bleu de Chanel is a woody aromatic fragrance built around clean woody freshness. It opens with grapefruit, lemon, mint and pink pepper, develops through a heart of ginger, nutmeg, jasmine and iso e super, and settles into a lasting base of incense, vetiver, cedar and sandalwood.',
            ],
            'Champion Davidoff' => [
                'description_ar' => 'تشامبيون دافيدوف هو عطر رجالي بطابع خشبي عطري. تبدأ الرائحة بـالبرغموت، الجريب فروت وGalbanum، ثم تتطور إلى قلب من الخزامى، البنفسج والمريمية، وتستقر على قاعدة من طحلب السنديان، الجلد، خشب الأرز والباتشولي.',
                'description' => 'Champion Davidoff is a woody aromatic fragrance built around clean woody freshness. It opens with bergamot, grapefruit and galbanum, develops through a heart of lavender, violet and clary sage, and settles into a lasting base of oakmoss, leather, cedar and patchouli.',
            ],
            'Kalimat Arabian Oud' => [
                'description_ar' => 'كلمات العربية للعود هو عطر للجنسين بطابع شرقي عودي. تبدأ الرائحة بـالزعفران، البرغموت والهيل، ثم تتطور إلى قلب من الورد، العود والياسمين، وتستقر على قاعدة من العنبر، المسك، خشب الصندل والعود.',
                'description' => 'Kalimat Arabian Oud is a oriental oud fragrance built around deep oud and amber. It opens with saffron, bergamot and cardamom, develops through a heart of rose, oud and jasmine, and settles into a lasting base of amber, musk, sandalwood and oud.',
            ],
            'Silver Cent' => [
                'description_ar' => 'سيلفر سنت هو عطر رجالي بطابع خشبي عطري. تبدأ الرائحة بـالبرغموت، التفاح والفلفل الأسود، ثم تتطور إلى قلب من الخزامى، الياسمين والهيل، وتستقر على قاعدة من العنبر، خشب الأرز، خشب الصندل والمسك.',
                'description' => 'Silver Cent is a woody aromatic fragrance built around clean woody freshness. It opens with bergamot, apple and black pepper, develops through a heart of lavender, jasmine and cardamom, and settles into a lasting base of amber, cedar, sandalwood and musk.',
            ],
            'Spice Bomb Extreme' => [
                'description_ar' => 'سبايس بومب اكستريم هو عطر رجالي بطابع شرقي متبّل. تبدأ الرائحة بـالفلفل الأسود، القرفة، الجريب فروت والبرغموت، ثم تتطور إلى قلب من التبغ، الكمون، الخزامى والزعفران، وتستقر على قاعدة من الفانيلا، حبوب التونكا، Black Vanilla وخشب الأرز.',
                'description' => 'Spice Bomb Extreme is a oriental spicy fragrance built around warm spicy depth. It opens with black pepper, cinnamon, grapefruit and bergamot, develops through a heart of tobacco, cumin, lavender and saffron, and settles into a lasting base of vanilla, tonka bean, black vanilla and cedar.',
            ],
            'Acqua Di Gio' => [
                'description_ar' => 'تركيبة مائية منعشة تستحضر أجواء البحر، تبدأ بالبرغموت واليوسفي الأخضر ونفحات البحر، ثم تتطور مع إكليل الجبل وجوزة الطيب، وتستقر على الأرز والباتشولي والمسك الأبيض والعنبر.',
                'description' => 'Acqua Di Gio is a aquatic aromatic fragrance built around fresh marine character. It opens with bergamot, neroli, green tangerine and sea notes, develops through a heart of persimmon, nutmeg, cypress and rose, and settles into a lasting base of cedar, patchouli, white musk and amber.',
            ],
            'Versace Eros' => [
                'description_ar' => 'عطر قوي بطابع منعش وحلو، يفتتح بالنعناع والتفاح الأخضر والليمون، ثم يظهر التونكا وإبرة الراعي، وتأتي قاعدة الفانيلا والأخشاب لتمنحه دفئاً وثباتاً واضحاً.',
                'description' => 'Versace Eros is a oriental woody fragrance built around warm polished woods. It opens with mint, green apple and lemon, develops through a heart of tonka bean, geranium and ambroxan, and settles into a lasting base of vanilla, vetiver, oakmoss and cedar.',
            ],
            '212 VIP Men' => [
                'description_ar' => '212 في اي بي رجالي هو عطر رجالي بطابع خشبي متبّل. تبدأ الرائحة بـالليمون الأخضر، الكافيار، الفلفل الوردي والزنجبيل، ثم تتطور إلى قلب من الفودكا، النعناع المنعش وتوابل، وتستقر على قاعدة من العنبر، الجلد ونفحات خشبية.',
                'description' => '212 VIP Men is a woody spicy fragrance built around a distinctive balanced scent. It opens with lime, caviar, pink pepper and ginger, develops through a heart of vodka, frozen mint and spices, and settles into a lasting base of amber, leather and woody notes.',
            ],
            '212 VIP Women' => [
                'description_ar' => '212 في اي بي نسائي هو عطر نسائي بطابع زهري فاكهي حلو. تبدأ الرائحة بـباشن فروت، الروم، الجريب فروت واليوسفي، ثم تتطور إلى قلب من الغاردينيا، الياسمين وحبوب الفلفل الوردي، وتستقر على قاعدة من حبوب التونكا، الفانيلا، البنزويين والمسك.',
                'description' => '212 VIP Women is a floral fruity gourmand fragrance built around a distinctive balanced scent. It opens with passion fruit, rum, grapefruit and mandarin orange, develops through a heart of gardenia, jasmine and pink peppercorn, and settles into a lasting base of tonka bean, vanilla, benzoin and musk.',
            ],
            '212 Sexy Men' => [
                'description_ar' => '212 سكسي رجالي هو عطر رجالي بطابع شرقي خشبي. تبدأ الرائحة بـاليوسفي، البرغموت والفلفل الأخضر، ثم تتطور إلى قلب من الهيل، خشب الصندل وPepper، وتستقر على قاعدة من الفانيلا، المسك، خشب الغاياك والعنبر.',
                'description' => '212 Sexy Men is a oriental woody fragrance built around warm polished woods. It opens with mandarin orange, bergamot and green pepper, develops through a heart of cardamom, sandalwood and pepper, and settles into a lasting base of vanilla, musk, guaiac wood and amber.',
            ],
            '212 Sexy Women' => [
                'description_ar' => '212 سكسي نسائي هو عطر نسائي بطابع زهري شرقي حلو. تبدأ الرائحة بـاليوسفي، الفلفل الوردي، البرغموت وزهر البرتقال، ثم تتطور إلى قلب من الغاردينيا، حلوى القطن، الورد والفاوانيا، وتستقر على قاعدة من الفانيلا، المسك، خشب الصندل والكراميل.',
                'description' => '212 Sexy Women is a floral oriental gourmand fragrance built around a distinctive balanced scent. It opens with mandarin orange, pink pepper, bergamot and orange blossom, develops through a heart of gardenia, cotton candy, rose and peony, and settles into a lasting base of vanilla, musk, sandalwood and caramel.',
            ],
            'Narciso Rodriguez' => [
                'description_ar' => 'نيرسيسو رودريغيز هو عطر للجنسين بطابع خشبي مسكي. تبدأ الرائحة بـWhite Rose والخوخ، ثم تتطور إلى قلب من المسك، العنبر والباتشولي، وتستقر على قاعدة من خشب الصندل، الفانيلا والمسك.',
                'description' => 'Narciso Rodriguez is a woody musk fragrance built around a distinctive balanced scent. It opens with white rose and peach, develops through a heart of musk, amber and patchouli, and settles into a lasting base of sandalwood, vanilla and musk.',
            ],
            'Olympia' => [
                'description_ar' => 'عطر نسائي مائي شرقي يجمع اليوسفي والنفحات الخضراء والياسمين المائي مع الزنجبيل والملح والفانيلا، ثم يستقر على العنبر والأخشاب بطابع دافئ.',
                'description' => 'Olympia is a aquatic oriental fragrance built around a distinctive balanced scent. It opens with mandarin orange, green notes and water jasmine, develops through a heart of ginger, salt and vanilla, and settles into a lasting base of ambergris, cashmere wood and sandalwood.',
            ],
            'Paris Hilton' => [
                'description_ar' => 'باريس هيلتون هو عطر نسائي بطابع زهري فاكهي. تبدأ الرائحة بـالتفاح، Orange، الخوخ والبطيخ، ثم تتطور إلى قلب من مسك الروم، الياسمين، الزنبق والورد، وتستقر على قاعدة من المسك، Ylang-Ylang، خشب الصندل وطحلب السنديان.',
                'description' => 'Paris Hilton is a floral fruity fragrance built around a luminous floral-fruity profile. It opens with apple, orange, peach and melon, develops through a heart of tuberose, jasmine, lily and rose, and settles into a lasting base of musk, ylang-ylang, sandalwood and oakmoss.',
            ],
            'Khamra Latifa' => [
                'description_ar' => 'خمرة لطافة هو عطر للجنسين بطابع شرقي متبّل حلو. تبدأ الرائحة بـالزعفران، القرفة والبرغموت، ثم تتطور إلى قلب من الورد، العود والعسل، وتستقر على قاعدة من العنبر، الفانيلا، خشب الصندل والمسك.',
                'description' => 'Khamra Latifa is a oriental spicy gourmand fragrance built around a distinctive balanced scent. It opens with saffron, cinnamon and bergamot, develops through a heart of rose, oud and honey, and settles into a lasting base of amber, vanilla, sandalwood and musk.',
            ],
            'Very Sexy Now' => [
                'description_ar' => 'فيري سكسي ناو هو عطر نسائي بطابع Floral Fruity Tropical. تبدأ الرائحة بـالمانجو، Clementine والفلفل الوردي، ثم تتطور إلى قلب من رحيق جوز الهند، الياسمين والفاوانيا، وتستقر على قاعدة من الفانيلا، المسك وخشب الصندل.',
                'description' => 'Very Sexy Now is a floral fruity tropical fragrance built around a distinctive balanced scent. It opens with mango, clementine and pink pepper, develops through a heart of coconut nectar, jasmine and peony, and settles into a lasting base of vanilla, musk and sandalwood.',
            ],
            'Lady Million' => [
                'description_ar' => 'عطر نسائي زهري فاخر يفتتح بالبرتقال المر والتوت وزهر البرتقال، ثم يظهر الياسمين والغاردينيا، وتستقر الرائحة على العسل والعنبر والباتشولي.',
                'description' => 'Lady Million is a floral woody fragrance built around a balanced floral-woody profile. It opens with bitter orange, raspberry and neroli, develops through a heart of orange blossom, jasmine, gardenia and african orange flower, and settles into a lasting base of patchouli, honey and amber.',
            ],
            'Invictus Victory' => [
                'description_ar' => 'انفكتوس فيكتوري هو عطر رجالي بطابع شرقي متبّل. تبدأ الرائحة بـالفلفل الوردي، اليوسفي والجريب فروت، ثم تتطور إلى قلب من الخزامى، Geranium والباتشولي، وتستقر على قاعدة من الفانيلا، العنبر وخشب الأرز.',
                'description' => 'Invictus Victory is a oriental spicy fragrance built around warm spicy depth. It opens with pink pepper, mandarin orange and grapefruit, develops through a heart of lavender, geranium and patchouli, and settles into a lasting base of vanilla, amber and cedar.',
            ],
            'Sauvage Dior' => [
                'description_ar' => 'عطر رجالي منعش وجريء، يجمع برغموت كالابريا والفلفل مع قلب عطري من الخزامى والتوابل، وينتهي بقاعدة من الأمبروكسان والأرز واللابدانوم تمنحه حضوراً واضحاً وطابعاً خشبياً مميزاً.',
                'description' => 'Sauvage Dior is a aromatic fougere fragrance built around crisp aromatic freshness. It opens with calabrian bergamot and pepper, develops through a heart of sichuan pepper, lavender, star anise and nutmeg, and settles into a lasting base of ambroxan, cedar and labdanum.',
            ],
            'Imperial Valley Story' => [
                'description_ar' => 'امبريال فالي قصة هو عطر للجنسين بطابع شرقي متبّل. تبدأ الرائحة بـالبرغموت، الجريب فروت والفلفل الوردي، ثم تتطور إلى قلب من الخزامى، Geranium والقرفة، وتستقر على قاعدة من الفانيلا، العنبر، حبوب التونكا وخشب الأرز.',
                'description' => 'Imperial Valley Story is a oriental spicy fragrance built around warm spicy depth. It opens with bergamot, grapefruit and pink pepper, develops through a heart of lavender, geranium and cinnamon, and settles into a lasting base of vanilla, amber, tonka bean and cedar.',
            ],
            'Khamra Coffee' => [
                'description_ar' => 'خمرة قهوة هو عطر للجنسين بطابع Oriental Gourmand. تبدأ الرائحة بـالقهوة، القرفة والهيل، ثم تتطور إلى قلب من العود، الورد والكاكاو، وتستقر على قاعدة من الفانيلا، العنبر، خشب الصندل والمسك.',
                'description' => 'Khamra Coffee is a oriental gourmand fragrance built around a distinctive balanced scent. It opens with coffee, cinnamon and cardamom, develops through a heart of oud, rose and cacao, and settles into a lasting base of vanilla, amber, sandalwood and musk.',
            ],
            'Black Orchid Tom Ford' => [
                'description_ar' => 'تركيبة شرقية غامضة ومكثفة، تتداخل فيها الكشمش الأسود والإيلنغ والبرغموت مع الأوركيد والتوابل، ثم تظهر قاعدة غنية من الباتشولي والبخور والصندل والفانيلا ولمسات الشوكولاتة.',
                'description' => 'Black Orchid Tom Ford is a oriental floral fragrance built around opulent floral depth. It opens with black currant, tuberose, ylang-ylang and bergamot, develops through a heart of black orchid, spices, lotus and jasmine, and settles into a lasting base of patchouli, incense, sandalwood and vanilla.',
            ],
            'Amber Leather Tom Ford' => [
                'description_ar' => 'امبر ليذر توم فورد هو عطر للجنسين بطابع جلدي عنبري. تبدأ الرائحة بـالهيل، الجلد والشاي الأسود، ثم تتطور إلى قلب من المريمية، الياسمين وسيبريول، وتستقر على قاعدة من العنبر، العود، خشب الصندل والباتشولي.',
                'description' => 'Amber Leather Tom Ford is a leather amber fragrance built around a distinctive balanced scent. It opens with cardamom, leather and black tea, develops through a heart of sage, jasmine and cypriol, and settles into a lasting base of amber, oud, sandalwood and patchouli.',
            ],
            'Diplomatic Oud' => [
                'description_ar' => 'عود دبلوماسي هو عطر للجنسين بطابع شرقي عودي. تبدأ الرائحة بـالزعفران، البرغموت والورد، ثم تتطور إلى قلب من العود، الباتشولي وخشب الأرز، وتستقر على قاعدة من العود، العنبر، المسك وخشب الصندل.',
                'description' => 'Diplomatic Oud is a oriental oud fragrance built around deep oud and amber. It opens with saffron, bergamot and rose, develops through a heart of oud, patchouli and cedar, and settles into a lasting base of oud, amber, musk and sandalwood.',
            ],
            'Hudson Valley Story' => [
                'description_ar' => 'هودسون فالي قصة هو عطر للجنسين بطابع Woody Floral. تبدأ الرائحة بـالبرغموت، اليوسفي والهيل، ثم تتطور إلى قلب من السوسن، البنفسج والخزامى، وتستقر على قاعدة من الشمواه، العنبر، خشب الصندل والمسك.',
                'description' => 'Hudson Valley Story is a woody floral fragrance built around a distinctive balanced scent. It opens with bergamot, mandarin orange and cardamom, develops through a heart of iris, violet and lavender, and settles into a lasting base of suede, amber, sandalwood and musk.',
            ],
            'Born In Roma Intense' => [
                'description_ar' => 'عطر رجالي دافئ يجمع الحمضيات والتوابل مع الخزامى وقرفة ولمسة من التوفي، ثم يستقر على الفانيلا والعنبر والتونكا والأخشاب.',
                'description' => 'Born In Roma Intense is a oriental floral fragrance built around opulent floral depth. It opens with bergamot, pink pepper and ginger, develops through a heart of lavender, jasmine and iris, and settles into a lasting base of vanilla, amber, benzoin and sandalwood.',
            ],
            'Sculpture' => [
                'description_ar' => 'سكلبشر هو عطر للجنسين بطابع شرقي خشبي. تبدأ الرائحة بـالبرغموت، اليوسفي وزهر البرتقال، ثم تتطور إلى قلب من خشب الأرز، الياسمين والبيلارغونيوم، وتستقر على قاعدة من حبوب التونكا، الفانيلا، البنزويين والعنبر.',
                'description' => 'Sculpture is a oriental woody fragrance built around warm polished woods. It opens with bergamot, mandarin orange and orange blossom, develops through a heart of cedar, jasmine and pelargonium, and settles into a lasting base of tonka bean, vanilla, benzoin and amber.',
            ],
            'Amber Nomad Louis Vuitton' => [
                'description_ar' => 'امبر نوماد لوي فيتون هو عطر للجنسين بطابع شرقي عنبري. تبدأ الرائحة بـالبرغموت، القرفة وبذور الجزر، ثم تتطور إلى قلب من العنبر، الأوسمانثوس وزهرة الخلود، وتستقر على قاعدة من العود، خشب الصندل والجلد.',
                'description' => 'Amber Nomad Louis Vuitton is a oriental amber fragrance built around a distinctive balanced scent. It opens with bergamot, cinnamon and carrot seeds, develops through a heart of amber, osmanthus and immortelle, and settles into a lasting base of oud, sandalwood and leather.',
            ],
            'Hawaii' => [
                'description_ar' => 'هاواي هو عطر للجنسين بطابع زهري استوائي. تبدأ الرائحة بـجوز الهند، الأناناس والبرغموت، ثم تتطور إلى قلب من الياسمين، Ylang-Ylang ونفحات بحرية، وتستقر على قاعدة من الفانيلا، المسك، خشب الصندل والعنبر.',
                'description' => 'Hawaii is a floral tropical fragrance built around a distinctive balanced scent. It opens with coconut, pineapple and bergamot, develops through a heart of jasmine, ylang-ylang and sea notes, and settles into a lasting base of vanilla, musk, sandalwood and amber.',
            ],
            'YSL Y' => [
                'description_ar' => 'عطر منعش وخشبي يجمع الحمضيات والأعشاب والتوابل مع قلب عطري، ثم يستقر على الأخشاب والعنبر والمسك بطابع نظيف وحديث.',
                'description' => 'YSL Y is a aromatic fougere fragrance built around crisp aromatic freshness. It opens with bergamot, ginger and apple, develops through a heart of sage, geranium, juniper and lavender, and settles into a lasting base of amberwood, tonka bean, cedar and vetiver.',
            ],
            'Khayali Marshmallow' => [
                'description_ar' => 'خيالي مارشميلو هو عطر للجنسين بطابع Oriental Gourmand. تبدأ الرائحة بـالمارشميلو، البرغموت والفانيلا، ثم تتطور إلى قلب من الياسمين، الورد وزهر البرتقال، وتستقر على قاعدة من الفانيلا، المسك، العنبر وخشب الصندل.',
                'description' => 'Khayali Marshmallow is a oriental gourmand fragrance built around a distinctive balanced scent. It opens with marshmallow, bergamot and vanilla, develops through a heart of jasmine, rose and orange blossom, and settles into a lasting base of vanilla, musk, amber and sandalwood.',
            ],
            'Thomas Casamula' => [
                'description_ar' => 'توماس كاسامولا هو عطر للجنسين بطابع شرقي تبغي. تبدأ الرائحة بـالبرغموت، الهيل والفلفل الوردي، ثم تتطور إلى قلب من التبغ، الجلد والزعفران، وتستقر على قاعدة من العود، العنبر، خشب الصندل والمسك.',
                'description' => 'Thomas Casamula is a oriental tobacco fragrance built around a distinctive balanced scent. It opens with bergamot, cardamom and pink pepper, develops through a heart of tobacco, leather and saffron, and settles into a lasting base of oud, amber, sandalwood and musk.',
            ],
            'Dior Homme Intense' => [
                'description_ar' => 'عطر أنيق بطابع بودري خشبي، يجمع السوسن والعنبر والكاكاو مع لمسات من الخزامى والميرمية، ثم يستقر على الفانيلا والجلد والصندل والباتشولي.',
                'description' => 'Dior Homme Intense is a oriental woody fragrance built around warm polished woods. It opens with lavender, sage, bergamot and cedar, develops through a heart of iris, amber and cacao, and settles into a lasting base of vanilla, leather, sandalwood and vetiver.',
            ],
            'Stronger With You' => [
                'description_ar' => 'عطر دافئ بطابع شرقي حلو، يبدأ بالهيل والفلفل والنعناع، ثم يظهر القرفة والميرمية والتوفي، وتنتهي الرائحة بالفانيلا والعنبر والتونكا والباتشولي.',
                'description' => 'Stronger With You is a oriental spicy gourmand fragrance built around a distinctive balanced scent. It opens with cardamom, pink pepper, mint and bergamot, develops through a heart of cinnamon, sage and toffee, and settles into a lasting base of vanilla, amber, tonka bean and patchouli.',
            ],
            'Idole' => [
                'description_ar' => 'ايدول هو عطر نسائي بطابع زهري خشبي. تبدأ الرائحة بـالكمثرى، البرغموت والفلفل الوردي، ثم تتطور إلى قلب من الورد، الياسمين وموغيه، وتستقر على قاعدة من المسك الأبيض، خشب الأرز، الفانيلا والباتشولي.',
                'description' => 'Idole is a floral woody fragrance built around a balanced floral-woody profile. It opens with pear, bergamot and pink pepper, develops through a heart of rose, jasmine and muguet, and settles into a lasting base of white musk, cedar, vanilla and patchouli.',
            ],
            'Black Lexus' => [
                'description_ar' => 'بلاك ليكزس هو عطر رجالي بطابع جلدي شرقي. تبدأ الرائحة بـالبرغموت، الفلفل الأسود والهيل، ثم تتطور إلى قلب من الجلد، العود والزعفران، وتستقر على قاعدة من العنبر، الباتشولي، خشب الصندل والمسك.',
                'description' => 'Black Lexus is a leather oriental fragrance built around a distinctive balanced scent. It opens with bergamot, black pepper and cardamom, develops through a heart of leather, oud and saffron, and settles into a lasting base of amber, patchouli, sandalwood and musk.',
            ],
            'Sea Passion' => [
                'description_ar' => 'سي باشن هو عطر للجنسين بطابع مائي زهري. تبدأ الرائحة بـنفحات بحرية، البرغموت والليمون، ثم تتطور إلى قلب من زنبق الماء، الياسمين وأكوردات بحرية، وتستقر على قاعدة من خشب الأرز، المسك، العنبر والخشب الطافي.',
                'description' => 'Sea Passion is a aquatic floral fragrance built around a distinctive balanced scent. It opens with sea notes, bergamot and lemon, develops through a heart of water lily, jasmine and marine accords, and settles into a lasting base of cedar, musk, amber and driftwood.',
            ],
            '3G' => [
                'description_ar' => 'ثري جي هو عطر للجنسين بطابع خشبي متبّل. تبدأ الرائحة بـالبرغموت، الجريب فروت والهيل، ثم تتطور إلى قلب من الزنجبيل، Geranium والخزامى، وتستقر على قاعدة من خشب الغاياك، أكوردات حلوة، العنبر والمسك.',
                'description' => '3G is a woody spicy fragrance built around a distinctive balanced scent. It opens with bergamot, grapefruit and cardamom, develops through a heart of ginger, geranium and lavender, and settles into a lasting base of guaiac wood, gourmand accord, amber and musk.',
            ],
            'Libre' => [
                'description_ar' => 'عطر نسائي زهري أنيق بطابع مشرق ودافئ، يوازن بين الحمضيات والأزهار البيضاء والخزامى مع قاعدة ناعمة من الفانيلا والمسك والأخشاب.',
                'description' => 'Libre is a aromatic fougere fragrance built around crisp aromatic freshness. It opens with lavender, mandarin orange, bergamot and neroli, develops through a heart of orange blossom, jasmine and ginger, and settles into a lasting base of vanilla, amber, musk and cedar.',
            ],
            'Darge' => [
                'description_ar' => 'دارج هو عطر للجنسين بطابع شرقي عودي. تبدأ الرائحة بـالزعفران، البرغموت والفلفل الوردي، ثم تتطور إلى قلب من العود، الورد والياسمين، وتستقر على قاعدة من العنبر، المسك، خشب الصندل والعود.',
                'description' => 'Darge is a oriental oud fragrance built around deep oud and amber. It opens with saffron, bergamot and pink pepper, develops through a heart of oud, rose and jasmine, and settles into a lasting base of amber, musk, sandalwood and oud.',
            ],
            'Silver Dust' => [
                'description_ar' => 'غبار الفضة هو عطر للجنسين بطابع شرقي بودري. تبدأ الرائحة بـالبرغموت، الزعفران والفلفل الوردي، ثم تتطور إلى قلب من السوسن، العود والورد، وتستقر على قاعدة من المسك الفضي، العنبر، خشب الصندل والباتشولي.',
                'description' => 'Silver Dust is a oriental powdery fragrance built around a distinctive balanced scent. It opens with bergamot, saffron and pink pepper, develops through a heart of iris, oud and rose, and settles into a lasting base of silver musk, amber, sandalwood and patchouli.',
            ],
            'Bouquets Rouge' => [
                'description_ar' => 'بكرات روج هو عطر نسائي بطابع زهري خشبي. تبدأ الرائحة بـالتوت الأحمر، البرغموت والفلفل الوردي، ثم تتطور إلى قلب من الورد، الفاوانيا والياسمين، وتستقر على قاعدة من الباتشولي، المسك، الفانيلا وخشب الأرز.',
                'description' => 'Bouquets Rouge is a floral woody fragrance built around a balanced floral-woody profile. It opens with red berries, bergamot and pink pepper, develops through a heart of rose, peony and jasmine, and settles into a lasting base of patchouli, musk, vanilla and cedar.',
            ],
            'Voyage' => [
                'description_ar' => 'فوياج هو عطر للجنسين بطابع خشبي عطري. تبدأ الرائحة بـالبرغموت، اليوسفي والهيل، ثم تتطور إلى قلب من الياسمين، خشب الأرز وهيديون، وتستقر على قاعدة من إيزو إي سوبر، المسك، العنبر وخشب الصندل.',
                'description' => 'Voyage is a woody aromatic fragrance built around clean woody freshness. It opens with bergamot, mandarin orange and cardamom, develops through a heart of jasmine, cedar and hedione, and settles into a lasting base of iso e super, musk, amber and sandalwood.',
            ],
            'Fahrenheit' => [
                'description_ar' => 'عطر كلاسيكي مميز تتداخل فيه أوراق البنفسج واليوسفي مع الخشب والتوابل، ثم يبرز الجلد ونجيل الهند والصندل والباتشولي في القاعدة.',
                'description' => 'Fahrenheit is a woody floral musk fragrance built around a distinctive balanced scent. It opens with violet leaf, honeysuckle, hawthorn and mandarin orange, develops through a heart of cedar, violet, jasmine and nutmeg, and settles into a lasting base of leather, vetiver, sandalwood and patchouli.',
            ],
            'White Musk' => [
                'description_ar' => 'مسك ابيض هو عطر للجنسين بطابع Floral Musk. تبدأ الرائحة بـالبرغموت، الليمون وGreen Notes، ثم تتطور إلى قلب من المسك الأبيض، الياسمين، الورد والزنبق، وتستقر على قاعدة من المسك الأبيض، الفانيلا، خشب الصندل والعنبر.',
                'description' => 'White Musk is a floral musk fragrance built around a distinctive balanced scent. It opens with bergamot, lemon and green notes, develops through a heart of white musk, jasmine, rose and lily, and settles into a lasting base of white musk, vanilla, sandalwood and amber.',
            ],
            'Hugo Boss Men' => [
                'description_ar' => 'هوجو بوس رجالي هو عطر رجالي بطابع فوجير عطري. تبدأ الرائحة بـالبرغموت، الليمون، التفاح الأخضر والنعناع، ثم تتطور إلى قلب من الخزامى، Geranium، القرفة والقرنفل، وتستقر على قاعدة من خشب الصندل، خشب الأرز، نجيل الهند والزيتون.',
                'description' => 'Hugo Boss Men is a aromatic fougere fragrance built around crisp aromatic freshness. It opens with bergamot, lemon, green apple and mint, develops through a heart of lavender, geranium, cinnamon and clove, and settles into a lasting base of sandalwood, cedar, vetiver and olives.',
            ],
            'Cigar' => [
                'description_ar' => 'سيجار هو عطر رجالي بطابع شرقي تبغي. تبدأ الرائحة بـالجريب فروت، اليوسفي والبرغموت، ثم تتطور إلى قلب من التبغ، القرفة، خشب الأرز والباتشولي، وتستقر على قاعدة من الجلد، العنبر، حبوب التونكا والفانيلا.',
                'description' => 'Cigar is a oriental tobacco fragrance built around a distinctive balanced scent. It opens with grapefruit, mandarin orange and bergamot, develops through a heart of tobacco, cinnamon, cedar and patchouli, and settles into a lasting base of leather, amber, tonka bean and vanilla.',
            ],
            'Tuxedo' => [
                'description_ar' => 'تركيبة خشبية جلدية أنيقة تجمع البرغموت والفلفل الأسود والهيل مع الميرمية والخزامى والسوسن، ثم تستقر على العنبر والجلد والصندل والباتشولي.',
                'description' => 'Tuxedo is a woody leather fragrance built around smooth woody leather. It opens with bergamot, black pepper and cardamom, develops through a heart of sage, lavender and iris, and settles into a lasting base of amber, leather, sandalwood and patchouli.',
            ],
            'Grace Chanel' => [
                'description_ar' => 'غريس شارنيل هو عطر نسائي بطابع Floral Aldehydic. تبدأ الرائحة بـالبرغموت، الألدهيدات وNeroli، ثم تتطور إلى قلب من الياسمين، الورد، زنبق الوادي والفريزيا، وتستقر على قاعدة من المسك، نجيل الهند، خشب الأرز والعنبر.',
                'description' => 'Grace Chanel is a floral aldehydic fragrance built around a distinctive balanced scent. It opens with bergamot, aldehydes and neroli, develops through a heart of jasmine, rose, lily-of-the-valley and freesia, and settles into a lasting base of musk, vetiver, cedar and amber.',
            ],
            'Stronger With You Intensely' => [
                'description_ar' => 'نسخة أكثر كثافة وحلاوة بطابع شرقي متبّل، تبرز فيها الفلفلة والعرعر والقرفة والتوفي، مع قاعدة من الفانيلا والعنبر والتونكا والشمواه.',
                'description' => 'Stronger With You Intensely is a oriental spicy gourmand fragrance built around a distinctive balanced scent. It opens with pink pepper, juniper and black pepper, develops through a heart of lavender, cinnamon, toffee and iris, and settles into a lasting base of vanilla, amber, tonka bean and suede.',
            ],
            'Musk Tout' => [
                'description_ar' => 'مسك توت هو عطر للجنسين بطابع Floral Musk. تبدأ الرائحة بـالبرغموت والألدهيدات، ثم تتطور إلى قلب من المسك الأبيض، الياسمين والورد، وتستقر على قاعدة من المسك، الفانيلا، خشب الصندل والعنبر.',
                'description' => 'Musk Tout is a floral musk fragrance built around a distinctive balanced scent. It opens with bergamot and aldehydes, develops through a heart of white musk, jasmine and rose, and settles into a lasting base of musk, vanilla, sandalwood and amber.',
            ],
            'Musk Powder Oil' => [
                'description_ar' => 'مسك باودر زيت هو عطر للجنسين بطابع Powdery Musk. تبدأ الرائحة بـالبرغموت ونفحات بودرية، ثم تتطور إلى قلب من المسك الأبيض، السوسن والبنفسج، وتستقر على قاعدة من المسك، الفانيلا، خشب الصندل ونفحات بودرية.',
                'description' => 'Musk Powder Oil is a powdery musk fragrance built around a distinctive balanced scent. It opens with bergamot and powdery notes, develops through a heart of white musk, iris and violet, and settles into a lasting base of musk, vanilla, sandalwood and powdery notes.',
            ],
            'Musk Tahara' => [
                'description_ar' => 'مسك طهارة هو عطر للجنسين بطابع Clean Musk. تبدأ الرائحة بـالبرغموت، الليمون ونفحات زهرية، ثم تتطور إلى قلب من المسك الأبيض، الورد والياسمين، وتستقر على قاعدة من المسك، الفانيلا وخشب الصندل.',
                'description' => 'Musk Tahara is a clean musk fragrance built around a distinctive balanced scent. It opens with bergamot, lemon and floral notes, develops through a heart of white musk, rose and jasmine, and settles into a lasting base of musk, vanilla and sandalwood.',
            ],
            'Musk Cherry' => [
                'description_ar' => 'مسك كرز هو عطر للجنسين بطابع Fruity Musk. تبدأ الرائحة بـالكرز، البرغموت والتوت الأحمر، ثم تتطور إلى قلب من المسك الأبيض، الورد والياسمين، وتستقر على قاعدة من المسك، الفانيلا، العنبر وخشب الصندل.',
                'description' => 'Musk Cherry is a fruity musk fragrance built around a distinctive balanced scent. It opens with cherry, bergamot and red berries, develops through a heart of white musk, rose and jasmine, and settles into a lasting base of musk, vanilla, amber and sandalwood.',
            ],
            'Musk Athara' => [
                'description_ar' => 'مسك اثارة هو عطر للجنسين بطابع Oriental Musk. تبدأ الرائحة بـالبرغموت، الزعفران والهيل، ثم تتطور إلى قلب من المسك الأبيض، العود والورد، وتستقر على قاعدة من المسك، العنبر، خشب الصندل والعود.',
                'description' => 'Musk Athara is a oriental musk fragrance built around a distinctive balanced scent. It opens with bergamot, saffron and cardamom, develops through a heart of white musk, oud and rose, and settles into a lasting base of musk, amber, sandalwood and oud.',
            ],
            'Hamoul' => [
                'description_ar' => 'هامول هو عطر للجنسين بطابع شرقي عودي. تبدأ الرائحة بـالزعفران، البرغموت والهيل، ثم تتطور إلى قلب من العود، الورد والعنبر، وتستقر على قاعدة من المسك، خشب الصندل، العنبر والعود.',
                'description' => 'Hamoul is a oriental oud fragrance built around deep oud and amber. It opens with saffron, bergamot and cardamom, develops through a heart of oud, rose and amber, and settles into a lasting base of musk, sandalwood, amber and oud.',
            ],
            'Musk Pomegranate' => [
                'description_ar' => 'مسك رمان هو عطر للجنسين بطابع Fruity Musk. تبدأ الرائحة بـالرمان، البرغموت والتوت الأحمر، ثم تتطور إلى قلب من المسك الأبيض، الورد والفاوانيا، وتستقر على قاعدة من المسك، الفانيلا، العنبر وخشب الصندل.',
                'description' => 'Musk Pomegranate is a fruity musk fragrance built around a distinctive balanced scent. It opens with pomegranate, bergamot and red berries, develops through a heart of white musk, rose and peony, and settles into a lasting base of musk, vanilla, amber and sandalwood.',
            ],
            'Jasmine' => [
                'description_ar' => 'ياسمين هو عطر نسائي بطابع زهري أبيض. تبدأ الرائحة بـالبرغموت، اليوسفي وGreen Notes، ثم تتطور إلى قلب من الياسمين، الياسمين السامباك وNeroli، وتستقر على قاعدة من المسك الأبيض، خشب الصندل وخشب الأرز.',
                'description' => 'Jasmine is a floral white fragrance built around a distinctive balanced scent. It opens with bergamot, mandarin orange and green notes, develops through a heart of jasmine, jasmine sambac and neroli, and settles into a lasting base of white musk, sandalwood and cedar.',
            ],
        ];

        $updated = 0;
        foreach ($items as $name => $data) {
            $updated += DB::table('products')->where('name', $name)->update([
                'description_ar' => $data['description_ar'],
                'description' => $data['description'],
                'updated_at' => now(),
            ]);
        }

        $this->command?->info("  ✓ Product descriptions updated for {$updated} products");
    }
}
