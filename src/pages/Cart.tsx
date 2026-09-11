import { Link } from "react-router-dom";
import { useLanguage } from "@/hooks/useLanguage";
import { useCart } from "@/hooks/useCart";
import { Trash2, ShoppingBag } from "lucide-react";
import { motion } from "framer-motion";
import SEO from "@/components/SEO";
import LoyaltyRewards from "@/components/LoyaltyRewards";
import CustomOrderNotice from "@/components/CustomOrderNotice";
import { QuantityControl } from "@/components/shared/QuantityControl";
import { PriceDisplay } from "@/components/shared/PriceDisplay";
import { useStorefrontConfig } from "@/hooks/useStorefrontConfig";

const Cart = () => {
  const { t, lang } = useLanguage();
  const { items, removeItem, updateQuantity, totalPrice } = useCart();
  const { config } = useStorefrontConfig();
  const hasFreeShipping = totalPrice > config.shipping.free_threshold;

  if (items.length === 0) {
    return (
      <div className="min-h-screen pt-24 flex flex-col items-center justify-center gap-6">
        <ShoppingBag size={64} className="text-muted-foreground" />
        <h2 className="text-2xl font-bold text-foreground">{t("emptyCart")}</h2>
        <Link
          to="/shop"
          className="bg-gradient-gold text-accent-foreground px-6 py-3 rounded-lg font-semibold shadow-gold"
        >
          {t("continueShopping")}
        </Link>
      </div>
    );
  }

  return (
    <div className="min-h-screen pt-20 lg:pt-24">
      <SEO
        title={lang === "ar" ? "سلة التسوق | روح" : "Shopping Cart | Rouh"}
        description={lang === "ar" ? "راجع منتجاتك في السلة وأتمم طلبك من عطور روح." : "Review your cart items and complete your Rouh perfumes order."}
        path="/cart"
      />
      <div className="container mx-auto px-4 lg:px-8 py-8">
        <h1 className="text-3xl font-bold text-gradient-gold mb-8">{t("cart")}</h1>

        <div className="mb-8">
          <CustomOrderNotice />
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Items */}
          <div className="lg:col-span-2 flex flex-col gap-4">
            {items.map((item) => (
              <motion.div
                key={`${item.id}-${item.size}-${item.variantId || "base"}-${item.offerId || "normal"}-${item.offerSelectionId || ""}-${item.offerSlotId || ""}`}
                layout
                className="flex gap-4 bg-card p-4 rounded-lg border border-border"
              >
                <img
                  src={item.image}
                  alt={lang === "ar" ? item.nameAr : item.name}
                  className="w-20 h-20 object-cover rounded-lg"
                  loading="lazy"
                />
                <div className="flex-1">
                  <h3 className="font-semibold text-foreground">
                    {lang === "ar" ? item.nameAr : item.name}
                  </h3>
                  <p className="text-sm text-muted-foreground">{item.size}</p>{item.offerName && <p className="text-xs text-gold mt-1">{item.offerName}</p>}
                  <PriceDisplay price={item.price} lang={lang} className="mt-1" size="sm" />
                </div>
                <div className="flex flex-col items-end gap-2">
                  <button
                    onClick={() => removeItem(item.id, item.size, item.variantId, item.offerId, item.offerSelectionId, item.offerSlotId)}
                    aria-label={lang === "ar" ? "حذف المنتج" : "Remove item"}
                    className="text-destructive hover:opacity-70"
                  >
                    <Trash2 size={16} />
                  </button>
                  {item.offerId ? (
                    <span className="text-xs text-gold bg-gold/10 px-2 py-1 rounded-full">
                      {lang === "ar" ? "عرض ثابت" : "Offer item"}
                    </span>
                  ) : (
                    <QuantityControl
                      quantity={item.quantity}
                      onDecrease={() => updateQuantity(item.id, item.size, item.quantity - 1, item.variantId)}
                      onIncrease={() => updateQuantity(item.id, item.size, item.quantity + 1, item.variantId)}
                      lang={lang}
                    />
                  )}
                </div>
              </motion.div>
            ))}
          </div>

          {/* Summary */}
          <div className="space-y-4">
            <div className="bg-card p-6 rounded-lg border border-border h-fit">
              <h2 className="text-lg font-bold text-foreground mb-4">{t("orderSummary")}</h2>
              <div className="flex justify-between text-sm mb-2">
                <span className="text-muted-foreground">{t("subtotal")}</span>
                <span className="text-foreground">
                  <PriceDisplay price={totalPrice} lang={lang} className="text-sm" size="sm" />
                </span>
              </div>
              <div className="flex justify-between text-sm mb-4">
                <span className="text-muted-foreground">{t("shipping")}</span>
                <span className="text-foreground">{hasFreeShipping ? (lang === "ar" ? "مجاني" : "Free") : (lang === "ar" ? "يحدد حسب المحافظة" : "Calculated by city")}</span>
              </div>
              <div className="border-t border-border pt-4 flex justify-between font-bold">
                <span className="text-foreground">{t("total")}</span>
                <span className="text-gold"><PriceDisplay price={totalPrice} lang={lang} /></span>
              </div>
              <Link
                to="/checkout"
                className="mt-6 block text-center bg-gradient-gold text-accent-foreground py-3 rounded-lg font-semibold shadow-gold hover:opacity-90 transition-opacity focus:outline-none focus:ring-2 focus:ring-gold focus:ring-offset-2"
              >
                {t("checkout")}
              </Link>
            </div>

            <LoyaltyRewards />
          </div>
        </div>
      </div>
    </div>
  );
};

export default Cart;
