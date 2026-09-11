import { useParams } from "react-router-dom";
import { useState, useEffect } from "react";
import { useLanguage } from "@/hooks/useLanguage";
import { useCart } from "@/hooks/useCart";
import { useWishlist } from "@/hooks/useWishlist";
import { useProduct, getPriceForSize, getVariantForSize } from "@/hooks/useProducts";
import { useRecommendations } from "@/hooks/useRecommendations";
import { useRecentlyViewed } from "@/hooks/useRecentlyViewed";
import ProductCard from "@/components/ProductCard";
import RecentlyViewed from "@/components/RecentlyViewed";
import Reviews from "@/components/Reviews";
import SocialShare from "@/components/SocialShare";
import { SectionHeader } from "@/components/shared/SectionHeader";
import { PriceDisplay } from "@/components/shared/PriceDisplay";
import { ShoppingBag, MessageCircle, Truck, Shield, Heart, Sparkles, Flame, Wind } from "lucide-react";
import { toast } from "sonner";
import { motion } from "framer-motion";
import { Link } from "react-router-dom";
import CustomOrderNotice from "@/components/CustomOrderNotice";

const ProductDetails = () => {
  const { id } = useParams();
  const { t, lang } = useLanguage();
  const { addItem } = useCart();
  const { has, toggle } = useWishlist();
  const { product, loading } = useProduct(id || "");
  const [selectedSize, setSelectedSize] = useState("");
  const related = useRecommendations(id || "", 4);
  const { add: addToRecentlyViewed } = useRecentlyViewed();

  // Add to recently viewed using Redux
  useEffect(() => {
    if (product?.id) {
      addToRecentlyViewed(product.id);
    }
  }, [product?.id, addToRecentlyViewed]);

  if (loading) {
    return (
      <div className="min-h-screen pt-24 flex items-center justify-center">
        <div className="animate-spin h-8 w-8 border-2 border-primary border-t-transparent rounded-full" />
      </div>
    );
  }

  if (!product) {
    return (
      <div className="min-h-screen pt-24 flex flex-col items-center justify-center gap-4">
        <p className="text-2xl font-bold text-foreground">
          {lang === "ar" ? "المنتج غير موجود" : "Product not found"}
        </p>
        <Link to="/shop" className="text-gold hover:underline">
          {lang === "ar" ? "العودة للمتجر" : "Back to shop"}
        </Link>
      </div>
    );
  }

  const currentSize = selectedSize;
  const currentVariant = getVariantForSize(product, currentSize);
  const currentPrice = currentSize ? getPriceForSize(product.price, currentSize, product.sizePrices) : 0;
  const currentImage = currentVariant?.image && currentVariant.image !== '/placeholder.svg' ? currentVariant.image : product.image;
  const inWishlist = has(product.id);
  const hasSizeOptions = product.sizes.length > 0;
  const sizeSelected = !hasSizeOptions || Boolean(currentSize);

  const handleAdd = () => {
    addItem({
      id: product.id,
      name: product.name,
      nameAr: product.nameAr,
      price: currentPrice,
      image: currentImage,
      size: currentSize,
      variantId: currentVariant?.id,
    });
    toast.success(lang === "ar" ? "تمت الإضافة إلى السلة" : "Added to cart");
  };

  const whatsappNumber = "963933898625";
  const whatsappMsg = encodeURIComponent(
    lang === "ar"
      ? `مرحباً، أريد شراء ${product.nameAr} - ${currentSize}`
      : `Hi, I want to buy ${product.name} - ${currentSize}`
  );

  return (
    <div className="min-h-screen pt-20 lg:pt-24">
      {/* Breadcrumb */}
      <div className="container mx-auto px-4 lg:px-8 py-4">
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Link to="/" className="hover:text-gold transition-colors">{t("home")}</Link>
          <span>/</span>
          <Link to="/shop" className="hover:text-gold transition-colors">{t("shop")}</Link>
          <span>/</span>
          <span className="text-foreground">{lang === "ar" ? product.nameAr : product.name}</span>
        </div>
      </div>

      <div className="container mx-auto px-4 lg:px-8 pb-8">
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          className="grid grid-cols-1 lg:grid-cols-2 gap-12"
        >
          {/* Image */}
          <div>
            <div className="rounded-3xl overflow-hidden bg-card border border-border/50">
              <img
                src={currentImage}
                alt={lang === "ar" ? `${product.nameAr}${currentSize ? ` - ${currentSize}` : ""}` : `${product.name}${currentSize ? ` - ${currentSize}` : ""}`}
                className="w-full aspect-square object-cover hover:scale-105 transition-transform duration-700"
                width={800}
                height={800}
              />
            </div>
            {(!currentVariant || currentVariant.imageIsReference) && (
              <p className="mt-3 text-xs leading-relaxed text-muted-foreground text-center">
                {lang === "ar"
                  ? "الصورة توضيحية وليست صورة المنتج الفعلية."
                  : "The image is for illustration and is not a photo of the actual product."}
              </p>
            )}
          </div>

          {/* Info */}
          <div className="flex flex-col justify-center">
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.2 }}
            >
              <div className="flex items-center gap-2 mb-3">
                <span className="bg-gold/10 text-gold text-xs font-medium px-3 py-1 rounded-full">
                  {t(product.fragrance)}
                </span>
                <span className="bg-secondary/50 text-secondary-foreground text-xs font-medium px-3 py-1 rounded-full">
                  {t(product.category)}
                </span>
              </div>

              <h1 className="font-display text-4xl md:text-5xl font-bold text-foreground mb-4">
                {lang === "ar" ? product.nameAr : product.name}
              </h1>
              <p className="text-muted-foreground leading-relaxed mb-8 text-lg">
                {lang === "ar" ? product.descriptionAr : product.description}
              </p>

              {/* Size + price are intentionally adjacent so the customer never has to scroll back up to see the price. */}
              {product.sizes.length > 0 && (
                <div className="mb-8 rounded-2xl border border-border/60 bg-card/40 p-5 md:p-6">
                  <p className="text-sm font-semibold text-foreground mb-3 uppercase tracking-wider">{t("size")}</p>
                  <div className="flex flex-wrap gap-3">
                    {product.sizes.map((size) => (
                      <button
                        key={size}
                        onClick={() => setSelectedSize(size)}
                        aria-pressed={currentSize === size}
                        className={`min-w-[92px] px-5 py-3 rounded-xl border-2 text-sm font-medium transition-all duration-200 ${
                          currentSize === size
                            ? "border-gold bg-gold text-accent-foreground shadow-gold"
                            : "border-border text-foreground hover:border-gold"
                        }`}
                      >
                        {size}
                      </button>
                    ))}
                  </div>
                  <div className="mt-5 pt-5 border-t border-border/60 min-h-12 flex items-center">
                    {sizeSelected ? (
                      <PriceDisplay price={currentPrice} lang={lang} className="text-3xl" size="lg" />
                    ) : (
                      <div className="text-lg font-medium text-gold">
                        {lang === "ar" ? "اختر الحجم لمعرفة السعر" : "Choose a size to see the price"}
                      </div>
                    )}
                  </div>
                </div>
              )}

              {/* Fragrance Notes Pyramid — Top / Heart / Base */}
              {(product.topNotes.length > 0 || product.heartNotes.length > 0 || product.baseNotes.length > 0) && (
                <div className="mb-8 rounded-2xl border border-border/60 bg-card/40 p-6">
                  <h3 className="font-display text-xl font-bold text-foreground mb-1">
                    {lang === "ar" ? "هرم النوتات العطرية" : "Fragrance Notes Pyramid"}
                  </h3>
                  {product.fragranceFamily && (
                    <p className="text-sm text-gold mb-4">
                      {lang === "ar" ? "العائلة العطرية" : "Fragrance Family"}: {product.fragranceFamily}
                    </p>
                  )}

                  {/* Top Notes */}
                  {product.topNotes.length > 0 && (
                    <div className="mb-4">
                      <div className="flex items-center gap-2 mb-2">
                        <Wind size={16} className="text-sky-400" />
                        <span className="text-sm font-semibold text-foreground">
                          {lang === "ar" ? "النوتات العلوية" : "Top Notes"}
                        </span>
                      </div>
                      <div className="flex flex-wrap gap-2">
                        {product.topNotes.map((note, i) => (
                          <span key={`top-${i}`} className="bg-sky-500/10 text-sky-600 dark:text-sky-300 text-xs font-medium px-3 py-1.5 rounded-full">
                            {note}
                          </span>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Heart Notes */}
                  {product.heartNotes.length > 0 && (
                    <div className="mb-4">
                      <div className="flex items-center gap-2 mb-2">
                        <Sparkles size={16} className="text-rose-400" />
                        <span className="text-sm font-semibold text-foreground">
                          {lang === "ar" ? "النوتات الوسطى" : "Heart Notes"}
                        </span>
                      </div>
                      <div className="flex flex-wrap gap-2">
                        {product.heartNotes.map((note, i) => (
                          <span key={`heart-${i}`} className="bg-rose-500/10 text-rose-600 dark:text-rose-300 text-xs font-medium px-3 py-1.5 rounded-full">
                            {note}
                          </span>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Base Notes */}
                  {product.baseNotes.length > 0 && (
                    <div>
                      <div className="flex items-center gap-2 mb-2">
                        <Flame size={16} className="text-amber-500" />
                        <span className="text-sm font-semibold text-foreground">
                          {lang === "ar" ? "النوتات القاعدية" : "Base Notes"}
                        </span>
                      </div>
                      <div className="flex flex-wrap gap-2">
                        {product.baseNotes.map((note, i) => (
                          <span key={`base-${i}`} className="bg-amber-500/10 text-amber-700 dark:text-amber-300 text-xs font-medium px-3 py-1.5 rounded-full">
                            {note}
                          </span>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              )}

              {/* Actions */}
              <div className="flex flex-col sm:flex-row gap-3 mb-8">
                <button
                  onClick={handleAdd}
                  disabled={!sizeSelected}
                  className="flex-1 flex items-center justify-center gap-2 bg-gradient-gold text-accent-foreground px-8 py-4 rounded-xl font-semibold hover:shadow-gold-lg transition-all duration-300 text-lg disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none"
                >
                  <ShoppingBag size={20} />
                  {t("addToCart")}
                </button>
                <a
                  href={sizeSelected && whatsappNumber ? `https://wa.me/${whatsappNumber}?text=${whatsappMsg}` : undefined}
                  onClick={(event) => { if (!sizeSelected) event.preventDefault(); }}
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-disabled={!sizeSelected}
                  className={`flex-1 flex items-center justify-center gap-2 border-2 border-[#25D366] text-[#25D366] px-8 py-4 rounded-xl font-semibold hover:bg-[#25D366] hover:text-white transition-all duration-300 ${!sizeSelected ? "opacity-50 cursor-not-allowed" : ""}`}
                >
                  <MessageCircle size={20} />
                  {t("buyWhatsApp")}
                </a>
                <button
                  onClick={() => {
                    toggle(product.id);
                    toast.success(inWishlist ? t("removedFromWishlist") : t("addedToWishlist"));
                  }}
                  aria-label={inWishlist ? t("removeFromWishlist") : t("addToWishlist")}
                  className={`flex items-center justify-center gap-2 px-6 py-4 rounded-xl font-semibold border-2 transition-all duration-300 ${
                    inWishlist
                      ? "bg-gold border-gold text-accent-foreground shadow-gold"
                      : "border-border text-foreground hover:border-gold hover:text-gold"
                  }`}
                >
                  <Heart size={20} className={inWishlist ? "fill-current" : ""} />
                </button>
              </div>

              {/* Social Share */}
              <div className="mb-8">
                <SocialShare
                  url={typeof window !== 'undefined' ? window.location.href : ''}
                  title={lang === "ar" ? product.nameAr : product.name}
                  description={lang === "ar" ? product.descriptionAr : product.description}
                />
              </div>

              {/* Trust badges */}
              <div className="flex gap-6 pt-6 border-t border-border">
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Truck size={18} className="text-gold" />
                  {lang === "ar" ? "شحن سريع" : "Fast Shipping"}
                </div>
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Shield size={18} className="text-gold" />
                  {lang === "ar" ? "منتج أصلي 100%" : "100% Authentic"}
                </div>
              </div>

              {/* Custom order notice */}
              <div className="mt-6">
                <CustomOrderNotice variant="compact" />
              </div>
            </motion.div>
          </div>
        </motion.div>

        {/* Related */}
        {related.length > 0 && (
          <section className="mt-20">
            <SectionHeader title={t("relatedProducts")} />
            <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
              {related.map((p) => (
                <ProductCard key={p.id} product={p} />
              ))}
            </div>
          </section>
        )}

        <RecentlyViewed excludeId={product.id} />
        <Reviews productId={product.id} />
      </div>
    </div>
  );
};

export default ProductDetails;
