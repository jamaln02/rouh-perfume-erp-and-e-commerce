import { Link } from "react-router-dom";
import { Heart, ArrowRight } from "lucide-react";
import { useLanguage } from "@/hooks/useLanguage";
import { useWishlist } from "@/hooks/useWishlist";
import { useProducts } from "@/hooks/useProducts";
import ProductCard from "@/components/ProductCard";
import ProductCardSkeleton from "@/components/ProductCardSkeleton";
import SEO from "@/components/SEO";
import { motion } from "framer-motion";

const Wishlist = () => {
  const { lang } = useLanguage();
  const { ids, clear } = useWishlist();
  const { products, loading } = useProducts();

  const items = ids.map((id) => products.find((p) => p.id === id)).filter(Boolean);

  return (
    <div className="min-h-screen pt-20 lg:pt-24">
      <SEO
        title={lang === "ar" ? "المفضلة | روح" : "Wishlist | Rouh"}
        description={lang === "ar" ? "عطورك المفضلة في مكان واحد" : "Your favorite perfumes in one place"}
        path="/wishlist"
      />
      <div className="container mx-auto px-4 lg:px-8 py-8">
        <div className="flex items-center justify-between mb-10">
          <div>
            <h1 className="font-display text-4xl md:text-5xl font-bold text-gradient-gold mb-2">
              {lang === "ar" ? "المفضلة" : "Wishlist"}
            </h1>
            <p className="text-muted-foreground">
              {lang === "ar" ? `${items.length} منتج` : `${items.length} items`}
            </p>
          </div>
          {items.length > 0 && (
            <button onClick={clear} className="text-sm text-destructive hover:underline">
              {lang === "ar" ? "مسح الكل" : "Clear all"}
            </button>
          )}
        </div>

        {loading ? (
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            {Array.from({ length: 4 }).map((_, i) => <ProductCardSkeleton key={i} />)}
          </div>
        ) : items.length === 0 ? (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="text-center py-20"
          >
            <div className="relative inline-block mb-8">
              <div className="w-32 h-32 rounded-full bg-gold/10 flex items-center justify-center mx-auto">
                <Heart size={64} className="text-gold/40" />
              </div>
              <div className="absolute -top-2 -right-2 w-8 h-8 rounded-full bg-muted flex items-center justify-center">
                <span className="text-muted-foreground text-xs">0</span>
              </div>
            </div>
            <h2 className="font-display text-2xl md:text-3xl font-bold text-foreground mb-3">
              {lang === "ar" ? "قائمة المفضلة فارغة" : "Your wishlist is empty"}
            </h2>
            <p className="text-muted-foreground mb-8 max-w-md mx-auto">
              {lang === "ar"
                ? "ابدأ بإضافة عطرك المفضل إلى القائمة. العطور التي تحبها في مكان واحد!"
                : "Start adding your favorite scents to your wishlist. Keep all your beloved perfumes in one place!"}
            </p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <Link
                to="/shop"
                className="inline-flex items-center justify-center gap-2 bg-gradient-gold text-accent-foreground px-8 py-4 rounded-full font-semibold hover:shadow-gold-lg transition-all"
              >
                {lang === "ar" ? "تصفح المتجر" : "Browse Shop"}
                <ArrowRight size={18} className={lang === "ar" ? "rotate-180" : ""} />
              </Link>
              <Link
                to="/quiz"
                className="inline-flex items-center justify-center gap-2 border-2 border-gold text-gold px-8 py-4 rounded-full font-semibold hover:bg-gold hover:text-accent-foreground transition-all"
              >
                {lang === "ar" ? "اكتشف عطرك" : "Find Your Scent"}
              </Link>
            </div>
          </motion.div>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            {items.map((p) => p && <ProductCard key={p.id} product={p} />)}
          </div>
        )}
      </div>
    </div>
  );
};

export default Wishlist;