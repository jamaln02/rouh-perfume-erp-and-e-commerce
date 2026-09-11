import { useState, useEffect } from "react";
import { useLanguage } from "@/hooks/useLanguage";
import { useCart } from "@/hooks/useCart";
import { useAuth } from "@/hooks/useAuth";
import { useStorefrontConfig } from "@/hooks/useStorefrontConfig";
import { withAuthHeaders } from "@/lib/auth";
import { toast } from "sonner";
import { useNavigate, useSearchParams } from "react-router-dom";
import { motion } from "framer-motion";
import { ShieldCheck, Truck, CreditCard, MessageCircle, ChevronDown, Tag, X } from "lucide-react";
import SEO from "@/components/SEO";
import LoadingSpinner from "@/components/ui/loading-spinner";

const DEFAULT_CITIES = [
  { key: "damascus", ar: "دمشق", en: "Damascus", shipping: 250 },
  { key: "aleppo", ar: "حلب", en: "Aleppo", shipping: 80 },
  { key: "homs", ar: "حمص", en: "Homs", shipping: 50 },
  { key: "latakia", ar: "اللاذقية", en: "Latakia", shipping: 70 },
  { key: "tartus", ar: "طرطوس", en: "Tartus", shipping: 70 },
  { key: "hama", ar: "حماة", en: "Hama", shipping: 60 },
  { key: "as-suwayda", ar: "السويداء", en: "As-Suwayda", shipping: 50 },
  { key: "daraa", ar: "درعا", en: "Daraa", shipping: 50 },
  { key: "deir ez-zor", ar: "دير الزور", en: "Deir ez-Zor", shipping: 100 },
  { key: "raqqa", ar: "الرقة", en: "Raqqa", shipping: 100 },
  { key: "al-hasakah", ar: "الحسكة", en: "Al-Hasakah", shipping: 120 },
  { key: "idlib", ar: "إدلب", en: "Idlib", shipping: 90 },
  { key: "quneitra", ar: "القنيطرة", en: "Quneitra", shipping: 60 },
];

