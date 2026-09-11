import { useCallback, useEffect, useMemo, useState } from "react";
import { useLanguage } from "@/hooks/useLanguage";
import { withAuthHeaders } from "@/lib/auth";
import { defaultStorefrontConfig, invalidateStorefrontConfig, type StoreCity } from "@/hooks/useStorefrontConfig";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Plus, Pencil, Trash2, Truck, Gift, Package2, SlidersHorizontal, X, Copy } from "lucide-react";
import { toast } from "sonner";

interface ProductOption {
  id: string;
  name: string;
  name_ar: string;
  price: number;
  image_url?: string | null;
  sizes: string[];
  size_prices: Record<string, number>;
}

type PriceMode = "normal" | "free" | "percent_discount" | "fixed_discount" | "fixed_price" | "fixed_price_by_size";
type OfferType = "custom_bundle" | "product_discount";

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

interface Offer {
  id: string;
  offer_type: string;
  name: string;
  name_ar: string;
  description: string | null;
  description_ar: string | null;
  image_url: string | null;
  bundle_price: number;
  original_price: number;
  discount_percentage: number;
  active: boolean;
  stock: number;
  usage_limit: number | null;
  starts_at: string | null;
  ends_at: string | null;
  paid_quantity: number;
  free_quantity: number;
  target_product_id: string | null;
  allowed_sizes: string[];
  offer_config?: OfferConfig | null;
}

type SettingsForm = {
  shipping_free_threshold: number;
  shipping_city_rates: Record<string, number>;
  loyalty_enabled: boolean;
  loyalty_earn_amount: number;
  loyalty_earn_points: number;
  loyalty_redeem_points: number;
  loyalty_redeem_discount: number;
  quiz_discount_percent: number;
};

type OfferForm = {
  offer_type: OfferType;
  name: string;
  name_ar: string;
  description: string;
  description_ar: string;
  image_url: string;
  discount_percentage: number;
  active: boolean;
  stock: number;
  usage_limit: number | "";
  starts_at: string;
  ends_at: string;
  config: OfferConfig;
};

const makeSlot = (index: number): OfferSlot => ({
  id: `slot-${Date.now()}-${index}`,
  label_ar: `القطعة ${index + 1}`,
  label_en: `Item ${index + 1}`,
  product_mode: "any",
  product_ids: [],
  size_mode: "any",
  sizes: [],
  price_mode: "normal",
  value: 0,
  values_by_size: {},
});

const emptyOffer = (): OfferForm => ({
  offer_type: "custom_bundle",
  name: "",
  name_ar: "",
  description: "",
  description_ar: "",
  image_url: "",
  discount_percentage: 0,
  active: true,
  stock: 0,
  usage_limit: "",
  starts_at: "",
  ends_at: "",
  config: { version: 2, pricing_mode: "slot_rules", fixed_total: 0, slots: [makeSlot(0), makeSlot(1), makeSlot(2)] },
});

const defaultForm = (): SettingsForm => ({
  shipping_free_threshold: defaultStorefrontConfig.shipping.free_threshold,
  shipping_city_rates: Object.fromEntries(defaultStorefrontConfig.shipping.cities.map((c) => [c.key, c.shipping])),
  loyalty_enabled: defaultStorefrontConfig.loyalty.enabled,
  loyalty_earn_amount: defaultStorefrontConfig.loyalty.earn_amount,
  loyalty_earn_points: defaultStorefrontConfig.loyalty.earn_points,
  loyalty_redeem_points: defaultStorefrontConfig.loyalty.redeem_points,
  loyalty_redeem_discount: defaultStorefrontConfig.loyalty.redeem_discount,
  quiz_discount_percent: defaultStorefrontConfig.quiz.discount_percent,
});

