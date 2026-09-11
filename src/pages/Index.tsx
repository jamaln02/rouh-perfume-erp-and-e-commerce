import { Link } from "react-router-dom";
import { useLanguage } from "@/hooks/useLanguage";
import { useProducts } from "@/hooks/useProducts";
import { useStorefrontConfig } from "@/hooks/useStorefrontConfig";
import ProductCard from "@/components/ProductCard";
import ProductCardSkeleton from "@/components/ProductCardSkeleton";
import SEO from "@/components/SEO";
import heroImage from "@/assets/hero-rouh-clean.png";
import { motion } from "framer-motion";
import { Truck, Shield, Headphones, ArrowRight, Sparkles, Star, Gift, Search } from "lucide-react";
import { useMemo, useState } from "react";

const Index = () => {
  const { t, lang } = useLanguage();
  const { products, loading } = useProducts();
  const { config: storeConfig } = useStorefrontConfig();
  const [searchQuery, setSearchQuery] = useState("");

  const searchResults = useMemo(() => {
    const query = searchQuery.trim().toLocaleLowerCase();
    if (!query) return [];
    return products.filter((p) => [
      p.name, p.nameAr, p.description, p.descriptionAr, p.category, p.fragrance,
      ...(p.sizes || []),
      ...(p.variants || []).map((variant) => variant.size),
    ].filter(Boolean).join(" ").toLocaleLowerCase().includes(query));
  }, [products, searchQuery]);

  const featured = products.filter((p) => p.featured).slice(0, 5);
  const bestSellers = products.filter((p) => p.bestSeller).slice(0, 5);

  const fadeUp = {
    hidden: { opacity: 0, y: 30 },
    visible: (i: number) => ({
      opacity: 1,
      y: 0,
      transition: { delay: i * 0.1, duration: 0.6, ease: [0.22, 1, 0.36, 1] as [number, number, number, number] },
    }),
  };

  return (
    <div className="min-h-screen">
      <SEO
        title={lang === "ar" ? "روح | Rouh - عطور فاخرة سورية أصلية" : "Rouh | روح - Syrian Luxury Perfumes"}
        description={lang === "ar"
          ? "اكتشف مجموعة روح الفاخرة من العطور الشرقية والغربية الأصلية. توصيل لكل سوريا ودفع عند الاستلام."
          : "Discover Rouh's curated collection of authentic oriental and western luxury perfumes. Delivery across Syria with cash on delivery."}
        path="/"
      />
      {/* Hero */}
      <section className="relative h-[100svh] md:h-screen flex items-center overflow-hidden bg-[#1a0a14] z-0">
        <motion.img
          initial={{ scale: 1.1 }}
          animate={{ scale: 1.05 }}
          transition={{ duration: 1.2, ease: "easeOut" }}
          src={heroImage}
          alt="Rouh Perfumes"
          className="absolute inset-0 w-full h-full object-cover object-top md:object-cover"
          width={1920}
          height={1080}
        />
        <div className="absolute inset-0 bg-gradient-to-r from-black/25 via-black/10 to-transparent md:from-black/35 md:via-black/20" />
        <div className="absolute inset-0 bg-gradient-to-t from-[#1a0a14] via-black/5 to-black/5 md:from-black/25 md:to-black/10" />

        <div className="w-full container mx-auto px-5 lg:px-8 relative z-10 pointer-events-none flex items-end md:items-center pb-20 md:pb-0" style={{ minHeight: "100svh" }}>
          <motion.div
            initial={{ opacity: 0, y: 35 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.9, delay: 0.2, ease: [0.22, 1, 0.36, 1] }}
            className="max-w-2xl flex flex-col w-full"
          >
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ delay: 0.6 }}
              className="flex items-center gap-2 mb-3 md:mb-5"
            >
              <Sparkles size={15} className="text-gold shrink-0" />
              <span className="text-gold text-xs md:text-sm font-medium tracking-widest uppercase">
                {lang === "ar" ? "عطور فاخرة" : "Luxury Perfumes"}
              </span>
            </motion.div>
            <h1 className="font-display text-[1.9rem] leading-tight sm:text-4xl md:text-6xl font-bold text-cream md:leading-[1.15] mb-3 md:mb-5 text-balance">
              {t("heroTitle")}
            </h1>
            <p className="text-sm sm:text-base md:text-lg text-cream/85 max-w-lg leading-relaxed">
              {t("heroSubtitle")}
            </p>
            <div className="flex items-center gap-3 mt-6 md:mt-8">
              <Link
                to="/shop"
                className="pointer-events-auto group inline-flex items-center justify-center gap-2 border border-gold/65 bg-foreground/15 backdrop-blur-sm text-gold px-5 py-2.5 md:px-8 md:py-3 rounded-sm text-sm md:text-base font-semibold tracking-wide hover:bg-gold/15 hover:border-gold transition-all duration-300"
              >
                {lang === "ar" ? "تسوق الآن" : "Explore Collection"}
                <ArrowRight
                  size={16}
                  className={`md:size-[18px] transition-transform ${lang === "ar" ? "rotate-180 group-hover:-translate-x-1" : "group-hover:translate-x-1"}`}
                />
              </Link>
              <Link
                to="/about"
                className="pointer-events-auto inline-flex items-center justify-center gap-2 border border-cream/35 text-cream px-5 py-2.5 md:px-7 md:py-3 rounded-full text-sm md:text-base font-medium hover:bg-cream/10 transition-all duration-300"
              >
                {t("ourStory")}
              </Link>
            </div>
          </motion.div>
        </div>

        {/* Scroll indicator */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 1.5 }}
          className="absolute bottom-5 md:bottom-8 left-1/2 -translate-x-1/2 hidden md:flex flex-col items-center gap-2"
        >
          <span className="text-cream/40 text-xs tracking-widest uppercase">
            {lang === "ar" ? "اكتشف المزيد" : "Scroll"}
          </span>
          <motion.div
            animate={{ y: [0, 8, 0] }}
            transition={{ repeat: Infinity, duration: 1.5 }}
            className="w-5 h-8 rounded-full border-2 border-cream/30 flex justify-center pt-1"
          >
            <div className="w-1 h-2 rounded-full bg-gold" />
          </motion.div>
        </motion.div>
      </section>

      {/* Search Section */}
      <section className="py-16 bg-card/30">
        <div className="container mx-auto px-4 lg:px-8">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="max-w-3xl mx-auto"
          >
            <h2 className="font-display text-3xl font-bold text-gradient-gold text-center mb-6">
              {lang === "ar" ? "ابحث عن عطرك المثالي" : "Find Your Perfect Scent"}
            </h2>
            <div className="relative">
              <Search size={20} className="absolute start-4 top-1/2 -translate-y-1/2 text-muted-foreground" />
              <input
                type="search"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder={lang === "ar" ? "ابحث عن عطر..." : "Search for perfumes..."}
                aria-label={lang === "ar" ? "البحث عن عطر" : "Search for perfumes"}
                className="w-full bg-card text-foreground ps-12 pe-4 py-4 rounded-2xl border border-border outline-none focus:ring-2 focus:ring-gold placeholder:text-muted-foreground"
              />
            </div>
            <p className="text-center text-xs text-muted-foreground mt-3">
              {lang === "ar" ? "تظهر النتائج مباشرة أثناء الكتابة." : "Results appear instantly as you type."}
            </p>
            
            {searchResults.length > 0 && (
              <motion.div
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                className="mt-8 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6"
              >
                {searchResults.map((product) => (
                  <ProductCard key={product.id} product={product} />
                ))}
              </motion.div>
            )}
            
            {searchQuery && searchResults.length === 0 && (
              <motion.p
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                className="text-center text-muted-foreground mt-8"
              >
                {lang === "ar" ? "لم يتم العثور على نتائج" : "No results found"}
              </motion.p>
            )}
          </motion.div>
        </div>
      </section>

      {/* Features bar */}
      <section className="relative -mt-16 z-20 pb-8">
        <div className="container mx-auto px-4 lg:px-8">
          <div className="glass rounded-2xl border border-border/50 shadow-xl p-6 md:p-8">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              {[
                { icon: Truck, title: t("freeShipping"), desc: t("freeShippingDesc") },
                { icon: Shield, title: t("premiumQuality"), desc: t("premiumQualityDesc") },
                { icon: Headphones, title: t("support"), desc: t("supportDesc") },
              ].map((item, i) => (
                <motion.div
                  key={i}
                  custom={i}
                  initial="hidden"
                  whileInView="visible"
                  viewport={{ once: true }}
                  variants={fadeUp}
                  className="flex items-center gap-4 justify-center md:justify-start"
                >
                  <div className="w-12 h-12 rounded-xl bg-gold/10 flex items-center justify-center shrink-0">
                    <item.icon className="text-gold" size={24} />
                  </div>
                  <div>
                    <p className="font-semibold text-foreground text-sm">{item.title}</p>
                    <p className="text-xs text-muted-foreground">{item.desc}</p>
                  </div>
                </motion.div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* Featured Products */}
      <section className="py-20">
        <div className="container mx-auto px-4 lg:px-8">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="text-center mb-14"
          >
            <span className="text-gold text-sm font-medium tracking-widest uppercase mb-3 block">
              {lang === "ar" ? "مجموعة مختارة" : "Curated Selection"}
            </span>
            <h2 className="font-display text-4xl md:text-5xl font-bold text-gradient-gold">{t("featured")}</h2>
          </motion.div>
          {loading ? (
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6 lg:gap-6">
              {Array.from({ length: 5 }).map((_, i) => <ProductCardSkeleton key={i} />)}
            </div>
          ) : (
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6 lg:gap-6">
              {(featured.length > 0 ? featured : products.slice(0, 5)).map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          )}
        </div>
      </section>

      {/* Quiz CTA Banner */}
      <section className="py-20 bg-gradient-burgundy relative overflow-hidden">
        <div className="absolute inset-0 opacity-10">
          <div className="absolute top-0 left-0 w-96 h-96 bg-gold rounded-full blur-[120px]" />
          <div className="absolute bottom-0 right-0 w-96 h-96 bg-gold rounded-full blur-[120px]" />
        </div>
        <div className="container mx-auto px-4 lg:px-8 relative z-10">
          <motion.div
            initial={{ opacity: 0, y: 30 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="text-center max-w-3xl mx-auto"
          >
            <Sparkles className="text-gold mx-auto mb-6" size={32} />
            <h2 className="font-display text-3xl md:text-5xl font-bold text-cream mb-6 text-balance">
              {lang === "ar"
                ? "مش عارف شو العطر يلي بناسبك؟"
                : "Not Sure Which Scent Suits You?"}
            </h2>
            <p className="text-cream/60 text-lg mb-4 max-w-xl mx-auto">
              {lang === "ar"
                ? "خذ اختبار الشخصية واكتشف عطرك المثالي بـ 5 أسئلة بسيطة"
                : "Take our personality quiz and discover your perfect fragrance in 5 simple questions"}
            </p>
            <div className="bg-gold/20 border border-gold/30 rounded-xl px-5 py-3 inline-flex items-center gap-2 mb-8">
              <Gift size={18} className="text-gold" />
              <span className="text-gold font-bold text-sm">
                {lang === "ar" ? `واحصل على خصم ${storeConfig.quiz.discount_percent}% كمكافأة!` : `And get ${storeConfig.quiz.discount_percent}% off as a reward!`}
              </span>
            </div>
            <div>
              <Link
                to="/quiz"
                className="group inline-flex items-center gap-3 bg-gradient-gold text-accent-foreground px-8 py-4 rounded-full font-semibold hover:shadow-gold-lg transition-all duration-300"
              >
                {lang === "ar" ? "ابدأ الاختبار الآن" : "Start the Quiz Now"}
                <ArrowRight size={18} className="group-hover:translate-x-1 transition-transform" />
              </Link>
            </div>
          </motion.div>
        </div>
      </section>

      {/* Best Sellers */}
      <section className="py-20">
        <div className="container mx-auto px-4 lg:px-8">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="text-center mb-14"
          >
            <span className="text-gold text-sm font-medium tracking-widest uppercase mb-3 block">
              {lang === "ar" ? "الأعلى تقييماً" : "Top Rated"}
            </span>
            <h2 className="font-display text-4xl md:text-5xl font-bold text-gradient-gold">{t("bestSellers")}</h2>
          </motion.div>
          {loading ? (
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6 lg:gap-6">
              {Array.from({ length: 5 }).map((_, i) => <ProductCardSkeleton key={i} />)}
            </div>
          ) : (
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6 lg:gap-6">
              {(bestSellers.length > 0 ? bestSellers : products.slice(0, 5)).map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          )}
          <div className="text-center mt-12">
            <Link
              to="/shop"
              className="group inline-flex items-center gap-3 border-2 border-gold text-gold px-8 py-4 rounded-full font-semibold hover:bg-gold hover:text-accent-foreground transition-all duration-300"
            >
              {t("shopNow")}
              <ArrowRight size={18} className="group-hover:translate-x-1 transition-transform" />
            </Link>
          </div>
        </div>
      </section>

      {/* Testimonials */}
      <section className="py-20 bg-card/50">
        <div className="container mx-auto px-4 lg:px-8">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="text-center mb-14"
          >
            <h2 className="font-display text-4xl md:text-5xl font-bold text-gradient-gold">
              {lang === "ar" ? "آراء العملاء" : "What Our Clients Say"}
            </h2>
          </motion.div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
            {[
              {
                nameAr: "أحمد *****",
                nameEn: "Ahmad M.",
                textAr: "عطور رائعة جداً، ثبات ممتاز ورائحته فخمة. أنصح به بشدة!",
                textEn: "Royal Amber is absolutely stunning. Long lasting and luxurious scent. Highly recommended!",
              },
              {
                nameAr: "سارة *****",
                nameEn: "Sarah A.",
                textAr: "جربت عطر اوراجين و كتير حلو و صار عطري المفضل الله يعطيكن الف عافية ",
                textEn: "Rose Éternelle has become my favorite. An elegant feminine scent that lasts all day.",
              },
              {
                nameAr: "عمر ****",
                nameEn: "Omar H.",
                textAr: "جودة عالية وأسعار منافسة. خدمة التوصيل سريعة والتغليف أنيق جداً.",
                textEn: "High quality at competitive prices. Fast delivery and very elegant packaging.",
              },
            ].map((review, i) => (
              <motion.div
                key={i}
                custom={i}
                initial="hidden"
                whileInView="visible"
                viewport={{ once: true }}
                variants={fadeUp}
                className="bg-card border border-border rounded-2xl p-8 hover:shadow-lg transition-shadow duration-300"
              >
                <div className="flex gap-1 mb-4">
                  {[...Array(5)].map((_, j) => (
                    <Star key={j} size={16} className="fill-gold text-gold" />
                  ))}
                </div>
                <p className="text-muted-foreground leading-relaxed mb-6">
                  "{lang === "ar" ? review.textAr : review.textEn}"
                </p>
                <p className="font-semibold text-foreground">
                  {lang === "ar" ? review.nameAr : review.nameEn}
                </p>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* Newsletter */}
      <section className="py-20">
        <div className="container mx-auto px-4 lg:px-8">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="bg-gradient-burgundy rounded-3xl p-10 md:p-16 text-center relative overflow-hidden"
          >
            <div className="absolute inset-0 opacity-5">
              <div className="absolute top-10 left-10 w-64 h-64 bg-gold rounded-full blur-[100px]" />
              <div className="absolute bottom-10 right-10 w-64 h-64 bg-gold rounded-full blur-[100px]" />
            </div>
            <div className="relative z-10">
              <h2 className="font-display text-3xl md:text-4xl font-bold text-cream mb-4">
                {lang === "ar" ? "انضم إلى عائلة روح" : "Join the Rouh Family"}
              </h2>
              <p className="text-cream/60 mb-8 max-w-md mx-auto">
                {lang === "ar"
                  ? "اشترك ليصلك كل جديد من العروض والمنتجات الحصرية"
                  : "Subscribe to get exclusive offers and new product updates"}
              </p>
              <form className="flex flex-col sm:flex-row gap-3 max-w-md mx-auto" onSubmit={(e) => e.preventDefault()}>
                <input
                  type="email"
                  placeholder={lang === "ar" ? "بريدك الإلكتروني" : "Your email"}
                  aria-label={lang === "ar" ? "البريد الإلكتروني للاشتراك" : "Email for newsletter subscription"}
                  className="flex-1 bg-cream/10 border border-cream/20 text-cream px-5 py-3 rounded-full outline-none focus:ring-2 focus:ring-gold placeholder:text-cream/40"
                />
                <button
                  type="submit"
                  className="bg-gradient-gold text-accent-foreground px-8 py-3 rounded-full font-semibold hover:shadow-gold-lg transition-all duration-300"
                >
                  {lang === "ar" ? "اشتراك" : "Subscribe"}
                </button>
              </form>
            </div>
          </motion.div>
        </div>
      </section>
    </div>
  );
};

export default Index;