const Checkout = () => {
  const { t, lang } = useLanguage();
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "").replace(/\/$/, "");
  const { items, totalPrice, clearCart } = useCart();
  const { user } = useAuth();
  const { config: storeConfig } = useStorefrontConfig();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [city, setCity] = useState(DEFAULT_CITIES[0]);
  const [form, setForm] = useState({ name: "", phone: "", email: "", address: "", notes: "" });
  const [submitting, setSubmitting] = useState(false);
  const [step, setStep] = useState(1);
  const [couponCode, setCouponCode] = useState("");
  const [appliedCoupon, setAppliedCoupon] = useState<{ code: string; discount_percent: number; id: string } | null>(null);
  const [validatingCoupon, setValidatingCoupon] = useState(false);
  const [availablePoints, setAvailablePoints] = useState(0);
  const [useLoyaltyPoints, setUseLoyaltyPoints] = useState(false);
  const [loyaltyPointsToRedeem, setLoyaltyPointsToRedeem] = useState(0);
  const loyaltyUnit = Math.max(1, Number(storeConfig.loyalty.redeem_points || 100));

  useEffect(() => {
    if (storeConfig.shipping.cities.length === 0) return;
    setCity((current) => storeConfig.shipping.cities.find((item) => item.key === current.key) || storeConfig.shipping.cities[0]);
  }, [storeConfig.shipping.cities]);

  // Redirect to cart if empty — must be in effect, not during render
  useEffect(() => {
    if (items.length === 0) navigate("/cart", { replace: true });
  }, [items.length, navigate]);

  useEffect(() => {
    const requested = Number(searchParams.get("loyalty_points") || 0);
    if (!storeConfig.loyalty.enabled || !user?.id) {
      setAvailablePoints(0);
      setUseLoyaltyPoints(false);
      setLoyaltyPointsToRedeem(0);
      return;
    }
    const configuredUnit = Math.max(1, Number(storeConfig.loyalty.redeem_points || 100));
    if (requested >= configuredUnit && requested % configuredUnit === 0) {
      setUseLoyaltyPoints(true);
      setLoyaltyPointsToRedeem(requested);
    } else {
      setUseLoyaltyPoints(false);
      setLoyaltyPointsToRedeem(0);
    }

    fetch(`${apiBaseUrl}/api/profiles/${user.id}`, { headers: withAuthHeaders({ Accept: "application/json" }) })
      .then((response) => response.json())
      .then((data) => {
        const points = Number(data?.profile?.loyalty_points || 0);
        setAvailablePoints(points);
        setLoyaltyPointsToRedeem(points >= loyaltyUnit ? Math.min(loyaltyUnit, Math.floor(points / loyaltyUnit) * loyaltyUnit) : 0);
      })
      .catch(() => {
        setAvailablePoints(0);
        setLoyaltyPointsToRedeem(0);
      });
  }, [apiBaseUrl, user?.id, searchParams, storeConfig.loyalty.enabled, storeConfig.loyalty.redeem_points]);

  const formatPrice = (price: number) =>
    new Intl.NumberFormat(lang === "ar" ? "ar-SY" : "en-SY").format(price);

  const shippingCost = totalPrice > storeConfig.shipping.free_threshold ? 0 : city.shipping;
  const discountAmount = appliedCoupon ? Math.round((totalPrice * appliedCoupon.discount_percent) / 100) : 0;
  const loyaltyPointsRedeemable = Math.max(0, Math.floor(availablePoints / loyaltyUnit) * loyaltyUnit);
  const loyaltyDiscount = useLoyaltyPoints ? Math.floor((loyaltyPointsToRedeem / loyaltyUnit) * Number(storeConfig.loyalty.redeem_discount || 0)) : 0;
  const combinedDiscountAmount = discountAmount + loyaltyDiscount;
  const grandTotal = Math.max(0, totalPrice + shippingCost - discountAmount - loyaltyDiscount);

  const applyCoupon = async () => {
    if (!couponCode.trim()) return;
    if (!form.phone.trim() || form.phone.trim().length < 6) {
      toast.error(lang === "ar" ? "أدخل رقم الهاتف أولاً لتطبيق الكوبون" : "Enter your phone number first to apply the coupon");
      return;
    }
    setValidatingCoupon(true);
    const code = couponCode.trim().toUpperCase();
    const response = await fetch(`${apiBaseUrl}/api/validate-coupon`, {
      method: "POST",
      headers: withAuthHeaders({
        "Content-Type": "application/json",
        Accept: "application/json",
      }),
      credentials: "include",
      body: JSON.stringify({ code, phone: form.phone.trim() }),
    });
    const data = await response.json();
    setValidatingCoupon(false);
    if (!data || !data.valid) {
      const reason = data?.reason;
      const msg =
        reason === "already_used" ? (lang === "ar" ? "لقد استخدمت هذا الكوبون سابقاً" : "You have already used this coupon")
        : reason === "not_assigned" ? (lang === "ar" ? "هذا الكوبون غير مخصص لك" : "This coupon is not assigned to you")
        : t("invalidCoupon");
      toast.error(msg);
      return;
    }
    setAppliedCoupon({ id: data.id, code: data.code, discount_percent: data.discount_percent });
    toast.success(t("couponApplied"));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.name || !form.phone || !form.address) {
      toast.error(lang === "ar" ? "يرجى ملء جميع الحقول المطلوبة" : "Please fill all required fields");
      return;
    }

    // Client-side phone validation to prevent sending a request that will be rejected.
    const phoneTrimmed = form.phone.trim();
    if (phoneTrimmed.length < 6) {
      toast.error(lang === "ar" ? "رقم الهاتف يجب أن يكون 6 أحرف على الأقل" : "The phone number must be at least 6 characters");
      return;
    }
    if (phoneTrimmed.length > 20) {
      toast.error(lang === "ar" ? "رقم الهاتف يجب ألا يتجاوز 20 حرفاً" : "The phone number must not exceed 20 characters");
      return;
    }

    if (useLoyaltyPoints) {
      if (!storeConfig.loyalty.enabled || loyaltyPointsToRedeem < loyaltyUnit || loyaltyPointsToRedeem > loyaltyPointsRedeemable || loyaltyPointsToRedeem % loyaltyUnit !== 0) {
        toast.error(lang === "ar" ? "أدخل عدد نقاط صحيح للاستبدال" : "Enter a valid number of points to redeem");
        return;
      }
    }

    setSubmitting(true);
    try {
      const orderItems = items.map((item) => ({
        product_id: item.id,
        product_variant_id: item.variantId || null,
        product_name: lang === "ar" ? item.nameAr : item.name,
        size: item.size,
        quantity: item.quantity,
        offer_id: item.offerId || null,
        offer_selection_id: item.offerSelectionId || null,
        offer_slot_id: item.offerSlotId || null,
      }));

      const idempotencyKey = typeof crypto !== "undefined" && "randomUUID" in crypto
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
      const createOrderResponse = await fetch(`${apiBaseUrl}/api/orders`, {
        method: "POST",
        credentials: "include",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "Idempotency-Key": idempotencyKey,
        },
        body: JSON.stringify({
          user_id: user?.id || null,
          customer_name: form.name,
          customer_phone: form.phone,
          customer_email: form.email || null,
          customer_address: form.address,
          city: lang === "ar" ? city.ar : city.en,
          subtotal: totalPrice,
          total: grandTotal,
          shipping_cost: shippingCost,
          payment_method: "cash_on_delivery",
          notes: form.notes || null,
          status: "pending",
          coupon_code: appliedCoupon?.code || null,
          loyalty_points_to_redeem: useLoyaltyPoints ? loyaltyPointsToRedeem : 0,
          items: orderItems,
        }),
      });

      const createOrderData = await createOrderResponse.json();

      if (!createOrderResponse.ok || !createOrderData?.order_id) {
        // Extract the most specific validation error from Laravel's 422 `errors` payload.
        let serverMessage =
          createOrderData?.message ||
          createOrderData?.error ||
          "Failed to create order";

        if (createOrderData?.errors && typeof createOrderData.errors === "object") {
          const firstKey = Object.keys(createOrderData.errors)[0];
          const firstError = firstKey ? createOrderData.errors[firstKey] : null;
          if (Array.isArray(firstError) && firstError.length > 0) {
            serverMessage = firstError[0];
          } else if (typeof firstError === "string") {
            serverMessage = firstError;
          }
        }

        toast.error(
          lang === "ar"
            ? `لم يتم إرسال الطلب: ${serverMessage}`
            : `Request not sent: ${serverMessage}`
        );
        return;
      }

      const orderId = createOrderData.order_id as string;
      if (useLoyaltyPoints && loyaltyPointsToRedeem > 0) {
        setAvailablePoints((current) => Math.max(0, current - loyaltyPointsToRedeem));
      }

      clearCart();
      toast.success(lang === "ar" ? "تم إرسال طلبك بنجاح! 🎉" : "Order placed successfully! 🎉");
      navigate(`/order-success/${orderId}?token=${encodeURIComponent(String(createOrderData.tracking_token || ""))}`);
    } catch (err) {
      // Network failure or unexpected error — request was not sent/completed.
      const isNetworkError = err instanceof TypeError || (err as Error)?.message === "Failed to fetch";
      toast.error(
        lang === "ar"
          ? isNetworkError
            ? "لم يتم إرسال الطلب: تعذر الاتصال بالخادم، تحقق من اتصالك بالإنترنت وحاول مرة أخرى"
            : `لم يتم إرسال الطلب: ${(err as Error)?.message || "حدث خطأ غير متوقع"}`
          : isNetworkError
            ? "Request not sent: Unable to reach the server. Check your connection and try again."
            : `Request not sent: ${(err as Error)?.message || "An unexpected error occurred"}`
      );
    } finally {
      setSubmitting(false);
    }
  };

  if (items.length === 0) return null;

  const inputClass =
    "w-full bg-background text-foreground px-4 py-3 rounded-xl border border-border outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary placeholder:text-muted-foreground transition-all duration-200";

  return (
    <div className="min-h-screen pt-20 lg:pt-24 pb-12">
      <SEO
        title={lang === "ar" ? "إتمام الطلب | روح" : "Checkout | Rouh"}
        description={lang === "ar" ? "أكمل بيانات التوصيل وادفع عند الاستلام لطلبك من عطور روح." : "Complete delivery details and pay cash on delivery for your Rouh perfumes order."}
        path="/checkout"
      />
      <div className="container mx-auto px-4 lg:px-8">
        <h1 className="sr-only">{lang === "ar" ? "إتمام الطلب" : "Checkout"}</h1>
        {/* Progress Steps */}
        <div className="flex items-center justify-center gap-2 mb-10">
          {[1, 2].map((s) => (
            <div key={s} className="flex items-center gap-2">
              <button
                onClick={() => setStep(s)}
                className={`w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300 ${
                  step >= s
                    ? "bg-primary text-primary-foreground shadow-gold"
                    : "bg-muted text-muted-foreground"
                }`}
              >
                {s}
              </button>
              <span className={`text-sm font-medium hidden sm:inline ${step >= s ? "text-foreground" : "text-muted-foreground"}`}>
                {s === 1
                  ? lang === "ar" ? "بيانات التوصيل" : "Delivery Info"
                  : lang === "ar" ? "تأكيد الطلب" : "Confirm Order"}
              </span>
              {s < 2 && <div className={`w-12 h-0.5 mx-2 ${step > s ? "bg-primary" : "bg-border"}`} />}
            </div>
          ))}
        </div>

        <form onSubmit={handleSubmit} className="grid grid-cols-1 lg:grid-cols-5 gap-8">
          {/* Customer details - 3 cols */}
          <motion.div
            initial={{ opacity: 0, x: -20 }}
            animate={{ opacity: 1, x: 0 }}
            className="lg:col-span-3 space-y-6"
          >
            <div className="bg-card border border-border rounded-2xl p-6 lg:p-8 space-y-5">
              <h2 className="text-xl font-bold text-foreground flex items-center gap-2">
                <Truck size={20} className="text-primary" />
                {t("customerDetails")}
              </h2>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label htmlFor="checkout-name" className="text-sm font-medium text-foreground mb-1.5 block">
                    {t("name")} <span className="text-destructive">*</span>
                  </label>
                  <input
                    id="checkout-name"
                    type="text"
                    placeholder={lang === "ar" ? "الاسم الكامل" : "Full Name"}
                    value={form.name}
                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                    className={inputClass}
                    required
                  />
                </div>
                <div>
                  <label htmlFor="checkout-phone" className="text-sm font-medium text-foreground mb-1.5 block">
                    {t("phone")} <span className="text-destructive">*</span>
                  </label>
                  <input
                    id="checkout-phone"
                    type="tel"
                    placeholder="+963 9XX XXX XXX"
                    value={form.phone}
                    onChange={(e) => setForm({ ...form, phone: e.target.value })}
                    className={inputClass}
                    dir="ltr"
                    required
                  />
                </div>
              </div>

              <div>
                <label htmlFor="checkout-city" className="text-sm font-medium text-foreground mb-1.5 block">
                  {t("city")} <span className="text-destructive">*</span>
                </label>
                <div className="relative">
                  <select
                    id="checkout-city"
                    value={lang === "ar" ? city.ar : city.en}
                    onChange={(e) => {
                      const found = storeConfig.shipping.cities.find((c) => (lang === "ar" ? c.ar : c.en) === e.target.value);
                      if (found) setCity(found);
                    }}
                    className={`${inputClass} appearance-none cursor-pointer`}
                  >
                    {storeConfig.shipping.cities.map((c) => (
                      <option key={c.key} value={lang === "ar" ? c.ar : c.en}>
                        {lang === "ar" ? c.ar : c.en}
                        {c.shipping === 0
                          ? ` (${lang === "ar" ? "شحن مجاني" : "Free shipping"})`
                          : ` (${formatPrice(c.shipping)} SYP)`}
                      </option>
                    ))}
                  </select>
                  <ChevronDown size={16} className="absolute end-4 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
                </div>
              </div>

              <div>
                <label htmlFor="checkout-address" className="text-sm font-medium text-foreground mb-1.5 block">
                  {t("deliveryAddress")} <span className="text-destructive">*</span>
                </label>
                <textarea
                  id="checkout-address"
                  placeholder={lang === "ar" ? "العنوان التفصيلي للتوصيل" : "Detailed delivery address"}
                  value={form.address}
                  onChange={(e) => setForm({ ...form, address: e.target.value })}
                  className={`${inputClass} h-24 resize-none`}
                  required
                />
              </div>

              <div>
                <label htmlFor="checkout-notes" className="text-sm font-medium text-foreground mb-1.5 block">
                  {lang === "ar" ? "ملاحظات (اختياري)" : "Notes (optional)"}
                </label>
                <textarea
                  id="checkout-notes"
                  placeholder={lang === "ar" ? "أي ملاحظات إضافية..." : "Any additional notes..."}
                  value={form.notes}
                  onChange={(e) => setForm({ ...form, notes: e.target.value })}
                  className={`${inputClass} h-20 resize-none`}
                />
              </div>

              {/* Payment method */}
              <div>
                <h3 className="text-sm font-medium text-foreground mb-3 flex items-center gap-2">
                  <CreditCard size={16} className="text-primary" />
                  {t("paymentMethod")}
                </h3>
                <div className="bg-background border-2 border-primary/30 rounded-xl p-4 flex items-center gap-3">
                  <div className="w-5 h-5 rounded-full border-2 border-primary flex items-center justify-center">
                    <div className="w-2.5 h-2.5 rounded-full bg-primary" />
                  </div>
                  <span className="text-foreground font-medium">{t("cashOnDelivery")}</span>
                </div>
              </div>
            </div>

            {/* Trust badges */}
            <div className="grid grid-cols-2 gap-3">
              <div className="bg-card border border-border rounded-xl p-4 flex items-center gap-3">
                <ShieldCheck size={20} className="text-primary shrink-0" />
                <span className="text-xs text-muted-foreground">
                  {lang === "ar" ? "معاملات آمنة 100%" : "100% Secure Transaction"}
                </span>
              </div>
              <div className="bg-card border border-border rounded-xl p-4 flex items-center gap-3">
                <MessageCircle size={20} className="text-[#25D366] shrink-0" />
                <span className="text-xs text-muted-foreground">
                  {lang === "ar" ? "دعم فوري عبر واتساب" : "Instant WhatsApp Support"}
                </span>
              </div>
            </div>
          </motion.div>

          {/* Order summary - 2 cols */}
          <motion.div
            initial={{ opacity: 0, x: 20 }}
            animate={{ opacity: 1, x: 0 }}
            className="lg:col-span-2"
          >
            <div className="space-y-4">
              <div className="bg-card border border-border rounded-2xl p-6 sticky top-28">
                <h2 className="text-xl font-bold text-foreground mb-5">{t("orderSummary")}</h2>

              <div className="space-y-3 mb-6 max-h-64 overflow-y-auto">
                {items.map((item) => (
                  <div key={`${item.id}-${item.size}-${item.variantId || "base"}`} className="flex gap-3 items-start">
                    <img
                      src={item.image}
                      alt={lang === "ar" ? item.nameAr : item.name}
                      className="w-14 h-14 rounded-lg object-cover border border-border"
                    />
                    <div className="flex-1 min-w-0">
                      <p className="font-medium text-foreground text-sm truncate">
                        {lang === "ar" ? item.nameAr : item.name}
                      </p>
                      <p className="text-xs text-muted-foreground">{item.size} × {item.quantity}</p>
                    </div>
                    <p className="text-sm font-bold text-foreground whitespace-nowrap">
                      {formatPrice(item.price * item.quantity)}
                    </p>
                  </div>
                ))}
              </div>

              <div className="border-t border-border pt-4 space-y-3">
                <div className="flex justify-between text-sm">
                  <span className="text-muted-foreground">{t("subtotal")}</span>
                  <span className="text-foreground">{formatPrice(totalPrice)} SYP</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-muted-foreground">{t("shipping")}</span>
                  <span className={shippingCost === 0 ? "text-green-500 font-medium" : "text-foreground"}>
                    {shippingCost === 0 ? (lang === "ar" ? "مجاني ✓" : "Free ✓") : `${formatPrice(shippingCost)} SYP`}
                  </span>
                </div>
                {appliedCoupon && (
                  <div className="flex justify-between text-sm">
                    <span className="text-green-500 font-medium">
                      {t("discount")} ({appliedCoupon.code} -{appliedCoupon.discount_percent}%)
                    </span>
                    <span className="text-green-500 font-medium">-{formatPrice(discountAmount)} SYP</span>
                  </div>
                )}
                {totalPrice < storeConfig.shipping.free_threshold && (
                  <p className="text-xs text-primary">
                    {lang === "ar"
                      ? `أضف ${formatPrice(storeConfig.shipping.free_threshold - totalPrice)} ل.س للشحن المجاني`
                      : `Add ${formatPrice(storeConfig.shipping.free_threshold - totalPrice)} SYP for free shipping`}
                  </p>
                )}

                {/* Coupon input */}
                <div className="pt-2">
                  {appliedCoupon ? (
                    <button
                      type="button"
                      onClick={() => { setAppliedCoupon(null); setCouponCode(""); }}
                      className="w-full text-xs text-muted-foreground hover:text-destructive flex items-center justify-center gap-1"
                    >
                      <X size={12} /> {t("removeCoupon")} {appliedCoupon.code}
                    </button>
                  ) : (
                    <div className="flex gap-2">
                      <div className="relative flex-1">
                        <Tag size={14} className="absolute start-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                        <input
                          type="text"
                          placeholder={t("couponCode")}
                          value={couponCode}
                          onChange={(e) => setCouponCode(e.target.value)}
                          className="w-full bg-background border border-border rounded-lg ps-9 pe-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/40 uppercase"
                          dir="ltr"
                        />
                      </div>
                      <button
                        type="button"
                        disabled={validatingCoupon || !couponCode.trim()}
                        onClick={applyCoupon}
                        className="bg-secondary text-secondary-foreground px-4 rounded-lg text-sm font-semibold hover:bg-secondary/80 disabled:opacity-50 flex items-center justify-center gap-2 min-w-[80px]"
                      >
                        {validatingCoupon ? (
                          <LoadingSpinner size="sm" />
                        ) : (
                          t("applyCoupon")
                        )}
                      </button>
                    </div>
                  )}
                </div>

                <div className="flex justify-between font-bold text-lg pt-3 border-t border-border">
                  <span className="text-foreground">{t("total")}</span>
                  <span className="text-primary">{formatPrice(grandTotal)} SYP</span>
                </div>

                {user?.id && storeConfig.loyalty.enabled && (
                  <div className="rounded-xl border border-border/80 bg-muted/20 p-4 space-y-3">
                    <div className="flex items-center justify-between text-sm">
                      <span className="text-muted-foreground">{lang === "ar" ? "رصيد النقاط" : "Points balance"}</span>
                      <span className="font-semibold text-foreground">{availablePoints.toLocaleString()}</span>
                    </div>

                    <label className="flex items-center gap-2 text-sm text-foreground">
                      <input
                        type="checkbox"
                        checked={useLoyaltyPoints}
                        onChange={(e) => setUseLoyaltyPoints(e.target.checked)}
                        className="h-4 w-4 rounded border-border text-primary focus:ring-primary"
                      />
                      {lang === "ar" ? "استخدم نقاط الولاء" : "Use loyalty points"}
                    </label>

                    {useLoyaltyPoints && (
                      <div>
                        <label htmlFor="loyalty-points" className="mb-1 block text-sm text-muted-foreground">
                          {lang === "ar" ? "النقاط المراد استبدالها" : "Points to redeem"}
                        </label>
                        <input
                          id="loyalty-points"
                          type="text" inputMode="decimal"
                          min={loyaltyUnit}
                          step={loyaltyUnit}
                          max={loyaltyPointsRedeemable}
                          value={loyaltyPointsToRedeem}
                          onChange={(e) => setLoyaltyPointsToRedeem(Math.max(0, Number(e.target.value || 0)))}
                          className="w-full bg-background text-foreground px-4 py-3 rounded-xl border border-border outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary placeholder:text-muted-foreground transition-all duration-200"
                        />
                        <p className="mt-2 text-xs text-muted-foreground">
                          {lang === "ar" ? `كل ${loyaltyUnit} نقطة = ${Number(storeConfig.loyalty.redeem_discount).toLocaleString()} ل.س خصم` : `Every ${loyaltyUnit} points = ${Number(storeConfig.loyalty.redeem_discount).toLocaleString()} SYP discount`}
                        </p>
                      </div>
                    )}
                  </div>
                )}
              </div>

              <button
                type="submit"
                disabled={submitting}
                className="mt-6 w-full bg-gradient-gold text-accent-foreground py-4 rounded-xl font-bold shadow-gold hover:shadow-gold-lg hover:opacity-95 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed text-lg flex items-center justify-center gap-2"
              >
                {submitting ? (
                  <>
                    <LoadingSpinner size="sm" />
                    {lang === "ar" ? "جاري الإرسال..." : "Processing..."}
                  </>
                ) : (
                  lang === "ar" ? "تأكيد الطلب" : "Place Order"
                )}
              </button>

              <p className="text-xs text-center text-muted-foreground mt-3">
                {lang === "ar"
                  ? "سيتم التواصل معك لتأكيد الطلب خلال 24 ساعة"
                  : "We'll contact you to confirm your order within 24 hours"}
              </p>
            </div>
            </div>
          </motion.div>
        </form>
      </div>
    </div>
  );
};

export default Checkout;
