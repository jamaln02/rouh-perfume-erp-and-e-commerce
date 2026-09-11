import { useMemo, useState } from "react";
import { useLanguage } from "@/hooks/useLanguage";
import { useAuth } from "@/hooks/useAuth";
import { useStorefrontConfig } from "@/hooks/useStorefrontConfig";
import { useProducts, type ProductView } from "@/hooks/useProducts";
import { motion, AnimatePresence } from "framer-motion";
import { Sparkles, Gift, ArrowRight, ArrowLeft, Copy, Loader2 } from "lucide-react";
import { toast } from "sonner";
import { Link } from "react-router-dom";
import SEO from "@/components/SEO";

type QuestionSignal = {
  families?: string[];
  notes?: string[];
  styles?: string[];
};

type QuestionOption = {
  ar: string;
  en: string;
  signals: QuestionSignal;
};

type Question = {
  ar: string;
  en: string;
  options: QuestionOption[];
};

type QuizResult = {
  perfume: ProductView;
  code: string;
  discountPercent: number;
  matchedNotes: string[];
  matchedFamily: string;
};

const questions: Question[] = [
  {
    ar: "كيف تصف شخصيتك؟",
    en: "How would you describe your personality?",
    options: [
      {
        ar: "جريء ومغامر", en: "Bold & Adventurous",
        signals: {
          styles: ["bold", "adventurous", "spicy"],
          families: ["oriental spicy", "woody spicy", "aromatic spicy", "oriental oud", "woody leather"],
          notes: ["black pepper", "pink pepper", "cinnamon", "saffron", "oud", "leather", "incense", "tobacco"],
        },
      },
      {
        ar: "أنيق وكلاسيكي", en: "Elegant & Classic",
        signals: {
          styles: ["elegant", "classic", "refined"],
          families: ["floral woody", "woody aromatic", "woody floral", "aromatic fougere", "leather amber"],
          notes: ["cedar", "sandalwood", "vetiver", "lavender", "iris", "amber", "musk"],
        },
      },
      {
        ar: "رومانسي وحساس", en: "Romantic & Sensitive",
        signals: {
          styles: ["romantic", "soft", "sensual"],
          families: ["floral fruity", "floral musk", "floral oriental", "powdery musk", "floral white", "floral vanilla"],
          notes: ["rose", "jasmine", "peony", "violet", "white musk", "vanilla", "iris", "orange blossom"],
        },
      },
      {
        ar: "غامض ومثير", en: "Mysterious & Intriguing",
        signals: {
          styles: ["mysterious", "dark", "deep"],
          families: ["oriental oud", "oriental woody", "oriental amber", "oriental tobacco", "leather oriental", "woody oriental"],
          notes: ["oud", "incense", "amber", "patchouli", "tobacco", "myrrh", "leather", "saffron"],
        },
      },
    ],
  },
  {
    ar: "ما هو موسمك المفضل؟",
    en: "What is your favorite season?",
    options: [
      {
        ar: "الصيف — أحب الانتعاش والحيوية", en: "Summer — I love freshness & energy",
        signals: {
          styles: ["fresh", "clean", "airy"],
          families: ["aquatic aromatic", "aquatic floral", "citrus aquatic", "aromatic citrus", "aromatic green"],
          notes: ["bergamot", "lemon", "grapefruit", "mandarin", "orange", "neroli", "mint", "marine notes", "sea notes", "green notes"],
        },
      },
      {
        ar: "الشتاء — أحب الدفء والغموض", en: "Winter — I love warmth & mystery",
        signals: {
          styles: ["warm", "deep", "cozy"],
          families: ["oriental oud", "oriental amber", "oriental vanilla", "oriental tobacco", "oriental gourmand", "oriental spicy"],
          notes: ["vanilla", "amber", "oud", "cinnamon", "tonka bean", "tobacco", "incense", "sandalwood", "praline"],
        },
      },
      {
        ar: "الربيع — أحب التجدد والرومانسية", en: "Spring — I love renewal & romance",
        signals: {
          styles: ["floral", "romantic", "light"],
          families: ["floral fruity", "floral white", "floral musk", "floral tropical", "aquatic floral"],
          notes: ["rose", "jasmine", "peony", "violet", "orange blossom", "neroli", "ylang-ylang", "lily", "fruity notes"],
        },
      },
      {
        ar: "الخريف — أحب الأناقة والهدوء", en: "Autumn — I love elegance & calm",
        signals: {
          styles: ["elegant", "woody", "calm"],
          families: ["woody aromatic", "woody floral", "woody leather", "oriental woody", "floral woody"],
          notes: ["sandalwood", "cedar", "vetiver", "patchouli", "leather", "amber", "tea", "tobacco"],
        },
      },
    ],
  },
  {
    ar: "ما الانطباع الذي تريد تركه؟",
    en: "What impression do you want to leave?",
    options: [
      {
        ar: "القوة والثقة", en: "Power & Confidence",
        signals: {
          styles: ["bold", "strong", "confident"],
          families: ["oriental oud", "oriental spicy", "woody spicy", "leather amber", "oriental tobacco"],
          notes: ["oud", "saffron", "amber", "tobacco", "leather", "pepper", "incense", "patchouli"],
        },
      },
      {
        ar: "الفخامة والرقي", en: "Luxury & Sophistication",
        signals: {
          styles: ["luxurious", "elegant", "opulent"],
          families: ["oriental amber", "oriental oud", "oriental gourmand", "floral oriental", "oriental vanilla"],
          notes: ["saffron", "oud", "amber", "vanilla", "praline", "rose", "jasmine", "incense"],
        },
      },
      {
        ar: "الانتعاش والنشاط", en: "Freshness & Energy",
        signals: {
          styles: ["fresh", "energetic", "clean"],
          families: ["aquatic aromatic", "citrus aquatic", "aromatic citrus", "aromatic green"],
          notes: ["bergamot", "lemon", "grapefruit", "mint", "sea notes", "marine notes", "ginger", "rosemary"],
        },
      },
      {
        ar: "الجاذبية والإغراء", en: "Attraction & Seduction",
        signals: {
          styles: ["sensual", "romantic", "seductive"],
          families: ["floral vanilla", "floral gourmand", "floral musk", "oriental gourmand", "oriental floral"],
          notes: ["vanilla", "musk", "rose", "jasmine", "caramel", "honey", "tonka bean", "white flowers"],
        },
      },
    ],
  },
  {
    ar: "أي طابع عطري يجذبك أكثر؟",
    en: "Which scent profile attracts you most?",
    options: [
      {
        ar: "العود والبخور", en: "Oud & Incense",
        signals: {
          styles: ["oriental", "smoky", "mysterious"],
          families: ["oriental oud", "oriental woody", "oriental amber", "oriental tobacco", "leather oriental"],
          notes: ["oud", "incense", "myrrh", "saffron", "amber", "leather"],
        },
      },
      {
        ar: "الأزهار والمسك", en: "Flowers & Musk",
        signals: {
          styles: ["floral", "soft", "romantic"],
          families: ["floral musk", "floral white", "floral fruity", "powdery musk", "clean musk"],
          notes: ["rose", "jasmine", "white musk", "musk", "iris", "violet", "lily", "peony"],
        },
      },
      {
        ar: "الحمضيات والنعناع", en: "Citrus & Mint",
        signals: {
          styles: ["fresh", "clean", "sporty"],
          families: ["aromatic citrus", "citrus aquatic", "aquatic aromatic", "aromatic green"],
          notes: ["bergamot", "lemon", "grapefruit", "mandarin", "orange", "mint", "lime", "rosemary"],
        },
      },
      {
        ar: "الفانيلا والتوابل", en: "Vanilla & Spices",
        signals: {
          styles: ["warm", "sweet", "luxurious"],
          families: ["oriental vanilla", "oriental gourmand", "floral vanilla", "oriental spicy gourmand", "fruity gourmand"],
          notes: ["vanilla", "tonka bean", "cinnamon", "cardamom", "saffron", "praline", "caramel", "chestnut"],
        },
      },
    ],
  },
  {
    ar: "متى تستخدم العطر عادة؟",
    en: "When do you usually wear perfume?",
    options: [
      {
        ar: "يومياً للعمل", en: "Daily for work",
        signals: {
          styles: ["clean", "refined", "versatile"],
          families: ["aromatic fougere", "woody aromatic", "aromatic citrus", "aquatic aromatic"],
          notes: ["bergamot", "lavender", "cedar", "vetiver", "musk", "rosemary", "green notes"],
        },
      },
      {
        ar: "المناسبات الخاصة", en: "Special occasions",
        signals: {
          styles: ["luxurious", "elegant", "rich"],
          families: ["oriental oud", "oriental amber", "floral oriental", "oriental gourmand", "floral gourmand"],
          notes: ["oud", "saffron", "amber", "vanilla", "rose", "jasmine", "incense", "praline"],
        },
      },
      {
        ar: "السهرات والحفلات", en: "Evenings & parties",
        signals: {
          styles: ["bold", "sensual", "intense"],
          families: ["oriental spicy", "woody spicy", "oriental spicy gourmand", "oriental tobacco", "woody leather"],
          notes: ["pepper", "cinnamon", "vanilla", "tobacco", "leather", "amber", "patchouli"],
        },
      },
      {
        ar: "الرياضة والنشاطات", en: "Sports & activities",
        signals: {
          styles: ["fresh", "sporty", "energetic"],
          families: ["aquatic aromatic", "citrus aquatic", "aromatic citrus", "aromatic green"],
          notes: ["marine notes", "sea notes", "bergamot", "lemon", "grapefruit", "mint", "ginger", "rosemary"],
        },
      },
    ],
  },
];

