import { useEffect, useMemo, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { useLanguage } from "@/hooks/useLanguage";
import { useCart } from "@/hooks/useCart";
import { ArrowRight, Check, Package2, Percent, ShoppingBag, AlertCircle } from "lucide-react";
import SEO from "@/components/SEO";
import { toast } from "sonner";

type PriceMode = "normal" | "free" | "percent_discount" | "fixed_discount" | "fixed_price" | "fixed_price_by_size";

type ProductVariant = {
  id: string;
  size: string;
  price: number | null;
  image_url: string | null;
};

type ProductOption = {
  id: string;
  name: string;
  name_ar: string;
  price: number;
  image_url: string | null;
  sizes: string[];
  size_prices: Record<string, number>;
  variants: ProductVariant[];
};

type OfferSlot = {
  id: string;
  label_ar: string;
  label_en: string;
  product_mode: "any" | "selected";
  product_ids: string[];
  size_mode: "any" | "selected";
  sizes: string[];
  price_mode: PriceMode;
  value: number;
  values_by_size: Record<string, number>;
};

type OfferConfig = {
  version?: number;
  pricing_mode: "slot_rules" | "fixed_total";
  fixed_total: number;
  slots: OfferSlot[];
};

type Offer = {
  id: string;
  offer_type: string;
  name: string;
  name_ar: string;
  description: string | null;
  description_ar: string | null;
  image_url: string | null;
  bundle_price: number;
  discount_percentage: number;
  active: boolean;
  starts_at: string | null;
  ends_at: string | null;
  target_product_id: string | null;
  offer_config: OfferConfig | null;
  selection_products: ProductOption[];
};

type Selection = { slot: OfferSlot; product: ProductOption; size: string; regularPrice: number; finalPrice: number };

const normalizeSizeKey = (value: string) => value.toLowerCase().replace(/\s+/g, "");
const unique = (values: string[]) => Array.from(new Set(values.filter(Boolean)));

const getPrice = (product: ProductOption, size: string) => {
  const variant = (product.variants || []).find((v) => normalizeSizeKey(v.size) === normalizeSizeKey(size) && typeof v.price === "number");
  if (variant?.price !== null && variant?.price !== undefined) return Number(variant.price);
  const direct = product.size_prices?.[size];
  if (direct !== undefined) return Number(direct);
  const found = Object.entries(product.size_prices || {}).find(([key]) => normalizeSizeKey(key) === normalizeSizeKey(size));
  return found ? Number(found[1]) : Number(product.price || 0);
};

const getVariantId = (product: ProductOption, size: string) => (product.variants || []).find((v) => normalizeSizeKey(v.size) === normalizeSizeKey(size))?.id;

const slotProducts = (slot: OfferSlot, products: ProductOption[]) => slot.product_mode === "selected"
  ? products.filter((p) => slot.product_ids.includes(p.id))
  : products;

const slotSizes = (slot: OfferSlot, product: ProductOption | undefined, products: ProductOption[]) => {
  if (slot.size_mode === "selected") return product ? slot.sizes.filter((size) => product.sizes.some((candidate) => normalizeSizeKey(candidate) === normalizeSizeKey(size))) : slot.sizes;
  if (product) return product.sizes;
  return unique(slotProducts(slot, products).flatMap((p) => p.sizes || []));
};

const calculateRulePrice = (slot: OfferSlot, base: number, size: string) => matchPriceMode(slot, base, size);
const matchPriceMode = (slot: OfferSlot, base: number, size: string) => {
  switch (slot.price_mode) {
    case "free": return 0;
    case "normal": return base;
    case "percent_discount": return Math.max(0, Math.round(base * (1 - Math.min(100, Math.max(0, Number(slot.value))) / 100) * 100) / 100);
    case "fixed_discount": return Math.max(0, Math.round((base - Math.max(0, Number(slot.value))) * 100) / 100);
    case "fixed_price": return Math.min(base, Math.max(0, Number(slot.value)));
    case "fixed_price_by_size": {
      const found = Object.entries(slot.values_by_size || {}).find(([key]) => normalizeSizeKey(key) === normalizeSizeKey(size));
      return found ? Math.min(base, Math.max(0, Number(found[1]))) : base;
    }
    default: return base;
  }
};

const BundleDetails = () => {
  const { id } = useParams();
  const { lang } = useLanguage();
  const { addItem } = useCart();
  const [offer, setOffer] = useState<Offer | null>(null);
  const [loading, setLoading] = useState(true);
  const [requestError, setRequestError] = useState<string>("");
  const [selected, setSelected] = useState<Record<string, { productId: string; size: string }>>({});
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "").replace(/\/$/, "");

  useEffect(() => {
    if (!id) return;
    let cancelled = false;
    setLoading(true);
    setRequestError("");

    const fetchOffer = async () => {
      const direct = await fetch(`${apiBaseUrl}/api/bundles/${encodeURIComponent(id)}`, { cache: "no-store", headers: { Accept: "application/json" } });
      const directData = await direct.json().catch(() => null);
      if (direct.ok && directData?.id) return directData;

      // Some shared-hosting / rewrite setups handle collection routes correctly
      // but fail on parameterized API routes. The list endpoint is the safe fallback.
      const list = await fetch(`${apiBaseUrl}/api/bundles`, { cache: "no-store", headers: { Accept: "application/json" } });
      const listData = await list.json().catch(() => null);
      if (list.ok && Array.isArray(listData)) {
        const match = listData.find((item: any) => String(item?.id) === String(id));
        if (match) {
          const detail = await fetch(`${apiBaseUrl}/api/bundles/${encodeURIComponent(id)}`, { cache: "no-store", headers: { Accept: "application/json" } }).catch(() => null);
          if (detail?.ok) return await detail.json();
          const productsResponse = await fetch(`${apiBaseUrl}/api/products`, { cache: "no-store", headers: { Accept: "application/json" } }).catch(() => null);
          const productsData = productsResponse?.ok ? await productsResponse.json().catch(() => null) : null;
          const allProducts = Array.isArray(productsData?.products) ? productsData.products : Array.isArray(productsData) ? productsData : [];
          if (allProducts.length) {
            const configSlots = Array.isArray(match?.offer_config?.slots) ? match.offer_config.slots : [];
            const ids = new Set(configSlots.flatMap((slot: any) => Array.isArray(slot?.product_ids) ? slot.product_ids.map(String) : []));
            const scoped = match.offer_type === "specific_product" && match.target_product_id
              ? allProducts.filter((p: any) => String(p.id) === String(match.target_product_id))
              : ids.size ? allProducts.filter((p: any) => ids.has(String(p.id))) : allProducts;
            return { ...match, selection_products: scoped.map((p: any) => ({ id: String(p.id), name: String(p.name || ""), name_ar: String(p.name_ar || p.name || ""), price: Number(p.price || 0), image_url: p.image_url || null, sizes: Array.isArray(p.sizes) ? p.sizes.map(String) : (Array.isArray(p.available_sizes) ? p.available_sizes.map(String) : []), size_prices: p.size_prices && typeof p.size_prices === "object" ? p.size_prices : {}, variants: Array.isArray(p.variants) ? p.variants.map((v: any) => ({ id: String(v.id), size: String(v.size_label || v.size || ""), price: v.selling_price_default != null ? Number(v.selling_price_default) : (v.price != null ? Number(v.price) : null), image_url: v.image_url || null })) : [] })) };
          }
          return match;
        }
      }
      throw new Error(directData?.message || `Offer request failed (${direct.status})`);
    };

    fetchOffer()
      .then((data) => { if (!cancelled) setOffer(data); })
      .catch((error) => { if (!cancelled) setRequestError(error instanceof Error ? error.message : String(error)); })
      .finally(() => { if (!cancelled) setLoading(false); });

    return () => { cancelled = true; };
  }, [apiBaseUrl, id]);

  const config = offer?.offer_config;
  const slots = useMemo(() => (Array.isArray(config?.slots) ? config!.slots : []), [config]);
  useEffect(() => {
    if (!offer || offer.offer_type !== "product_discount" || slots.length !== 1) return;
    const productsForSlot = slotProducts(slots[0], offer.selection_products);
    if (productsForSlot.length === 1 && !selected[slots[0].id]?.productId) {
      const sizes = slotSizes(slots[0], productsForSlot[0], offer.selection_products);
      setSelected((current) => ({ ...current, [slots[0].id]: { productId: productsForSlot[0].id, size: sizes[0] || "" } }));
    }
  }, [offer, selected, slots]);
  const formatPrice = (value: number) => new Intl.NumberFormat(lang === "ar" ? "ar-SY" : "en-SY").format(Math.round(value));

  const preview = useMemo(() => {
    if (!offer || !slots.length) return { valid: false, total: 0, regularTotal: 0, selections: [] as Selection[] };
    const selections: Selection[] = [];
    for (const slot of slots) {
      const value = selected[slot.id];
      const product = value?.productId ? slotProducts(slot, offer.selection_products).find((p) => p.id === value.productId) : undefined;
      if (!product || !value?.size) return { valid: false, total: 0, regularTotal: 0, selections: [] as Selection[] };
      const regularPrice = getPrice(product, value.size);
      selections.push({ slot, product, size: value.size, regularPrice, finalPrice: 0 });
    }

    const regularTotal = selections.reduce((sum, item) => sum + item.regularPrice, 0);
    if (config?.pricing_mode === "fixed_total") {
      const target = Number(config.fixed_total || 0);
      const paid = selections.filter((item) => item.slot.price_mode !== "free");
      const paidBase = paid.reduce((sum, item) => sum + item.regularPrice, 0);
      if (target <= 0 || target > paidBase + 0.005 || paidBase <= 0) return { valid: false, total: 0, regularTotal, selections };
      let allocated = 0;
      let paidIndex = 0;
      for (const item of selections) {
        if (item.slot.price_mode === "free") item.finalPrice = 0;
        else {
          paidIndex += 1;
          item.finalPrice = paidIndex === paid.length ? Math.max(0, target - allocated) : Math.min(item.regularPrice, Math.round(target * item.regularPrice / paidBase * 100) / 100);
          allocated += item.finalPrice;
        }
      }
    } else {
      for (const item of selections) item.finalPrice = calculateRulePrice(item.slot, item.regularPrice, item.size);
    }

    const total = selections.reduce((sum, item) => sum + item.finalPrice, 0);
    return { valid: true, total: Math.round(total * 100) / 100, regularTotal, selections };
  }, [config, offer, selected, slots]);

  const updateSelection = (slot: OfferSlot, productId: string) => {
    const product = offer?.selection_products.find((p) => p.id === productId);
    const sizes = product ? slotSizes(slot, product, offer?.selection_products || []) : [];
    setSelected((current) => ({ ...current, [slot.id]: { productId, size: sizes[0] || "" } }));
  };

  const addOffer = () => {
    if (!offer || !preview.valid || preview.selections.length !== slots.length) {
      toast.error(lang === "ar" ? "أكمل اختيار كل قطع العرض قبل الإضافة للسلة" : "Complete every offer item before adding it to the cart");
      return;
    }
    const selectionId = typeof crypto !== "undefined" && "randomUUID" in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    for (const item of preview.selections) {
      const variantId = getVariantId(item.product, item.size);
      if (!variantId) {
        toast.error(lang === "ar" ? `لا يوجد حجم نشط للعطر ${item.product.name_ar}` : `No active variant exists for ${item.product.name}`);
        return;
      }
      addItem({
        id: item.product.id,
        name: item.product.name,
        nameAr: item.product.name_ar,
        price: item.finalPrice,
        originalPrice: item.regularPrice,
        image: item.product.image_url || "/placeholder.svg",
        size: item.size,
        variantId,
        isBundle: true,
        offerId: offer.id,
        offerName: lang === "ar" ? offer.name_ar : offer.name,
        offerSelectionId: selectionId,
        offerSlotId: item.slot.id,
      });
    }
    toast.success(lang === "ar" ? "تمت إضافة العرض إلى السلة" : "Offer added to cart");
  };

  if (loading) return <div className="min-h-screen pt-28 flex items-center justify-center"><div className="h-10 w-10 animate-spin rounded-full border-2 border-gold border-t-transparent" /></div>;
  if (!offer) return <div className="min-h-screen pt-28 flex flex-col items-center justify-center gap-5 px-4 text-center"><AlertCircle className="h-16 w-16 text-muted-foreground" /><h1 className="text-2xl font-bold">{lang === "ar" ? "تعذر تحميل العرض" : "Unable to load offer"}</h1><p className="max-w-xl text-muted-foreground">{requestError || (lang === "ar" ? "العرض غير موجود أو لم يعد متاحًا." : "The offer was not found or is no longer available.")}</p><Link to="/bundles" className="text-gold inline-flex items-center gap-2">{lang === "ar" ? "العودة للعروض" : "Back to offers"}<ArrowRight className="h-4 w-4" /></Link></div>;

  const displayImage = offer.image_url || preview.selections[0]?.product.image_url || offer.selection_products[0]?.image_url || "/placeholder.svg";

  return (
    <div className="min-h-screen pt-20 lg:pt-24 pb-12">
      <SEO title={lang === "ar" ? `${offer.name_ar} | روح` : `${offer.name} | Rouh`} description={lang === "ar" ? offer.description_ar || offer.name_ar : offer.description || offer.name} path={`/bundle/${offer.id}`} />
      <div className="container mx-auto px-4 lg:px-8 py-8">
        <Link to="/bundles" className="text-sm text-muted-foreground inline-flex items-center gap-2 mb-6 hover:text-gold"><ArrowRight className={`h-4 w-4 ${lang === "ar" ? "" : "rotate-180"}`} />{lang === "ar" ? "العودة للعروض" : "Back to offers"}</Link>
        <div className="grid lg:grid-cols-[0.8fr_1.2fr] gap-10 items-start">
          <div className="relative rounded-3xl overflow-hidden border border-border bg-card lg:sticky lg:top-28">
            <img src={displayImage} alt={lang === "ar" ? offer.name_ar : offer.name} className="w-full aspect-square object-cover" />
            {Number(offer.discount_percentage) > 0 && <div className="absolute top-4 start-4 bg-gradient-to-r from-gold to-gold-dark text-accent-foreground font-bold px-4 py-2 rounded-full flex items-center gap-2"><Percent className="h-4 w-4" />{formatPrice(Number(offer.discount_percentage))}%</div>}
          </div>

          <div>
            <h1 className="font-display text-4xl font-bold text-gradient-gold mb-3">{lang === "ar" ? offer.name_ar : offer.name}</h1>
            {(lang === "ar" ? offer.description_ar : offer.description) && <p className="text-muted-foreground leading-7 mb-6">{lang === "ar" ? offer.description_ar : offer.description}</p>}

            <div className="space-y-4">
              <div className="rounded-2xl border border-gold/30 bg-gold/5 p-5"><div className="flex items-center gap-2 font-semibold"><Check className="h-5 w-5 text-gold" />{lang === "ar" ? "خصّص العرض كما تريد" : "Customize your offer"}</div><p className="text-sm text-muted-foreground mt-2">{lang === "ar" ? "كل قطعة لها شروطها الخاصة: العطر، الحجم، وقاعدة السعر." : "Each item has its own perfume, size and pricing rule."}</p></div>

              {slots.map((slot, index) => {
                const value = selected[slot.id];
                const product = value?.productId ? slotProducts(slot, offer.selection_products).find((p) => p.id === value.productId) : undefined;
                const productsForSlot = slotProducts(slot, offer.selection_products);
                const sizesForSlot = slotSizes(slot, product, offer.selection_products);
                const selection = preview.selections.find((item) => item.slot.id === slot.id);
                return <div key={slot.id} className="rounded-2xl border border-border bg-card p-5 space-y-4">
                  <div className="flex items-center justify-between gap-3"><div><h2 className="font-semibold text-lg">{lang === "ar" ? slot.label_ar || `القطعة ${index + 1}` : slot.label_en || `Item ${index + 1}`}</h2><p className="text-xs text-muted-foreground mt-1">{slot.price_mode === "free" ? (lang === "ar" ? "هذه القطعة مجانية" : "This item is free") : slot.price_mode === "normal" ? (lang === "ar" ? "بالسعر العادي" : "Normal price") : (lang === "ar" ? "عليها خصم ضمن العرض" : "Discounted within this offer")}</p></div>{slot.price_mode === "free" && <span className="text-xs bg-green-500/10 text-green-600 px-2 py-1 rounded-full font-medium">{lang === "ar" ? "مجاني" : "Free"}</span>}</div>

                  {offer.offer_type === "product_discount" ? <div className="rounded-xl border border-gold/20 bg-gold/5 px-4 py-3"><div className="text-xs text-muted-foreground">{lang === "ar" ? "العطر" : "Perfume"}</div><div className="font-semibold mt-1">{lang === "ar" ? productsForSlot[0]?.name_ar : productsForSlot[0]?.name}</div></div> : <select value={value?.productId || ""} onChange={(e) => updateSelection(slot, e.target.value)} className="h-12 w-full rounded-xl border border-input bg-background px-4 text-sm"><option value="">{lang === "ar" ? "اختر العطر" : "Choose perfume"}</option>{productsForSlot.map((p) => <option key={p.id} value={p.id}>{lang === "ar" ? p.name_ar : p.name}</option>)}</select>}

                  <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">{sizesForSlot.map((size) => {
                    const active = value?.size && normalizeSizeKey(value.size) === normalizeSizeKey(size);
                    const unavailableForProduct = product ? !product.sizes.some((s) => normalizeSizeKey(s) === normalizeSizeKey(size)) : false;
                    const price = product ? getPrice(product, size) : 0;
                    return <button type="button" key={size} disabled={!product || unavailableForProduct} onClick={() => setSelected((current) => ({ ...current, [slot.id]: { productId: product!.id, size } }))} className={`rounded-xl border p-4 text-start transition-all ${active ? "border-gold bg-gold/10" : "border-border hover:border-gold/50"} ${!product || unavailableForProduct ? "opacity-40 cursor-not-allowed" : ""}`}><div className="font-semibold">{size}</div>{product && <div className="text-xs text-muted-foreground mt-1">{formatPrice(price)} SYP</div>}</button>;
                  })}</div>
                  {selection && <div className="flex items-center justify-between text-sm pt-1"><span className="text-muted-foreground">{lang === "ar" ? "سعر هذه القطعة ضمن العرض" : "Offer price for this item"}</span><span className="font-bold text-gold">{formatPrice(selection.finalPrice)} SYP</span></div>}
                </div>;
              })}

              <div className="rounded-2xl bg-card border border-border p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4"><div><p className="text-sm text-muted-foreground">{lang === "ar" ? "السعر النهائي" : "Final price"}</p><p className="text-3xl font-bold text-gold">{preview.valid ? formatPrice(preview.total) : "—"} <span className="text-sm font-normal">SYP</span></p>{preview.valid && preview.regularTotal > preview.total && <p className="text-xs text-muted-foreground line-through">{formatPrice(preview.regularTotal)} SYP</p>}</div><button disabled={!preview.valid} onClick={addOffer} className="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-gold text-accent-foreground px-6 py-4 font-semibold shadow-gold disabled:opacity-50 disabled:cursor-not-allowed"><ShoppingBag className="h-5 w-5" />{lang === "ar" ? "أضف العرض للسلة" : "Add offer to cart"}</button></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default BundleDetails;