const localDateTimeValue = (value?: string | null) => {
  if (!value) return "";
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return "";
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const normalizeSizeKey = (value: string) => value.toLowerCase().replace(/\s+/g, "");
const unique = (values: string[]) => Array.from(new Set(values.filter(Boolean)));

const AdminStoreSettings = () => {
  const { lang } = useLanguage();
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "").replace(/\/$/, "");
  const [form, setForm] = useState<SettingsForm>(defaultForm());
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [offers, setOffers] = useState<Offer[]>([]);
  const [products, setProducts] = useState<ProductOption[]>([]);
  const [offerOpen, setOfferOpen] = useState(false);
  const [editingOffer, setEditingOffer] = useState<Offer | null>(null);
  const [offerForm, setOfferForm] = useState<OfferForm>(emptyOffer());
  const [offerSaving, setOfferSaving] = useState(false);
  const cities = useMemo<StoreCity[]>(() => defaultStorefrontConfig.shipping.cities, []);

  const allSizes = useMemo(() => unique(products.flatMap((p) => p.sizes || [])), [products]);

  const load = useCallback(async () => {
    try {
      const [settingsResponse, offersResponse, productsResponse] = await Promise.all([
        fetch(`${apiBaseUrl}/api/admin/store-settings`, { headers: withAuthHeaders({ Accept: "application/json" }), credentials: "include" }),
        fetch(`${apiBaseUrl}/api/admin/bundles`, { headers: withAuthHeaders({ Accept: "application/json" }), credentials: "include" }),
        fetch(`${apiBaseUrl}/api/products`, { headers: { Accept: "application/json" }, credentials: "include" }),
      ]);
      const [settingsData, offersData, productsData] = await Promise.all([
        settingsResponse.json(), offersResponse.json(), productsResponse.json(),
      ]);
      const config = settingsData?.config || {};
      const incomingCities = Array.isArray(config?.shipping?.cities) ? config.shipping.cities : [];
      setForm({
        shipping_free_threshold: Number(config?.shipping?.free_threshold ?? defaultStorefrontConfig.shipping.free_threshold),
        shipping_city_rates: Object.fromEntries(cities.map((city) => [city.key, Number(incomingCities.find((item: StoreCity) => item.key === city.key)?.shipping ?? city.shipping)])),
        loyalty_enabled: Boolean(config?.loyalty?.enabled ?? defaultStorefrontConfig.loyalty.enabled),
        loyalty_earn_amount: Number(config?.loyalty?.earn_amount ?? defaultStorefrontConfig.loyalty.earn_amount),
        loyalty_earn_points: Number(config?.loyalty?.earn_points ?? defaultStorefrontConfig.loyalty.earn_points),
        loyalty_redeem_points: Number(config?.loyalty?.redeem_points ?? defaultStorefrontConfig.loyalty.redeem_points),
        loyalty_redeem_discount: Number(config?.loyalty?.redeem_discount ?? defaultStorefrontConfig.loyalty.redeem_discount),
        quiz_discount_percent: Number(config?.quiz?.discount_percent ?? defaultStorefrontConfig.quiz.discount_percent),
      });
      setOffers(Array.isArray(offersData?.bundles) ? offersData.bundles : []);
      setProducts(Array.isArray(productsData?.products) ? productsData.products.map((p: any) => ({
        id: String(p.id), name: String(p.name || ""), name_ar: String(p.name_ar || p.name || ""), price: Number(p.price || 0),
        image_url: p.image_url || null,
        sizes: Array.isArray(p.sizes) ? p.sizes.map(String) : (Array.isArray(p.available_sizes) ? p.available_sizes.map(String) : []),
        size_prices: p.size_prices && typeof p.size_prices === "object" ? p.size_prices : {},
      })) : []);
    } catch {
      toast.error(lang === "ar" ? "تعذر تحميل إعدادات المتجر" : "Could not load store settings");
    } finally {
      setLoading(false);
    }
  }, [apiBaseUrl, cities, lang]);

  useEffect(() => { void load(); }, [load]);

  const saveSettings = async () => {
    setSaving(true);
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/store-settings`, {
        method: "PUT",
        headers: { "Content-Type": "application/json", Accept: "application/json", ...withAuthHeaders() },
        credentials: "include",
        body: JSON.stringify(form),
      });
      const data = await response.json();
      if (!response.ok || !data?.ok) throw new Error(data?.message || "Save failed");
      invalidateStorefrontConfig();
      toast.success(lang === "ar" ? "تم حفظ إعدادات المتجر" : "Store settings saved");
      await load();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
    } finally {
      setSaving(false);
    }
  };

  const legacyToConfig = (offer: Offer): OfferConfig => {
    if (offer.offer_config?.slots?.length) return {
      version: 2,
      pricing_mode: offer.offer_config.pricing_mode || "slot_rules",
      fixed_total: Number(offer.offer_config.fixed_total || 0),
      slots: offer.offer_config.slots.map((slot, i) => ({
        ...makeSlot(i), ...slot,
        value: Number(slot.value || 0),
        values_by_size: slot.values_by_size || {},
        product_ids: Array.isArray(slot.product_ids) ? slot.product_ids.map(String) : [],
        sizes: Array.isArray(slot.sizes) ? slot.sizes.map(String) : [],
      })),
    };
    const sizes = Array.isArray(offer.allowed_sizes) ? offer.allowed_sizes : [];
    if (offer.offer_type === "specific_product") {
      return {
        version: 2, pricing_mode: "slot_rules", fixed_total: 0,
        slots: [{ ...makeSlot(0), label_ar: "العطر", label_en: "Perfume", product_mode: "selected", product_ids: offer.target_product_id ? [offer.target_product_id] : [], size_mode: sizes.length ? "selected" : "any", sizes, price_mode: "fixed_price", value: Number(offer.bundle_price || 0) }],
      };
    }
    if (offer.offer_type === "mix_match") {
      const total = Math.max(1, Number(offer.paid_quantity || 0) + Number(offer.free_quantity || 0));
      return {
        version: 2, pricing_mode: "fixed_total", fixed_total: Number(offer.bundle_price || 0),
        slots: Array.from({ length: total }, (_, i) => ({ ...makeSlot(i), size_mode: sizes.length ? "selected" : "any", sizes, price_mode: i >= Number(offer.paid_quantity || 0) ? "free" : "normal" })),
      };
    }
    return { version: 2, pricing_mode: "slot_rules", fixed_total: 0, slots: [makeSlot(0)] };
  };

  const openNewOffer = () => {
    setEditingOffer(null);
    setOfferForm(emptyOffer());
    setOfferOpen(true);
  };

  const openEditOffer = (offer: Offer) => {
    const config = legacyToConfig(offer);
    setEditingOffer(offer);
    setOfferForm({
      offer_type: offer.offer_type === "product_discount" || offer.offer_type === "specific_product" ? "product_discount" : "custom_bundle",
      name: offer.name || "", name_ar: offer.name_ar || "", description: offer.description || "", description_ar: offer.description_ar || "",
      image_url: offer.image_url || "", discount_percentage: Number(offer.discount_percentage || 0), active: Boolean(offer.active), stock: Number(offer.stock || 0),
      usage_limit: offer.usage_limit ?? "", starts_at: localDateTimeValue(offer.starts_at), ends_at: localDateTimeValue(offer.ends_at), config,
    });
    setOfferOpen(true);
  };

  const setSlots = (slots: OfferSlot[]) => setOfferForm((current) => ({ ...current, config: { ...current.config, slots } }));
  const updateSlot = (index: number, patch: Partial<OfferSlot>) => {
    const slots = offerForm.config.slots.map((slot, i) => i === index ? { ...slot, ...patch } : slot);
    setSlots(slots);
  };

  const addSlot = () => setSlots([...offerForm.config.slots, makeSlot(offerForm.config.slots.length)]);
  const duplicateSlot = (index: number) => {
    const source = offerForm.config.slots[index];
    const copy: OfferSlot = { ...source, id: makeSlot(index).id, product_ids: [...source.product_ids], sizes: [...source.sizes], values_by_size: { ...source.values_by_size } };
    setSlots([...offerForm.config.slots.slice(0, index + 1), copy, ...offerForm.config.slots.slice(index + 1)]);
  };
  const removeSlot = (index: number) => {
    if (offerForm.config.slots.length <= 1) return;
    setSlots(offerForm.config.slots.filter((_, i) => i !== index));
  };

  const slotProducts = (slot: OfferSlot) => slot.product_mode === "selected" ? products.filter((p) => slot.product_ids.includes(p.id)) : products;
  const slotAvailableSizes = (slot: OfferSlot) => unique(slotProducts(slot).flatMap((p) => p.sizes || []));

  const toggleSlotProduct = (index: number, productId: string) => {
    const slot = offerForm.config.slots[index];
    const next = slot.product_ids.includes(productId) ? slot.product_ids.filter((id) => id !== productId) : [...slot.product_ids, productId];
    updateSlot(index, { product_ids: next });
  };
  const toggleSlotSize = (index: number, size: string) => {
    const slot = offerForm.config.slots[index];
    const next = slot.sizes.includes(size) ? slot.sizes.filter((s) => normalizeSizeKey(s) !== normalizeSizeKey(size)) : [...slot.sizes, size];
    updateSlot(index, { sizes: next });
  };

  const saveOffer = async () => {
    if (!offerForm.name.trim() || !offerForm.name_ar.trim()) {
      toast.error(lang === "ar" ? "اكتب اسم العرض بالعربي والإنكليزي" : "Enter the offer name in Arabic and English"); return;
    }
    const slots = offerForm.config.slots;
    if (offerForm.offer_type === "custom_bundle" && slots.length < 1) {
      toast.error(lang === "ar" ? "أضف قطعة واحدة على الأقل للعرض" : "Add at least one offer item"); return;
    }
    if (offerForm.config.pricing_mode === "fixed_total" && offerForm.config.fixed_total <= 0) {
      toast.error(lang === "ar" ? "ضع السعر الإجمالي للعرض" : "Enter the fixed offer total"); return;
    }
    for (let i = 0; i < slots.length; i++) {
      const slot = slots[i];
      if (offerForm.offer_type === "product_discount" && (slot.product_mode !== "selected" || slot.product_ids.length !== 1)) {
        toast.error(lang === "ar" ? "اختر عطراً واحداً لعرض المنتج" : "Choose exactly one perfume for a product discount"); return;
      }
      if (slot.product_mode === "selected" && slot.product_ids.length === 0) {
        toast.error(lang === "ar" ? `اختر العطور للقطعة ${i + 1}` : `Select perfumes for item ${i + 1}`); return;
      }
      if (slot.size_mode === "selected" && slot.sizes.length === 0) {
        toast.error(lang === "ar" ? `اختر الأحجام للقطعة ${i + 1}` : `Select sizes for item ${i + 1}`); return;
      }
      if (["percent_discount", "fixed_discount", "fixed_price"].includes(slot.price_mode) && Number(slot.value) < 0) {
        toast.error(lang === "ar" ? `قيمة السعر/الخصم غير صحيحة للقطعة ${i + 1}` : `Invalid price/discount for item ${i + 1}`); return;
      }
      if (slot.price_mode === "percent_discount" && Number(slot.value) > 100) {
        toast.error(lang === "ar" ? "نسبة الخصم لا يمكن أن تتجاوز 100%" : "Percentage discount cannot exceed 100%"); return;
      }
      if (slot.price_mode === "fixed_price_by_size" && Object.keys(slot.values_by_size || {}).length === 0) {
        toast.error(lang === "ar" ? `ضع سعرًا للأحجام في القطعة ${i + 1}` : `Enter size prices for item ${i + 1}`); return;
      }
    }

    setOfferSaving(true);
    try {
      const payload = {
        offer_type: offerForm.offer_type,
        name: offerForm.name.trim(), name_ar: offerForm.name_ar.trim(),
        description: offerForm.description || null, description_ar: offerForm.description_ar || null,
        image_url: offerForm.image_url || null,
        bundle_price: offerForm.config.pricing_mode === "fixed_total" ? Number(offerForm.config.fixed_total) : 0,
        original_price: 0,
        discount_percentage: Number(offerForm.discount_percentage) || 0,
        active: offerForm.active, stock: Number(offerForm.stock || 0),
        usage_limit: offerForm.usage_limit === "" ? null : Number(offerForm.usage_limit),
        starts_at: offerForm.starts_at || null, ends_at: offerForm.ends_at || null,
        offer_config: {
          version: 2,
          pricing_mode: offerForm.offer_type === "product_discount" ? "slot_rules" : offerForm.config.pricing_mode,
          fixed_total: Number(offerForm.config.fixed_total || 0),
          slots: offerForm.config.slots.map((slot) => ({ ...slot, value: Number(slot.value || 0), values_by_size: slot.values_by_size || {} })),
        },
      };
      const response = await fetch(`${apiBaseUrl}/api/admin/bundles${editingOffer ? `/${editingOffer.id}` : ""}`, {
        method: editingOffer ? "PATCH" : "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json", ...withAuthHeaders() },
        credentials: "include",
        body: JSON.stringify(payload),
      });
      const data = await response.json();
      if (!response.ok || !data?.ok) throw new Error(data?.message || "Save failed");
      toast.success(lang === "ar" ? "تم حفظ العرض" : "Offer saved");
      setOfferOpen(false);
      await load();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
    } finally {
      setOfferSaving(false);
    }
  };

  const deleteOffer = async (id: string) => {
    if (!window.confirm(lang === "ar" ? "حذف هذا العرض؟" : "Delete this offer?")) return;
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/bundles/${id}`, { method: "DELETE", headers: withAuthHeaders({ Accept: "application/json" }), credentials: "include" });
      const data = await response.json();
      if (!response.ok || !data?.ok) throw new Error(data?.message || "Delete failed");
      toast.success(lang === "ar" ? "تم حذف العرض" : "Offer deleted");
      await load();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
    }
  };

  const offerLabel = (offer: Offer) => {
    const config = offer.offer_config;
    if (offer.offer_type === "product_discount" || offer.offer_type === "specific_product") return lang === "ar" ? "خصم على عطر محدد" : "Product discount";
    if (config?.slots?.length) {
      const free = config.slots.filter((s) => s.price_mode === "free").length;
      return lang === "ar" ? `${config.slots.length} قطع${free ? ` — ${free} مجانية` : ""}` : `${config.slots.length} items${free ? ` — ${free} free` : ""}`;
    }
    return lang === "ar" ? "عرض خاص" : "Special offer";
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-foreground">{lang === "ar" ? "إعدادات المتجر" : "Store Settings"}</h1>
        <p className="text-muted-foreground mt-1">{lang === "ar" ? "الشحن والولاء وخصم اختبار العطر والعروض." : "Shipping, loyalty, quiz discount and offers."}</p>
      </div>

      <div className="grid xl:grid-cols-3 gap-5">
        <Card>
          <CardHeader><CardTitle className="flex items-center gap-2"><Truck className="h-5 w-5 text-gold" />{lang === "ar" ? "الشحن" : "Shipping"}</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div><Label>{lang === "ar" ? "الشحن مجاني فوق" : "Free shipping above"}</Label><Input type="number" min={0} value={form.shipping_free_threshold} onChange={(e) => setForm({ ...form, shipping_free_threshold: Number(e.target.value) })} /></div>
            <div className="grid grid-cols-2 gap-3 max-h-96 overflow-y-auto pe-1">{cities.map((city) => <div key={city.key}><Label>{lang === "ar" ? city.ar : city.en}</Label><Input type="number" min={0} value={form.shipping_city_rates[city.key] ?? 0} onChange={(e) => setForm({ ...form, shipping_city_rates: { ...form.shipping_city_rates, [city.key]: Number(e.target.value) } })} /></div>)}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle className="flex items-center gap-2"><Gift className="h-5 w-5 text-gold" />{lang === "ar" ? "نقاط الولاء" : "Loyalty Points"}</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <label className="flex items-center gap-3 cursor-pointer"><Checkbox checked={form.loyalty_enabled} onCheckedChange={(checked) => setForm({ ...form, loyalty_enabled: checked === true })} /><span>{lang === "ar" ? "تفعيل برنامج الولاء" : "Enable loyalty program"}</span></label>
            <div><Label>{lang === "ar" ? "كل مبلغ (ل.س)" : "Every amount (SYP)"}</Label><Input type="number" min={1} value={form.loyalty_earn_amount} onChange={(e) => setForm({ ...form, loyalty_earn_amount: Number(e.target.value) })} /></div>
            <div><Label>{lang === "ar" ? "النقاط المكتسبة" : "Points earned"}</Label><Input type="number" min={1} value={form.loyalty_earn_points} onChange={(e) => setForm({ ...form, loyalty_earn_points: Number(e.target.value) })} /></div>
            <div><Label>{lang === "ar" ? "نقاط الاستبدال" : "Redemption points"}</Label><Input type="number" min={1} value={form.loyalty_redeem_points} onChange={(e) => setForm({ ...form, loyalty_redeem_points: Number(e.target.value) })} /></div>
            <div><Label>{lang === "ar" ? "قيمة الخصم لكل وحدة" : "Discount per unit"}</Label><Input type="number" min={0} value={form.loyalty_redeem_discount} onChange={(e) => setForm({ ...form, loyalty_redeem_discount: Number(e.target.value) })} /></div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle className="flex items-center gap-2"><SlidersHorizontal className="h-5 w-5 text-gold" />{lang === "ar" ? "اختبار العطر" : "Perfume Quiz"}</CardTitle></CardHeader>
          <CardContent><div><Label>{lang === "ar" ? "نسبة خصم الاختبار %" : "Quiz discount %"}</Label><Input type="number" min={0} max={100} value={form.quiz_discount_percent} onChange={(e) => setForm({ ...form, quiz_discount_percent: Number(e.target.value) })} /></div></CardContent>
        </Card>
      </div>

      <Button onClick={() => void saveSettings()} disabled={saving || loading} className="w-full sm:w-auto bg-gradient-gold text-accent-foreground">{saving ? (lang === "ar" ? "جاري الحفظ..." : "Saving...") : (lang === "ar" ? "حفظ الإعدادات" : "Save settings")}</Button>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between gap-4">
          <div><CardTitle className="flex items-center gap-2"><Package2 className="h-5 w-5 text-gold" />{lang === "ar" ? "العروض" : "Offers"}</CardTitle><p className="text-sm text-muted-foreground mt-1">{lang === "ar" ? "أنشئ عرضًا مرنًا: كل قطعة يمكن أن يكون لها عطر وحجم وقاعدة سعر مختلفة." : "Build flexible offers where every item can have its own perfume, size and pricing rule."}</p></div>
          <Button onClick={openNewOffer}><Plus className="h-4 w-4 me-2" />{lang === "ar" ? "إضافة عرض" : "Add offer"}</Button>
        </CardHeader>
        <CardContent>
          <div className="border rounded-xl overflow-x-auto">
            <table className="w-full min-w-[900px] text-sm">
              <thead className="bg-muted/40"><tr className="border-b"><th className="text-start p-3">{lang === "ar" ? "العرض" : "Offer"}</th><th className="text-start p-3">{lang === "ar" ? "النوع" : "Type"}</th><th className="text-start p-3">{lang === "ar" ? "القواعد" : "Rules"}</th><th className="text-start p-3">{lang === "ar" ? "السعر" : "Price"}</th><th className="text-start p-3">{lang === "ar" ? "الحالة" : "Status"}</th><th className="p-3" /></tr></thead>
              <tbody>
                {offers.map((offer) => <tr key={offer.id} className="border-b last:border-0 align-top">
                  <td className="p-3"><div className="font-semibold">{lang === "ar" ? offer.name_ar : offer.name}</div><div className="text-xs text-muted-foreground mt-1">{offerLabel(offer)}</div></td>
                  <td className="p-3">{offer.offer_type === "product_discount" || offer.offer_type === "specific_product" ? (lang === "ar" ? "خصم على عطر" : "Product discount") : (lang === "ar" ? "عرض مركب" : "Custom bundle")}</td>
                  <td className="p-3"><div>{offer.offer_config?.slots?.length || 0} {lang === "ar" ? "قطع" : "items"}</div>{offer.offer_config?.slots?.slice(0, 3).map((slot, i) => <div key={slot.id || i} className="text-xs text-muted-foreground">{i + 1}. {slot.price_mode === "free" ? (lang === "ar" ? "مجاني" : "Free") : slot.size_mode === "selected" ? slot.sizes.join("، ") : (lang === "ar" ? "كل الأحجام" : "Any size")}</div>)}</td>
                  <td className="p-3">{offer.offer_config?.pricing_mode === "fixed_total" ? `${Number(offer.offer_config.fixed_total).toLocaleString()} SYP` : (lang === "ar" ? "حسب القواعد" : "Rule based")}</td>
                  <td className="p-3">{offer.active ? (lang === "ar" ? "نشط" : "Active") : (lang === "ar" ? "متوقف" : "Disabled")}</td>
                  <td className="p-3"><div className="flex justify-end gap-2"><Button size="icon" variant="outline" onClick={() => openEditOffer(offer)}><Pencil className="h-4 w-4" /></Button><Button size="icon" variant="destructive" onClick={() => void deleteOffer(offer.id)}><Trash2 className="h-4 w-4" /></Button></div></td>
                </tr>)}
                {offers.length === 0 && <tr><td colSpan={6} className="p-8 text-center text-muted-foreground">{lang === "ar" ? "لا توجد عروض بعد" : "No offers yet"}</td></tr>}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>

      <Dialog open={offerOpen} onOpenChange={setOfferOpen}>
        <DialogContent className="max-w-6xl max-h-[94vh] overflow-y-auto">
          <DialogHeader><DialogTitle>{editingOffer ? (lang === "ar" ? "تعديل العرض" : "Edit offer") : (lang === "ar" ? "إنشاء عرض جديد" : "Create offer")}</DialogTitle></DialogHeader>

          <div className="space-y-6">
            <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
              <div className="lg:col-span-2"><Label>{lang === "ar" ? "اسم العرض بالعربي" : "Arabic name"}</Label><Input value={offerForm.name_ar} onChange={(e) => setOfferForm({ ...offerForm, name_ar: e.target.value })} placeholder={lang === "ar" ? "عبوتين 100 مل والثالثة 50 مل مجاناً" : ""} /></div>
              <div className="lg:col-span-2"><Label>{lang === "ar" ? "اسم العرض بالإنكليزي" : "English name"}</Label><Input value={offerForm.name} onChange={(e) => setOfferForm({ ...offerForm, name: e.target.value })} placeholder="2 x 100ml + 50ml free" /></div>
              <div><Label>{lang === "ar" ? "نوع العرض" : "Offer type"}</Label><select value={offerForm.offer_type} onChange={(e) => {
                const type = e.target.value as OfferType;
                setOfferForm((current) => ({ ...current, offer_type: type, config: type === "product_discount" ? { version: 2, pricing_mode: "slot_rules", fixed_total: 0, slots: [{ ...makeSlot(0), product_mode: "selected", label_ar: "العطر", label_en: "Perfume" }] } : { ...current.config, pricing_mode: current.config.pricing_mode || "slot_rules" } }));
              }} className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"><option value="custom_bundle">{lang === "ar" ? "عرض مركب قابل للتخصيص" : "Custom bundle"}</option><option value="product_discount">{lang === "ar" ? "خصم على عطر محدد" : "Specific product discount"}</option></select></div>
              <div><Label>{lang === "ar" ? "نسبة الخصم الظاهرة % (اختياري)" : "Displayed discount % (optional)"}</Label><Input type="number" min={0} max={100} value={offerForm.discount_percentage} onChange={(e) => setOfferForm({ ...offerForm, discount_percentage: Number(e.target.value) })} /></div>
              <div><Label>{lang === "ar" ? "صورة العرض (اختياري)" : "Offer image (optional)"}</Label><Input value={offerForm.image_url} onChange={(e) => setOfferForm({ ...offerForm, image_url: e.target.value })} placeholder="https://..." /></div>
              <div><Label>{lang === "ar" ? "حد استخدام العرض (اختياري)" : "Usage limit (optional)"}</Label><Input type="number" min={1} value={offerForm.usage_limit} onChange={(e) => setOfferForm({ ...offerForm, usage_limit: e.target.value === "" ? "" : Number(e.target.value) })} /></div>
            </div>

            {offerForm.offer_type === "custom_bundle" && <div className="rounded-2xl border border-gold/30 bg-gold/5 p-5 space-y-4">
              <div className="flex flex-wrap items-end justify-between gap-4"><div><h3 className="font-semibold text-lg">{lang === "ar" ? "تسعير العرض" : "Offer pricing"}</h3><p className="text-sm text-muted-foreground mt-1">{lang === "ar" ? "إما نطبق قاعدة سعر كل قطعة، أو تحدد سعرًا إجماليًا ثابتًا للعرض." : "Use item rules, or set one fixed total for the whole offer."}</p></div>
                <div className="min-w-[250px]"><Label>{lang === "ar" ? "طريقة التسعير" : "Pricing mode"}</Label><select value={offerForm.config.pricing_mode} onChange={(e) => setOfferForm({ ...offerForm, config: { ...offerForm.config, pricing_mode: e.target.value as OfferConfig["pricing_mode"] } })} className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"><option value="slot_rules">{lang === "ar" ? "حسب قاعدة كل قطعة" : "Per-item rules"}</option><option value="fixed_total">{lang === "ar" ? "سعر إجمالي ثابت" : "Fixed total"}</option></select></div>
              </div>
              {offerForm.config.pricing_mode === "fixed_total" && <div className="max-w-sm"><Label>{lang === "ar" ? "السعر الإجمالي للعرض" : "Fixed total price"}</Label><Input type="number" min={0} value={offerForm.config.fixed_total} onChange={(e) => setOfferForm({ ...offerForm, config: { ...offerForm.config, fixed_total: Number(e.target.value) } })} /><p className="text-xs text-muted-foreground mt-1">{lang === "ar" ? "القطع المجانية تبقى 0، والباقي يتوزع عليه السعر الإجمالي تلقائيًا." : "Free items remain 0; the fixed total is allocated across paid items."}</p></div>}
            </div>}

            <div className="space-y-4">
              {offerForm.config.slots.map((slot, index) => {
                const availableSizes = slotAvailableSizes(slot);
                return <div key={slot.id} className="rounded-2xl border border-border bg-card p-5 space-y-5 shadow-sm">
                  <div className="flex items-center justify-between gap-3"><div><h3 className="font-semibold text-lg">{lang === "ar" ? `القطعة ${index + 1}` : `Item ${index + 1}`}</h3><p className="text-xs text-muted-foreground mt-1">{lang === "ar" ? "حدد العطر والحجم وقاعدة السعر لهذه القطعة فقط." : "Configure the perfume, size and price rule for this item."}</p></div><div className="flex gap-2"><Button type="button" variant="outline" size="icon" title={lang === "ar" ? "تكرار" : "Duplicate"} onClick={() => duplicateSlot(index)}><Copy className="h-4 w-4" /></Button><Button type="button" variant="destructive" size="icon" disabled={offerForm.config.slots.length <= 1} onClick={() => removeSlot(index)}><X className="h-4 w-4" /></Button></div></div>

                  <div className="grid lg:grid-cols-4 gap-4">
                    <div><Label>{lang === "ar" ? "اسم القطعة بالعربي" : "Item label (Arabic)"}</Label><Input value={slot.label_ar} onChange={(e) => updateSlot(index, { label_ar: e.target.value })} /></div>
                    <div><Label>{lang === "ar" ? "اسم القطعة بالإنكليزي" : "Item label (English)"}</Label><Input value={slot.label_en} onChange={(e) => updateSlot(index, { label_en: e.target.value })} /></div>
                    <div><Label>{lang === "ar" ? "اختيار العطر" : "Perfume selection"}</Label><select value={slot.product_mode} disabled={offerForm.offer_type === "product_discount"} onChange={(e) => updateSlot(index, { product_mode: e.target.value as OfferSlot["product_mode"], product_ids: e.target.value === "any" ? [] : slot.product_ids })} className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"><option value="any">{lang === "ar" ? "الزبون يختار من كل العطور" : "Customer can choose any perfume"}</option><option value="selected">{lang === "ar" ? "أنا أحدد العطور" : "I choose the perfumes"}</option></select></div>
                    <div><Label>{lang === "ar" ? "اختيار الحجم" : "Size selection"}</Label><select value={slot.size_mode} onChange={(e) => updateSlot(index, { size_mode: e.target.value as OfferSlot["size_mode"], sizes: e.target.value === "any" ? [] : slot.sizes })} className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"><option value="any">{lang === "ar" ? "الزبون يختار أي حجم متاح" : "Customer can choose any available size"}</option><option value="selected">{lang === "ar" ? "أنا أحدد الأحجام" : "I choose the allowed sizes"}</option></select></div>
                  </div>

                  {slot.product_mode === "selected" && <div><Label>{lang === "ar" ? "العطور المسموحة لهذه القطعة" : "Allowed perfumes for this item"}</Label><div className="mt-2 grid sm:grid-cols-2 lg:grid-cols-4 gap-2 max-h-52 overflow-y-auto border rounded-xl p-3">{products.map((product) => <label key={product.id} className={`flex items-center gap-2 rounded-lg border px-3 py-2 cursor-pointer ${slot.product_ids.includes(product.id) ? "border-gold bg-gold/10" : "border-border"}`}><Checkbox checked={slot.product_ids.includes(product.id)} onCheckedChange={() => toggleSlotProduct(index, product.id)} /><span className="text-sm truncate">{lang === "ar" ? product.name_ar : product.name}</span></label>)}</div></div>}

                  {slot.size_mode === "selected" && <div><Label>{lang === "ar" ? "الأحجام المسموحة لهذه القطعة" : "Allowed sizes for this item"}</Label><div className="mt-2 flex flex-wrap gap-2">{(slot.product_mode === "selected" ? availableSizes : allSizes).map((size) => <label key={size} className={`flex items-center gap-2 rounded-lg border px-3 py-2 cursor-pointer ${slot.sizes.some((s) => normalizeSizeKey(s) === normalizeSizeKey(size)) ? "border-gold bg-gold/10" : "border-border"}`}><Checkbox checked={slot.sizes.some((s) => normalizeSizeKey(s) === normalizeSizeKey(size))} onCheckedChange={() => toggleSlotSize(index, size)} /><span className="text-sm">{size}</span></label>)}</div></div>}

                  <div className="grid md:grid-cols-3 gap-4 items-end">
                    <div><Label>{lang === "ar" ? "قاعدة سعر القطعة" : "Item pricing rule"}</Label><select value={slot.price_mode} onChange={(e) => updateSlot(index, { price_mode: e.target.value as PriceMode })} className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"><option value="normal">{lang === "ar" ? "السعر العادي" : "Normal price"}</option><option value="free">{lang === "ar" ? "مجاني" : "Free"}</option><option value="percent_discount">{lang === "ar" ? "خصم بنسبة مئوية" : "Percentage discount"}</option><option value="fixed_discount">{lang === "ar" ? "خصم بمبلغ" : "Fixed amount discount"}</option><option value="fixed_price">{lang === "ar" ? "سعر ثابت للقطعة" : "Fixed price"}</option><option value="fixed_price_by_size">{lang === "ar" ? "سعر مختلف حسب الحجم" : "Price by size"}</option></select></div>
                    {slot.price_mode === "percent_discount" && <div><Label>{lang === "ar" ? "نسبة الخصم %" : "Discount %"}</Label><Input type="number" min={0} max={100} value={slot.value} onChange={(e) => updateSlot(index, { value: Number(e.target.value) })} /></div>}
                    {slot.price_mode === "fixed_discount" && <div><Label>{lang === "ar" ? "مبلغ الخصم" : "Discount amount"}</Label><Input type="number" min={0} value={slot.value} onChange={(e) => updateSlot(index, { value: Number(e.target.value) })} /></div>}
                    {slot.price_mode === "fixed_price" && <div><Label>{lang === "ar" ? "سعر القطعة" : "Fixed item price"}</Label><Input type="number" min={0} value={slot.value} onChange={(e) => updateSlot(index, { value: Number(e.target.value) })} /></div>}
                  </div>

                  {slot.price_mode === "fixed_price_by_size" && <div className="rounded-xl border bg-muted/20 p-4"><Label>{lang === "ar" ? "سعر كل حجم" : "Price for each size"}</Label><div className="mt-3 grid sm:grid-cols-2 md:grid-cols-4 gap-3">{(slot.product_mode === "selected" ? availableSizes : allSizes).map((size) => <div key={size}><Label className="text-xs">{size}</Label><Input type="number" min={0} value={slot.values_by_size?.[size] ?? ""} onChange={(e) => updateSlot(index, { values_by_size: { ...slot.values_by_size, [size]: e.target.value === "" ? 0 : Number(e.target.value) } })} /></div>)}</div></div>}
                </div>;
              })}
            </div>

            {offerForm.offer_type === "custom_bundle" && <Button type="button" variant="outline" onClick={addSlot}><Plus className="h-4 w-4 me-2" />{lang === "ar" ? "إضافة قطعة جديدة" : "Add another item"}</Button>}

            <div className="grid md:grid-cols-2 gap-4">
              <div><Label>{lang === "ar" ? "يبدأ" : "Starts"}</Label><Input type="datetime-local" value={offerForm.starts_at} onChange={(e) => setOfferForm({ ...offerForm, starts_at: e.target.value })} /></div>
              <div><Label>{lang === "ar" ? "ينتهي" : "Ends"}</Label><Input type="datetime-local" value={offerForm.ends_at} onChange={(e) => setOfferForm({ ...offerForm, ends_at: e.target.value })} /></div>
              <div><Label>{lang === "ar" ? "وصف عربي (اختياري)" : "Arabic description (optional)"}</Label><Input value={offerForm.description_ar} onChange={(e) => setOfferForm({ ...offerForm, description_ar: e.target.value })} /></div>
              <div><Label>{lang === "ar" ? "وصف إنكليزي (اختياري)" : "English description (optional)"}</Label><Input value={offerForm.description} onChange={(e) => setOfferForm({ ...offerForm, description: e.target.value })} /></div>
            </div>

            <label className="flex items-center gap-3 cursor-pointer"><Checkbox checked={offerForm.active} onCheckedChange={(checked) => setOfferForm({ ...offerForm, active: checked === true })} /><span>{lang === "ar" ? "العرض نشط ويظهر للزبائن" : "Active and visible"}</span></label>
            <Button onClick={() => void saveOffer()} disabled={offerSaving} className="w-full bg-gradient-gold text-accent-foreground">{offerSaving ? (lang === "ar" ? "جاري الحفظ..." : "Saving...") : (lang === "ar" ? "حفظ العرض" : "Save offer")}</Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default AdminStoreSettings;