const normalise = (value: string) =>
  value
    .toLocaleLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9\s]/g, " ")
    .replace(/\s+/g, " ")
    .trim();

const containsTerm = (haystack: string, term: string) => {
  const target = normalise(term);
  return target.length > 0 && haystack.includes(target);
};

const scoreProduct = (product: ProductView, selectedOptions: QuestionOption[]) => {
  const family = normalise(product.fragranceFamily || product.fragrance || "");
  const noteEntries = [...product.topNotes, ...product.heartNotes, ...product.baseNotes]
    .map((note) => ({ raw: note, normalised: normalise(note) }))
    .filter((entry) => entry.normalised.length > 0);
  const allNotes = noteEntries.map((entry) => entry.normalised);
  const noteText = allNotes.join(" | ");
  const searchable = `${family} ${noteText}`;
  let score = 0;
  let matchedNoteCount = 0;
  const matchedNotes = new Set<string>();

  selectedOptions.forEach((option) => {
    const signals = option.signals;

    const familyHits = (signals.families || []).filter((candidate) => containsTerm(family, candidate));
    if (familyHits.length > 0) {
      // One family match is already strong; cap it so products with many labels
      // do not dominate purely because their family name is longer.
      score += 5;
    }

    const matchedProductNotes = noteEntries.filter(({ normalised: note }) =>
      (signals.notes || []).some((candidate) => containsTerm(note, candidate) || containsTerm(candidate, note))
    );
    if (matchedProductNotes.length > 0) {
      score += Math.min(4, 4 * (matchedProductNotes.length / Math.max(2, Math.min(6, (signals.notes || []).length))));
      matchedNoteCount += matchedProductNotes.length;
      matchedProductNotes.forEach(({ raw }) => matchedNotes.add(raw));
    }

    const styleHits = (signals.styles || []).filter((candidate) => containsTerm(searchable, candidate));
    if (styleHits.length > 0) {
      score += 1.5;
    }
  });

  // Small consistency bonus: products matching notes across different pyramid
  // levels are generally a better fit than a single-note coincidence.
  const populatedLevels = [product.topNotes, product.heartNotes, product.baseNotes].filter((level) => level.length > 0).length;
  const matchingLevels = [product.topNotes, product.heartNotes, product.baseNotes].filter((level) =>
    level.some((note) => selectedOptions.some((option) =>
      (option.signals.notes || []).some((target) => containsTerm(note, target) || containsTerm(target, note))
    ))
  ).length;
  if (populatedLevels > 0 && matchingLevels > 0) {
    score += Math.min(3, matchingLevels * 1.25);
  }

  return { score, matchedNotes: [...matchedNotes], matchedNoteCount };
};

