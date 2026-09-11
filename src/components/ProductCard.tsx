import { Link } from "react-router-dom";
import { Eye, Heart, Scale, Share2 } from "lucide-react";
import { useLanguage } from "@/hooks/useLanguage";
import { useWishlist } from "@/hooks/useWishlist";
import { useComparison } from "@/hooks/useComparison";
import { ProductView } from "@/hooks/useProducts";
import { motion } from "framer-motion";
import { toast } from "sonner";
import { ImageWithLazyLoad } from "./ImageWithLazyLoad";
import { ProductAction } from "@/components/shared/ProductActions";

interface ProductCardProps {
  product: ProductView;
}

const ProductCard = ({ product }: ProductCardProps) => {
  const { lang, t } = useLanguage();
  const { has, toggle } = useWishlist();
  const { has: inComparison, add: addToComparison, remove: removeFromComparison } = useComparison();
  const inWishlist = has(product.id);
  const inComp = inComparison(product.id);
  const handleToggleWishlist = (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    toggle(product.id);
    toast.success(inWishlist ? t("removedFromWishlist") : t("addedToWishlist"));
  };

  const handleToggleComparison = (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (inComp) {
      removeFromComparison(product.id);
      toast.success(lang === "ar" ? "تمت الإزالة من المقارنة" : "Removed from comparison");
    } else {
      addToComparison(product.id);
      toast.success(lang === "ar" ? "تمت الإضافة إلى المقارنة" : "Added to comparison");
    }
  };



  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true }}
      transition={{ duration: 0.5 }}
    >
      <Link to={`/product/${product.id}`} className="group block">
        <div className="relative overflow-hidden rounded-2xl bg-card border border-border/50 hover:border-gold/30 transition-all duration-500 hover:shadow-xl">
          <div className="relative overflow-hidden">
            <ImageWithLazyLoad
              src={product.image}
              alt={lang === "ar" ? product.nameAr : product.name}
              className="aspect-[3/4]"
              loading="lazy"
            />
            <div className="absolute inset-0 bg-gradient-to-t from-foreground/70 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500" />

            {/* Badges */}
            <div className="absolute top-3 start-3 flex flex-col gap-2">
              {product.isNew && (
                <span className="bg-gold text-accent-foreground text-xs font-bold px-3 py-1.5 rounded-full shadow-gold">
                  {t("newArrivals")}
                </span>
              )}
              {product.bestSeller && (
                <span className="bg-secondary text-secondary-foreground text-xs font-bold px-3 py-1.5 rounded-full">
                  {t("bestSellers")}
                </span>
              )}
            </div>

            {/* Wishlist heart */}
            <ProductAction
              onClick={handleToggleWishlist}
              isActive={inWishlist}
              title={inWishlist ? t("removeFromWishlist") : t("addToWishlist")}
              className="absolute top-3 end-3"
            >
              <Heart size={16} className={inWishlist ? "fill-current" : ""} />
            </ProductAction>

            {/* Hover actions */}
            <div className="absolute bottom-4 left-0 right-0 flex justify-center gap-3 opacity-0 group-hover:opacity-100 translate-y-4 group-hover:translate-y-0 transition-all duration-500">
              <ProductAction
                onClick={handleToggleComparison}
                isActive={inComp}
                title={lang === "ar" ? "مقارنة" : "Compare"}
              >
                <Scale size={18} />
              </ProductAction>
              <div className="bg-card/90 backdrop-blur text-foreground p-3 rounded-full shadow-lg" role="status" aria-label="Quick view">
                <Eye size={18} />
              </div>
              <ProductAction
                onClick={() => {
                  const shareUrl = typeof window !== 'undefined' ? `${window.location.origin}/product/${product.id}` : '';
                  if (navigator.share) {
                    navigator.share({
                      title: lang === "ar" ? product.nameAr : product.name,
                      url: shareUrl,
                    });
                  } else {
                    navigator.clipboard.writeText(shareUrl);
                    toast.success(lang === "ar" ? "تم نسخ الرابط" : "Link copied");
                  }
                }}
                title={lang === "ar" ? "مشاركة" : "Share"}
              >
                <Share2 size={18} />
              </ProductAction>
            </div>
          </div>

          <div className="p-4">
            <h3 className="font-semibold text-foreground group-hover:text-gold transition-colors">
              {lang === "ar" ? product.nameAr : product.name}
            </h3>
            <p className="text-xs text-muted-foreground mt-1">
              {t(product.fragrance)} · {t(product.category)}
            </p>
            <div className="mt-3 flex items-center justify-between gap-2">
              <span className="text-sm font-medium text-gold">
                {lang === "ar" ? "اختر الحجم لعرض السعر" : "Choose a size to see the price"}
              </span>
            </div>
          </div>
        </div>
      </Link>
    </motion.div>
  );
};

export default ProductCard;