const Quiz = () => {
  const { lang } = useLanguage();
  const { user, loading: authLoading } = useAuth();
  const { config: storeConfig } = useStorefrontConfig();
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "");
  const { products, loading: productsLoading } = useProducts();
  const [step, setStep] = useState(0);
  const [answers, setAnswers] = useState<QuestionOption[]>([]);
  const [phone, setPhone] = useState("");
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<QuizResult | null>(null);
  const [alreadyUsed, setAlreadyUsed] = useState(false);

  const normalizePhone = (value: string) => value.replace(/[\s\-()]/g, "").trim();

  const recommendation = useMemo(() => {
    if (products.length === 0 || answers.length !== questions.length) return null;

    const ranked = products
      .map((product) => ({ product, ...scoreProduct(product, answers) }))
      .sort((a, b) => {
        if (b.score !== a.score) return b.score - a.score;
        if (b.matchedNoteCount !== a.matchedNoteCount) return b.matchedNoteCount - a.matchedNoteCount;
        return a.product.name.localeCompare(b.product.name);
      });

    return ranked[0] || null;
  }, [answers, products]);

  const handleAnswer = (option: QuestionOption) => {
    setAnswers((current) => [...current, option]);
    setStep((current) => current + 1);
  };

  const handleSubmitPhone = async () => {
    if (!user) {
      toast.error(lang === "ar" ? "يجب تسجيل الدخول أولاً لإكمال الاختبار والحصول على الخصم" : "Please sign in before completing the quiz and receiving the discount.");
      return;
    }
    if (!phone || phone.length < 9) {
      toast.error(lang === "ar" ? "يرجى إدخال رقم هاتف صحيح" : "Please enter a valid phone number");
      return;
    }

    if (normalizePhone(phone) !== normalizePhone(user.phone || "")) {
      toast.error(lang === "ar" ? "رقم الهاتف يجب أن يطابق الرقم المسجل بحسابك" : "The phone number must match the phone registered on your account.");
      return;
    }

    if (!recommendation?.product) {
      toast.error(lang === "ar" ? "تعذر العثور على المنتجات المناسبة حالياً، حاول مرة أخرى" : "We could not load the fragrance catalog. Please try again.");
      return;
    }

    setLoading(true);
    try {
      const perfume = recommendation.product;
      const response = await fetch(`${apiBaseUrl}/api/submit-quiz`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        credentials: "include",
        body: JSON.stringify({
          phone,
          recommended_product: perfume.name,
          recommended_product_ar: perfume.nameAr,
        }),
      });

      const data = await response.json();
      if (response.status === 409 || data?.reason === "already_used") {
        setAlreadyUsed(true);
        toast.info(lang === "ar" ? "كود اختبار العطر استُخدم مسبقاً ولا يمكن استخدامه مرة ثانية" : "Your perfume quiz discount has already been used and cannot be reused.");
        return;
      }
      if (response.status === 401 || response.status === 403) {
        toast.error(lang === "ar" ? "يجب تسجيل الدخول واستخدام الرقم المسجل بالحساب" : "Sign in and use the phone number registered to your account.");
        return;
      }
      if (!response.ok || !data) {
        throw new Error(data?.message || "failed");
      }

      const resolvedPerfume = data.existing
        ? products.find((p) => p.name === data.recommended_product || p.nameAr === data.recommended_product_ar) || perfume
        : perfume;

      const resolvedMatch = scoreProduct(resolvedPerfume, answers);
      setAlreadyUsed(Boolean(data.existing));
      setResult({
        perfume: resolvedPerfume,
        code: data.discount_code,
        discountPercent: Number(data.discount_percent || storeConfig.quiz.discount_percent || 10),
        matchedNotes: resolvedMatch.matchedNotes.slice(0, 5),
        matchedFamily: resolvedPerfume.fragranceFamily || resolvedPerfume.fragrance || "",
      });
      setStep(7);
    } catch (err) {
      toast.error(lang === "ar" ? "حدث خطأ، حاول مرة أخرى" : "An error occurred, please try again");
    } finally {
      setLoading(false);
    }
  };

  const copyCode = () => {
    if (result) {
      navigator.clipboard.writeText(result.code);
      toast.success(lang === "ar" ? "تم نسخ الكود!" : "Code copied!");
    }
  };

  const currentQuestion = step >= 1 && step <= questions.length ? questions[step - 1] : null;
  const progress = step <= questions.length ? (step / questions.length) * 100 : 100;
  const canStart = !productsLoading && products.length > 0;

  return (
    <div className="min-h-screen pt-20 pb-12 bg-gradient-to-b from-background via-background to-card/50">
      <SEO
        title={lang === "ar" ? "اختبار العطر الشخصي | روح" : "Perfume Personality Quiz | Rouh"}
        description={lang === "ar" ? "أجب على 5 أسئلة عن ذوقك وشخصيتك لنطابقك مع عطر اعتماداً على النوتات والعائلة العطرية." : "Answer 5 questions about your style and taste and get a fragrance matched from its real notes and fragrance family."}
        path="/quiz"
      />

      <div className="container mx-auto px-4 max-w-3xl">
        <div className="text-center mb-8">
          <Sparkles className="mx-auto text-gold mb-4" size={40} />
          <h1 className="font-display text-3xl md:text-4xl font-bold text-gradient-gold mb-2">
            {lang === "ar" ? "اكتشف عطرك المثالي" : "Find Your Perfect Scent"}
          </h1>
          <p className="text-muted-foreground">
            {lang === "ar" ? "5 أسئلة بسيطة، والاختيار يعتمد على نوتات العطر الموجودة فعلياً في كتالوجنا." : "5 simple questions, matched against the actual notes in our current catalog."}
          </p>
        </div>

        {productsLoading && (
          <div className="mb-6 rounded-xl border border-gold/20 bg-gold/5 px-4 py-3 text-sm text-gold flex items-center justify-center gap-2">
            <Loader2 className="h-4 w-4 animate-spin" />
            {lang === "ar" ? "جاري تحميل مجموعة العطور..." : "Loading the fragrance catalog..."}
          </div>
        )}

        {step === 0 && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="bg-card border border-border rounded-3xl p-8 md:p-12 text-center shadow-lg"
          >
            <div className="w-24 h-24 rounded-full bg-gold/10 flex items-center justify-center mx-auto mb-6">
              <Sparkles className="text-gold" size={42} />
            </div>
            <h2 className="font-display text-2xl font-bold text-foreground mb-3">
              {lang === "ar" ? "اختبار أذواقك العطرية" : "Your Fragrance Profile"}
            </h2>
            <p className="text-muted-foreground leading-relaxed mb-4">
              {lang === "ar"
                ? "النتيجة لم تعد مبنية على أسماء شخصية ثابتة فقط. نحلل إجاباتك مقابل العائلة العطرية ونوتات البداية والقلب والقاعدة لكل منتج متوفر حالياً."
                : "Your result is no longer based on a small static personality list. We compare your answers with each available product's fragrance family and top, heart, and base notes."}
            </p>
            <p className="text-sm text-muted-foreground/80 mb-8">
              {user ? (lang === "ar" ? `سيتم التحقق من رقم هاتفك المسجل قبل إظهار النتيجة وكود الخصم ${storeConfig.quiz.discount_percent}%.` : `Your registered phone will be verified before we reveal your result and ${storeConfig.quiz.discount_percent}% discount code.`) : (lang === "ar" ? "سجّل الدخول أولاً، ويجب استخدام رقم الهاتف المسجل بحسابك لإظهار النتيجة وكود الخصم." : "Sign in first. Your registered phone must match before the result and discount code can be revealed.")}
            </p>
            {user ? (
              <button
                onClick={() => setStep(1)}
                disabled={!canStart || authLoading}
                className="inline-flex items-center justify-center gap-2 bg-gradient-gold text-accent-foreground px-10 py-4 rounded-full font-bold text-lg hover:shadow-gold-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {productsLoading ? (lang === "ar" ? "جاري التحميل..." : "Loading...") : (lang === "ar" ? "ابدأ الاختبار" : "Start Quiz")}
                {lang === "ar" ? <ArrowLeft size={20} /> : <ArrowRight size={20} />}
              </button>
            ) : (
              <Link to="/auth?redirect=/quiz" className="inline-flex items-center justify-center gap-2 bg-gradient-gold text-accent-foreground px-10 py-4 rounded-full font-bold text-lg hover:shadow-gold-lg transition-all">
                {lang === "ar" ? "سجّل الدخول لبدء الاختبار" : "Sign in to start the quiz"}
                {lang === "ar" ? <ArrowLeft size={20} /> : <ArrowRight size={20} />}
              </Link>
            )}
          </motion.div>
        )}

        {currentQuestion && (
          <AnimatePresence mode="wait">
            <motion.div
              key={step}
              initial={{ opacity: 0, x: lang === "ar" ? 30 : -30 }}
              animate={{ opacity: 1, x: 0 }}
              exit={{ opacity: 0, x: lang === "ar" ? -30 : 30 }}
              className="bg-card border border-border rounded-3xl p-6 md:p-10 shadow-lg"
            >
              <div className="mb-8">
                <div className="flex items-center justify-between text-sm text-muted-foreground mb-2">
                  <span>{lang === "ar" ? `السؤال ${step} من ${questions.length}` : `Question ${step} of ${questions.length}`}</span>
                  <span>{Math.round(progress)}%</span>
                </div>
                <div className="h-2 bg-muted rounded-full overflow-hidden">
                  <motion.div
                    initial={{ width: 0 }}
                    animate={{ width: `${progress}%` }}
                    className="h-full bg-gradient-gold rounded-full"
                  />
                </div>
              </div>

              <h2 className="font-display text-2xl md:text-3xl font-bold text-foreground text-center mb-8">
                {lang === "ar" ? currentQuestion.ar : currentQuestion.en}
              </h2>

              <div className="grid gap-3">
                {currentQuestion.options.map((option) => (
                  <button
                    key={option.en}
                    onClick={() => handleAnswer(option)}
                    className="w-full text-start p-5 rounded-2xl border-2 border-border bg-background hover:border-gold hover:bg-gold/5 transition-all duration-200 text-foreground font-medium"
                  >
                    <span className="block text-base">{lang === "ar" ? option.ar : option.en}</span>
                  </button>
                ))}
              </div>
            </motion.div>
          </AnimatePresence>
        )}

        {step === 6 && (
          <motion.div
            initial={{ opacity: 0, scale: 0.98 }}
            animate={{ opacity: 1, scale: 1 }}
            className="bg-card border border-border rounded-3xl p-8 md:p-12 shadow-lg text-center"
          >
            <div className="w-20 h-20 rounded-full bg-gold/10 flex items-center justify-center mx-auto mb-6">
              <Gift className="text-gold" size={34} />
            </div>
            <h2 className="font-display text-2xl md:text-3xl font-bold text-foreground mb-3">
              {lang === "ar" ? "وصلنا لآخر خطوة" : "One last step"}
            </h2>
            <p className="text-muted-foreground mb-8">
              {lang === "ar" ? `أدخل رقم هاتفك المسجل بحسابك لنكشف العطر الأنسب لك وكود خصم ${storeConfig.quiz.discount_percent}%.` : `Enter the phone number registered on your account to reveal your best match and your ${storeConfig.quiz.discount_percent}% discount code.`}
            </p>
            <input
              type="tel"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              placeholder={lang === "ar" ? "+9639XXXXXXXX" : "+9639XXXXXXXX"}
              className="w-full max-w-md mx-auto block h-12 rounded-xl border border-border bg-background px-4 text-center text-lg text-foreground focus:outline-none focus:ring-2 focus:ring-gold/40"
              dir="ltr"
            />
            <p className="mt-2 text-xs text-muted-foreground">
              {user?.phone ? (lang === "ar" ? `الرقم المسجل بحسابك: ${user.phone}` : `Registered phone: ${user.phone}`) : (lang === "ar" ? "يجب تسجيل الدخول أولاً" : "You must be signed in first")}
            </p>
            <button
              onClick={() => void handleSubmitPhone()}
              disabled={loading || !recommendation?.product || !user || authLoading}
              className="mt-5 inline-flex items-center justify-center gap-2 bg-gradient-gold text-accent-foreground px-8 py-3 rounded-full font-bold disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {loading ? <Loader2 className="h-5 w-5 animate-spin" /> : <Sparkles size={18} />}
              {lang === "ar" ? "اكشف عطرك!" : "Reveal your scent!"}
            </button>

            <div className="mt-8 rounded-2xl border border-gold/20 bg-gold/5 p-4 text-start">
              <p className="text-sm font-semibold text-gold mb-1">
                {lang === "ar" ? "كيف يتم الاختيار؟" : "How do we choose?"}
              </p>
              <p className="text-xs leading-relaxed text-muted-foreground">
                {lang === "ar"
                  ? "نوازن بين العائلة العطرية والنوتات التي تتطابق مع إجاباتك، ونمنح أفضلية إضافية للعطور التي تطابق أكثر من مستوى في هرم النوتات."
                  : "We score fragrance-family matches and note matches, with a small bonus when your preferences match multiple levels of the note pyramid."}
              </p>
            </div>
          </motion.div>
        )}

        {step === 7 && result && (
          <motion.div
            initial={{ opacity: 0, scale: 0.96 }}
            animate={{ opacity: 1, scale: 1 }}
            className="py-8 text-center"
          >
            <motion.div
              initial={{ scale: 0 }}
              animate={{ scale: 1 }}
              transition={{ type: "spring", delay: 0.2 }}
              className="w-20 h-20 rounded-full bg-gold/20 flex items-center justify-center mx-auto mb-6"
            >
              <Sparkles className="text-gold" size={36} />
            </motion.div>

            <h2 className="font-display text-3xl md:text-4xl font-bold text-gradient-gold mb-2">
              {lang === "ar" ? "عطرك الأقرب لذوقك هو" : "Your closest scent match is"}
            </h2>

            <div className="bg-card border border-border rounded-3xl p-5 md:p-8 mt-6 mb-6 text-start">
              <div className="grid md:grid-cols-[160px_1fr] gap-6 items-center">
                <img
                  src={result.perfume.image}
                  alt={lang === "ar" ? result.perfume.nameAr : result.perfume.name}
                  className="w-40 h-40 rounded-2xl object-cover mx-auto md:mx-0 bg-muted"
                  loading="eager"
                />
                <div>
                  <h3 className="font-display text-2xl font-bold text-foreground mb-1">
                    {lang === "ar" ? result.perfume.nameAr : result.perfume.name}
                  </h3>
                  <p className="text-sm text-muted-foreground mb-4">
                    {lang === "ar" ? result.perfume.name : result.perfume.nameAr}
                  </p>
                  <p className="text-muted-foreground leading-relaxed">
                    {lang === "ar" ? result.perfume.descriptionAr : result.perfume.description}
                  </p>
                </div>
              </div>

              {(result.matchedFamily || result.matchedNotes.length > 0) && (
                <div className="mt-6 pt-5 border-t border-border/60">
                  {result.matchedFamily && (
                    <p className="text-sm text-gold mb-3">
                      {lang === "ar" ? "العائلة العطرية:" : "Fragrance family:"} {result.matchedFamily}
                    </p>
                  )}
                  {result.matchedNotes.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                      {result.matchedNotes.map((note) => (
                        <span key={note} className="rounded-full bg-gold/10 px-3 py-1.5 text-xs font-medium text-gold border border-gold/20">
                          {note}
                        </span>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </div>

            {alreadyUsed ? (
              <div className="bg-primary/10 border border-primary/20 rounded-xl p-4 mb-6">
                <p className="text-primary font-medium">
                  {lang === "ar" ? "لديك كود خصم محفوظ لحسابك:" : "You already have a discount code saved for your account:"}
                </p>
                <div className="flex items-center justify-center gap-2 mt-2">
                  <span className="font-mono text-2xl font-bold text-foreground">{result.code}</span>
                  <button onClick={copyCode} className="text-primary hover:text-primary/80" aria-label={lang === "ar" ? "نسخ الكود" : "Copy code"}>
                    <Copy size={18} />
                  </button>
                </div>
              </div>
            ) : (
              <div className="bg-gold/10 border border-gold/20 rounded-xl p-6 mb-6">
                <div className="flex items-center justify-center gap-2 text-gold mb-2">
                  <Gift size={20} />
                  <span className="font-bold">
                    {lang === "ar" ? `كود خصم ${result.discountPercent}% خاص بك:` : `Your ${result.discountPercent}% discount code:`}
                  </span>
                </div>
                <div className="flex items-center justify-center gap-2">
                  <span className="font-mono text-3xl font-bold text-foreground">{result.code}</span>
                  <button onClick={copyCode} className="text-gold hover:text-gold/80" aria-label={lang === "ar" ? "نسخ الكود" : "Copy code"}>
                    <Copy size={20} />
                  </button>
                </div>
                <p className="text-xs text-muted-foreground mt-3">
                  {lang === "ar"
                    ? "استخدم هذا الكود عند إتمام طلبك  "
                    : "Use this code when placing your order "}
                </p>
              </div>
            )}

            <div className="flex flex-col sm:flex-row gap-3 justify-center mt-8">
              <Link
                to="/shop"
                className="inline-flex items-center justify-center gap-2 bg-gradient-gold text-accent-foreground px-8 py-3 rounded-full font-bold hover:shadow-gold-lg transition-all"
              >
                {lang === "ar" ? "تسوق الآن" : "Shop Now"}
              </Link>
              <button
                onClick={() => {
                  setStep(0);
                  setAnswers([]);
                  setPhone("");
                  setResult(null);
                  setAlreadyUsed(false);
                }}
                className="inline-flex items-center justify-center gap-2 border border-border text-foreground px-8 py-3 rounded-full font-medium hover:bg-card transition-all"
              >
                {lang === "ar" ? "إعادة الاختبار" : "Retake Quiz"}
              </button>
            </div>
          </motion.div>
        )}
      </div>
    </div>
  );
};

export default Quiz;
