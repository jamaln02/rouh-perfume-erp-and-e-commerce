import { useCallback, useEffect, useState } from "react";
import { useLanguage } from "@/hooks/useLanguage";
import { useAuth } from "@/hooks/useAuth";
import { withAuthHeaders } from "@/lib/auth";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Switch } from "@/components/ui/switch";
import { toast } from "sonner";
import { Plus, Pencil, Trash2, Search, FlaskConical, ImageIcon, Globe2 } from "lucide-react";
import ProductVariantManager from "@/components/admin/ProductVariantManager";
import ProductSizeMediaManager from "@/components/admin/ProductSizeMediaManager";

/**
 * Safely parse a notes field that may be a JSON-encoded string (e.g. '["Bergamot","Lemon"]')
 * or a comma-separated string. Returns a clean string[].
 */
const safeParseNotes = (raw: string): string[] => {
  const trimmed = raw.trim();
  if (trimmed === "") return [];
  try {
    const parsed = JSON.parse(trimmed);
    if (Array.isArray(parsed)) return parsed.map(String).map((n) => n.trim()).filter((n) => n.length > 0);
  } catch {
    // Not JSON — split by comma
  }
  return trimmed.split(",").map((n) => n.trim()).filter((n) => n.length > 0);
};

/** Convert a string[] of notes to a comma-separated string for display in a text input. */
const notesToInputString = (notes: string[] | string | null | undefined): string => {
  if (Array.isArray(notes)) return notes.join(", ");
  if (typeof notes === "string") {
    const parsed = safeParseNotes(notes);
    return parsed.join(", ");
  }
  return "";
};

interface Product {
  id: string;
  name: string;
  name_ar: string;
  description: string;
  description_ar: string;
  price: number;
  image_url: string;
  category_id: string | null;
  fragrance: string;
  top_notes?: string[] | string | null;
  heart_notes?: string[] | string | null;
  base_notes?: string[] | string | null;
  fragrance_family?: string | null;
  sizes: string[];
  size_prices: Record<string, number>;
  featured: boolean;
  is_new: boolean;
  best_seller: boolean;
  stock: number;
  estimated_costs?: Array<{
    size_label: string;
    volume_ml: number | null;
    selling_price: number | null;
    estimated_cost: number;
    gross_profit: number | null;
    margin_percentage: number | null;
    has_recipe: boolean;
    has_missing_costs: boolean;
    missing_materials?: string[];
    recipe_id?: string;
    recipe_version?: number;
    breakdown?: Array<{
      material_id: number;
      material_name: string;
      material_name_ar?: string | null;
      category?: string | null;
      quantity: number;
      unit: string;
      unit_cost: number;
      total_cost: number;
    }>;
  }>;
}

interface Category {
  id: string;
  name: string;
  name_ar: string;
}

const emptyProduct: Omit<Product, "id"> = {
  name: "", name_ar: "", description: "", description_ar: "",
  price: 0, image_url: "", category_id: null, fragrance: "oriental",
  top_notes: [], heart_notes: [], base_notes: [], fragrance_family: "",
  sizes: [], size_prices: {}, featured: false, is_new: false, best_seller: false, stock: 0,
};



const AdminProducts = () => {
  const { user } = useAuth();
  const canViewCosts = user?.role === "admin" || user?.role === "manager";
  const { lang } = useLanguage();
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "");
  const [products, setProducts] = useState<Product[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Product | null>(null);
  const [form, setForm] = useState(emptyProduct);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [variantProduct, setVariantProduct] = useState<Product | null>(null);
  const [sizeMediaOpen, setSizeMediaOpen] = useState(false);
  const [globalSizeOpen, setGlobalSizeOpen] = useState(false);
  const [globalSizeSaving, setGlobalSizeSaving] = useState(false);
  const [globalPackaging, setGlobalPackaging] = useState<any[]>([]);
  const [globalSizeForm, setGlobalSizeForm] = useState({
    size_label: "",
    volume_ml: "",
    selling_price_default: "",
    default_material_id: "",
    bottle_shape: "",
    reactivate_existing: true,
  });

  const parseSizes = (sizes: unknown): string[] => {
    if (Array.isArray(sizes)) return sizes.map(String).map((value) => value.trim()).filter(Boolean);
    if (typeof sizes === "string") {
      try {
        const parsed = JSON.parse(sizes);
        if (Array.isArray(parsed)) return parsed.map(String).map((value) => value.trim()).filter(Boolean);
      } catch {
        return sizes.split(",").map((value) => value.trim()).filter(Boolean);
      }
    }
    return [];
  };

  const parseSizePrices = (sizePrices: unknown, sizes: string[], basePrice: number): Record<string, number> => {
    const parsed: Record<string, number> = {};
    if (sizePrices && typeof sizePrices === "object" && !Array.isArray(sizePrices)) {
      for (const [key, value] of Object.entries(sizePrices as Record<string, unknown>)) {
        const num = Number(value);
        if (!Number.isNaN(num)) parsed[key] = num;
      }
    } else if (typeof sizePrices === "string") {
      try {
        const decoded = JSON.parse(sizePrices) as Record<string, unknown>;
        for (const [key, value] of Object.entries(decoded || {})) {
          const num = Number(value);
          if (!Number.isNaN(num)) parsed[key] = num;
        }
      } catch {
        // ignore invalid JSON
      }
    }

    for (const size of sizes) {
      if (parsed[size] === undefined) parsed[size] = basePrice;
    }

    return parsed;
  };



  const loadGlobalPackaging = useCallback(async () => {
    try {
      const response = await window.fetch(`${apiBaseUrl}/api/admin/inventory/materials/category/packaging`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data?.message || "Failed to load packaging materials");
      setGlobalPackaging(Array.isArray(data) ? data.filter((m:any) => m?.is_active !== false) : []);
    } catch {
      setGlobalPackaging([]);
    }
  }, [apiBaseUrl]);

  const openGlobalSizeDialog = () => {
    setGlobalSizeForm({ size_label: "", volume_ml: "", selling_price_default: "", default_material_id: "", bottle_shape: "", reactivate_existing: true });
    setGlobalSizeOpen(true);
    void loadGlobalPackaging();
  };

  const addGlobalSize = async () => {
    const sizeLabel = globalSizeForm.size_label.trim();
    if (!sizeLabel) {
      toast.error(lang === "ar" ? "أدخل اسم الحجم" : "Enter a size label");
      return;
    }
    setGlobalSizeSaving(true);
    try {
      const payload:any = {
        size_label: sizeLabel,
        reactivate_existing: globalSizeForm.reactivate_existing,
      };
      if (globalSizeForm.volume_ml !== "") payload.volume_ml = Number(globalSizeForm.volume_ml);
      if (globalSizeForm.selling_price_default !== "") payload.selling_price_default = Number(globalSizeForm.selling_price_default);
      if (globalSizeForm.default_material_id) payload.default_material_id = Number(globalSizeForm.default_material_id);
      if (globalSizeForm.bottle_shape) payload.bottle_shape = globalSizeForm.bottle_shape;

      const response = await window.fetch(`${apiBaseUrl}/api/admin/products/global-size`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json", ...withAuthHeaders() },
        body: JSON.stringify(payload),
      });
      const data = await response.json();
      if (!response.ok || !data?.ok) throw new Error(data?.message || "Failed to add global size");
      toast.success(lang === "ar"
        ? `تمت إضافة ${sizeLabel} — جديد: ${data.created}، أُعيد تفعيل: ${data.reactivated}، موجود مسبقًا: ${data.skipped}`
        : `${sizeLabel} added — new: ${data.created}, reactivated: ${data.reactivated}, existing: ${data.skipped}`);
      setGlobalSizeOpen(false);
      await fetchData();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
    } finally {
      setGlobalSizeSaving(false);
    }
  };

  const fetchData = useCallback(async () => {
    try {
      const [p, c] = await Promise.all([
        window.fetch(`${apiBaseUrl}/api/admin/products`, { headers: withAuthHeaders({ Accept: "application/json" }) }),
        window.fetch(`${apiBaseUrl}/api/admin/categories`, { headers: withAuthHeaders({ Accept: "application/json" }) }),
      ]);

      const productsPayload = await p.json();
      const categoriesPayload = await c.json();

      const normalizedProducts = ((productsPayload?.products || []) as Product[]).map((product) => {
        const sizes = parseSizes(product.sizes);
        const price = Number(product.price || 0);
        return {
          ...product,
          sizes,
          size_prices: parseSizePrices(product.size_prices, sizes, price),
          price,
          stock: Number(product.stock || 0),
        };
      });

      setProducts(normalizedProducts);
      setCategories((categoriesPayload?.categories || []) as Category[]);
    } catch {
      // Network/server error — keep current state, mark not loading.
      setProducts([]);
    } finally {
      setLoading(false);
    }
  }, [apiBaseUrl]);

  useEffect(() => { void fetchData(); }, [fetchData]);

  const handleSave = async () => {
    try {
      // Convert comma-separated note strings to arrays for the API
      const topNotesArr = Array.isArray(form.top_notes) ? form.top_notes : safeParseNotes(String(form.top_notes || ""));
      const heartNotesArr = Array.isArray(form.heart_notes) ? form.heart_notes : safeParseNotes(String(form.heart_notes || ""));
      const baseNotesArr = Array.isArray(form.base_notes) ? form.base_notes : safeParseNotes(String(form.base_notes || ""));

      // Selling prices are entered per size. Product cost is calculated from
      // the active recipe and current material costs; it is not a user-entered price.
      const { stock: _ignoredStock, ...productFields } = form;
      const payload = {
        ...productFields,
        top_notes: topNotesArr,
        heart_notes: heartNotesArr,
        base_notes: baseNotesArr,
        fragrance_family: form.fragrance_family || null,
        price: Number(form.price || 0),
      };

      if (editing) {
        const response = await window.fetch(`${apiBaseUrl}/api/admin/products/${editing.id}`, {
          method: "PATCH",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            ...withAuthHeaders(),
          },
          body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (!response.ok || !data?.ok) throw new Error(data?.message || "Failed to update product");
        toast.success(lang === "ar" ? "تم تحديث المنتج" : "Product updated");
      } else {
        const response = await window.fetch(`${apiBaseUrl}/api/admin/products`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            ...withAuthHeaders(),
          },
          body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (!response.ok || !data?.ok) throw new Error(data?.message || "Failed to create product");
        toast.success(lang === "ar" ? "تم إضافة المنتج" : "Product added");
      }
      setOpen(false);
      setEditing(null);
      setForm(emptyProduct);
      fetchData();
    } catch (error: unknown) {
      if (error instanceof Error) toast.error(error.message);
      else toast.error(String(error));
    }
  };

  const handleDelete = async (id: string) => {
    if (!confirm(lang === "ar" ? "هل أنت متأكد من الحذف؟" : "Are you sure?")) return;
    const response = await window.fetch(`${apiBaseUrl}/api/admin/products/${id}`, {
      method: "DELETE",
      headers: withAuthHeaders({ Accept: "application/json" }),
    });
    const data = await response.json();
    if (!response.ok || !data?.ok) toast.error(data?.message || "Delete failed");
    else { toast.success(lang === "ar" ? "تم الحذف" : "Deleted"); fetchData(); }
  };

  const openEdit = (p: Product) => {
    setEditing(p);
    setForm({
      name: p.name,
      name_ar: p.name_ar,
      description: p.description || "",
      description_ar: p.description_ar || "",
      price: p.price,
      image_url: p.image_url || "",
      category_id: p.category_id,
      fragrance: p.fragrance || "oriental",
      // Fragrance notes — handle both array and JSON-string formats from the API
      top_notes: Array.isArray(p.top_notes) ? p.top_notes : (typeof p.top_notes === "string" ? safeParseNotes(p.top_notes) : []),
      heart_notes: Array.isArray(p.heart_notes) ? p.heart_notes : (typeof p.heart_notes === "string" ? safeParseNotes(p.heart_notes) : []),
      base_notes: Array.isArray(p.base_notes) ? p.base_notes : (typeof p.base_notes === "string" ? safeParseNotes(p.base_notes) : []),
      fragrance_family: p.fragrance_family || "",
      sizes: parseSizes(p.sizes),
      size_prices: parseSizePrices(p.size_prices, parseSizes(p.sizes), p.price),
      featured: p.featured,
      is_new: p.is_new,
      best_seller: p.best_seller,
      stock: 0,
    });
    setOpen(true);
  };

  const t = {
    title: lang === "ar" ? "إدارة المنتجات" : "Manage Products",
    add: lang === "ar" ? "إضافة منتج" : "Add Product",
    edit: lang === "ar" ? "تعديل المنتج" : "Edit Product",
    save: lang === "ar" ? "حفظ" : "Save",
    name: lang === "ar" ? "الاسم (EN)" : "Name (EN)",
    nameAr: lang === "ar" ? "الاسم (AR)" : "Name (AR)",
    desc: lang === "ar" ? "الوصف (EN)" : "Description (EN)",
    descAr: lang === "ar" ? "الوصف (AR)" : "Description (AR)",
    price: lang === "ar" ? "السعر" : "Price",
    estimatedCost: lang === "ar" ? "التكلفة التقديرية" : "Estimated Cost",
    priceHint: lang === "ar" ? "سعر البيع منفصل عن التكلفة ويُحدد لكل حجم" : "Selling price is separate from cost and set per size",
    image: lang === "ar" ? "رابط الصورة" : "Image URL",
    category: lang === "ar" ? "التصنيف" : "Category",
    fragrance: lang === "ar" ? "نوع العطر" : "Fragrance",
    sizes: lang === "ar" ? "الأحجام" : "Sizes",
    sizePrice: lang === "ar" ? "سعر" : "Price",
    featured: lang === "ar" ? "مميز" : "Featured",
    isNew: lang === "ar" ? "جديد" : "New",
    bestSeller: lang === "ar" ? "الأكثر مبيعاً" : "Best Seller",
  };

  if (loading) return <div className="flex justify-center p-8"><div className="animate-spin h-8 w-8 border-2 border-primary border-t-transparent rounded-full" /></div>;

  const filtered = products.filter((p) => {
    if (!search) return true;
    const q = search.toLowerCase();
    return p.name.toLowerCase().includes(q) || p.name_ar.toLowerCase().includes(q);
  });

  return (
    <div className="p-6 space-y-6 space-x-2">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6 space-x-2">
        <div className="flex items-center gap-2"><h1 className="text-2xl font-display font-bold">{t.title}</h1></div>
        <div className="flex items-center gap-2 flex-wrap">
          <Button variant="outline" onClick={openGlobalSizeDialog}><Globe2 className="h-4 w-4 me-2" />{lang === "ar" ? "إضافة حجم لكل المنتجات" : "Add size to all products"}</Button>
          <Button variant="outline" onClick={() => setSizeMediaOpen(true)}><ImageIcon className="h-4 w-4 me-2" />{lang === "ar" ? "صور الأحجام" : "Size images"}</Button>
          <Dialog open={globalSizeOpen} onOpenChange={setGlobalSizeOpen}>
            <DialogContent className="w-[95vw] max-w-xl">
              <DialogHeader>
                <DialogTitle>{lang === "ar" ? "إضافة حجم لجميع المنتجات" : "Add size to all products"}</DialogTitle>
              </DialogHeader>
              <div className="space-y-4">
                <div className="rounded-lg border bg-muted/30 p-3 text-sm">
                  {lang === "ar" ? "سيتم إنشاء هذا الحجم لكل المنتجات الحالية. الأسعار الحالية للأحجام الموجودة لن تتغير." : "This size will be created for every current product. Existing variant prices will not be changed."}
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                  <div><Label>{lang === "ar" ? "اسم الحجم" : "Size label"}</Label><Input placeholder="مثال: 12ml" value={globalSizeForm.size_label} onChange={(e) => setGlobalSizeForm({ ...globalSizeForm, size_label: e.target.value })} /></div>
                  <div><Label>{lang === "ar" ? "الحجم بالمل" : "Volume (ml)"}</Label><Input type="text" inputMode="decimal" placeholder="12" value={globalSizeForm.volume_ml} onChange={(e) => setGlobalSizeForm({ ...globalSizeForm, volume_ml: e.target.value })} /></div>
                  <div><Label>{lang === "ar" ? "سعر البيع الافتراضي" : "Default selling price"}</Label><Input type="text" inputMode="decimal" placeholder={lang === "ar" ? "مثال: 50000" : "e.g. 50000"} value={globalSizeForm.selling_price_default} onChange={(e) => setGlobalSizeForm({ ...globalSizeForm, selling_price_default: e.target.value })} /></div>
                  <div><Label>{lang === "ar" ? "شكل العبوة" : "Bottle shape"}</Label><Input placeholder="default" value={globalSizeForm.bottle_shape} onChange={(e) => setGlobalSizeForm({ ...globalSizeForm, bottle_shape: e.target.value })} /></div>
                  <div className="sm:col-span-2"><Label>{lang === "ar" ? "مادة تغليف افتراضية (اختياري)" : "Default packaging material (optional)"}</Label><Select value={globalSizeForm.default_material_id || "none"} onValueChange={(v) => setGlobalSizeForm({ ...globalSizeForm, default_material_id: v === "none" ? "" : v })}><SelectTrigger><SelectValue placeholder={lang === "ar" ? "بدون مادة افتراضية" : "No default material"} /></SelectTrigger><SelectContent><SelectItem value="none">{lang === "ar" ? "بدون مادة افتراضية" : "No default material"}</SelectItem>{globalPackaging.map((m:any) => <SelectItem key={m.id} value={String(m.id)}>{m.name_ar || m.name} · {m.base_unit}</SelectItem>)}</SelectContent></Select></div>
                </div>
                <div className="flex items-center gap-3 rounded-lg border p-3">
                  <Switch checked={globalSizeForm.reactivate_existing} onCheckedChange={(checked) => setGlobalSizeForm({ ...globalSizeForm, reactivate_existing: checked })} />
                  <div><div className="text-sm font-medium">{lang === "ar" ? "إعادة تفعيل الحجم إذا كان موجودًا لكنه غير نشط" : "Reactivate an existing inactive size"}</div><div className="text-xs text-muted-foreground">{lang === "ar" ? "لن يتم استبدال بيانات الحجم النشط الموجود." : "Active existing variants are never overwritten."}</div></div>
                </div>
                <div className="flex justify-end gap-2"><Button variant="outline" onClick={() => setGlobalSizeOpen(false)}>{lang === "ar" ? "إلغاء" : "Cancel"}</Button><Button onClick={() => void addGlobalSize()} disabled={globalSizeSaving}>{globalSizeSaving ? (lang === "ar" ? "جارٍ التطبيق..." : "Applying...") : (lang === "ar" ? "إضافة للجميع" : "Add to all")}</Button></div>
              </div>
            </DialogContent>
          </Dialog>

          <Dialog open={open} onOpenChange={(v) => { setOpen(v); if (!v) { setEditing(null); setForm(emptyProduct); } }}>
            <DialogTrigger asChild>
              <Button className="bg-gradient-gold"><Plus className="h-4 w-4 me-2" />{t.add}</Button>
            </DialogTrigger>
          <DialogContent className="w-[95vw] max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle>{editing ? t.edit : t.add}</DialogTitle>
            </DialogHeader>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div><Label>{t.name}</Label><Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></div>
              <div><Label>{t.nameAr}</Label><Input value={form.name_ar} onChange={(e) => setForm({ ...form, name_ar: e.target.value })} /></div>
              <div className="md:col-span-2"><Label>{t.desc}</Label><Input value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} /></div>
              <div className="md:col-span-2"><Label>{t.descAr}</Label><Input value={form.description_ar} onChange={(e) => setForm({ ...form, description_ar: e.target.value })} /></div>
              <div className="md:col-span-2 rounded-lg border bg-muted/20 p-3 text-sm text-muted-foreground">
                {lang === "ar" ? "هذا سعر البيع وليس تكلفة التصنيع. تكلفة التصنيع تُحسب تلقائياً من الوصفة الفعالة وتكلفة المواد في المخزون." : "This is the selling price, not manufacturing cost. Manufacturing cost is calculated from the active recipe and inventory material cost."}
              </div>
              <div>
                <Label>{lang === "ar" ? "سعر البيع الأساسي" : "Base Selling Price"}</Label>
                <Input
                  type="text"
                  inputMode="decimal"
                  value={String(form.price || "")}
                  onChange={(e) => setForm({ ...form, price: Number(e.target.value) || 0 })}
                  placeholder="6500"
                />
              </div>
              <div className="md:col-span-2 rounded-lg border bg-muted/20 p-3 text-sm">
                <div className="font-medium">{t.sizes}</div>
                <div className="text-muted-foreground mt-1">
                  {lang === "ar"
                    ? "تُدار أحجام المنتج وأسعارها بشكل مستقل من زر «الأحجام والوصفات» بجانب المنتج، ويمكنك إضافة أي حجم أو إلغاؤه متى شئت."
                    : "Manage this product's sizes and prices from the “Variants & Recipes” button. You can add or disable sizes at any time."}
                </div>
              </div>
              {canViewCosts && (<div className="md:col-span-2 rounded-xl border p-3 bg-muted/10 space-y-2">
                <div className="font-medium">{t.estimatedCost}</div>
                {(editing?.estimated_costs || []).length === 0 ? (
                  <div className="text-sm text-muted-foreground">
                    {lang === "ar" ? "لا توجد وصفة فعالة لهذا المنتج بعد؛ أضف وصفة من «الأحجام والوصفات» ليُحسب التكلفة تلقائياً." : "No active recipe yet. Add a recipe from “Variants & Recipes” to calculate cost automatically."}
                  </div>
                ) : (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                    {(editing?.estimated_costs || []).map((c) => (
                      <div key={c.size_label} className="rounded-lg border bg-background p-3 space-y-1">
                        <div className="flex items-center justify-between gap-2">
                          <span className="font-medium">{c.size_label}</span>
                          <span>{Number(c.estimated_cost || 0).toLocaleString()} SYP</span>
                        </div>
                        <div className="text-xs text-muted-foreground">
                          {c.has_missing_costs
                            ? (lang === "ar" ? "تنبيه: توجد مادة بدون تكلفة مسجلة" : "Warning: one or more materials have no cost")
                            : c.has_recipe
                              ? (lang === "ar" ? "محسوبة من الوصفة النشطة ومتوسط تكلفة المخزون" : "Calculated from active recipe and current weighted-average material cost")
                              : (lang === "ar" ? "بدون وصفة" : "No recipe")}
                        </div>
                        {c.breakdown && c.breakdown.length > 0 && (
                          <div className="pt-2 border-t mt-2 space-y-1.5">
                            <div className="text-xs font-medium">{lang === "ar" ? "تفاصيل التكلفة" : "Cost breakdown"}</div>
                            {c.breakdown.map((line) => (
                              <div key={`${c.size_label}-${line.material_id}`} className="flex items-center justify-between gap-3 text-xs text-muted-foreground">
                                <span className="truncate">{lang === "ar" ? (line.material_name_ar || line.material_name) : line.material_name}</span>
                                <span className="shrink-0">{Number(line.quantity).toLocaleString()} {line.unit} × {Number(line.unit_cost).toLocaleString()} = {Number(line.total_cost).toLocaleString()} SYP</span>
                              </div>
                            ))}
                          </div>
                        )}
                        {c.selling_price != null && (
                          <div className="text-xs">
                            {lang === "ar" ? "الربح الإجمالي" : "Gross profit"}: {Number(c.gross_profit || 0).toLocaleString()} SYP
                            {c.margin_percentage != null ? ` · ${Number(c.margin_percentage).toFixed(1)}%` : ""}
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                )}
              </div>)}

              <div className="md:col-span-2"><Label>{t.image}</Label><Input value={form.image_url} onChange={(e) => setForm({ ...form, image_url: e.target.value })} /></div>
              <div>
                <Label>{t.category}</Label>
                <Select value={form.category_id || ""} onValueChange={(v) => setForm({ ...form, category_id: v || null })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {categories.map((c) => <SelectItem key={c.id} value={c.id}>{lang === "ar" ? c.name_ar : c.name}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label>{t.fragrance}</Label>
                <Select value={form.fragrance} onValueChange={(v) => setForm({ ...form, fragrance: v })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {["oud", "floral", "woody", "fresh", "oriental"].map((f) => <SelectItem key={f} value={f}>{f}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              {/* Fragrance Family — more specific classification (e.g. "Citrus Aromatic", "Oriental Woody") */}
              <div>
                <Label>{lang === "ar" ? "العائلة العطرية (تفصيلية)" : "Fragrance Family (detailed)"}</Label>
                <Input
                  value={typeof form.fragrance_family === "string" ? form.fragrance_family : ""}
                  onChange={(e) => setForm({ ...form, fragrance_family: e.target.value })}
                  placeholder={lang === "ar" ? "مثال: Citrus Aromatic / Oriental Woody" : "e.g. Citrus Aromatic / Oriental Woody"}
                />
              </div>
              {/* Fragrance Notes — comma-separated inputs for top, heart, and base notes */}
              <div>
                <Label>{lang === "ar" ? "النوتات العلوية (افصل بفاصلة)" : "Top Notes (comma-separated)"}</Label>
                <Input
                  value={notesToInputString(form.top_notes)}
                  onChange={(e) => setForm({ ...form, top_notes: safeParseNotes(e.target.value) })}
                  placeholder={lang === "ar" ? "برغموت، ليمون، نعناع" : "Bergamot, Lemon, Mint"}
                />
              </div>
              <div>
                <Label>{lang === "ar" ? "النوتات الوسطى (افصل بفاصلة)" : "Heart Notes (comma-separated)"}</Label>
                <Input
                  value={notesToInputString(form.heart_notes)}
                  onChange={(e) => setForm({ ...form, heart_notes: safeParseNotes(e.target.value) })}
                  placeholder={lang === "ar" ? "ورد، ياسمين، لافندر" : "Rose, Jasmine, Lavender"}
                />
              </div>
              <div className="md:col-span-2">
                <Label>{lang === "ar" ? "النوتات القاعدية (افصل بفاصلة)" : "Base Notes (comma-separated)"}</Label>
                <Input
                  value={notesToInputString(form.base_notes)}
                  onChange={(e) => setForm({ ...form, base_notes: safeParseNotes(e.target.value) })}
                  placeholder={lang === "ar" ? "عود، مسك، عنبر، خشب الصندل" : "Oud, Musk, Amber, Sandalwood"}
                />
              </div>
              <div className="flex items-center gap-4">
                <Label>{t.featured}</Label><Switch checked={form.featured} onCheckedChange={(v) => setForm({ ...form, featured: v })} />
              </div>
              <div className="flex items-center gap-4">
                <Label>{t.isNew}</Label><Switch checked={form.is_new} onCheckedChange={(v) => setForm({ ...form, is_new: v })} />
              </div>
              <div className="flex items-center gap-4">
                <Label>{t.bestSeller}</Label><Switch checked={form.best_seller} onCheckedChange={(v) => setForm({ ...form, best_seller: v })} />
              </div>
            </div>
            <Button onClick={handleSave} className="w-full bg-gradient-gold mt-4">{t.save}</Button>
          </DialogContent>
          </Dialog>
        </div>
      </div>

      <div className="relative w-full sm:max-w-md mb-4">
        <Search className="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
        <Input
          placeholder={lang === "ar" ? "بحث عن منتج..." : "Search products..."}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="ps-9"
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-3 xl:hidden">
        {filtered.map((p) => (
          <div key={p.id} className="border rounded-lg p-4 bg-card">
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0">
                <p className="font-medium truncate">{lang === "ar" ? p.name_ar : p.name}</p>
                <p className="text-sm text-muted-foreground mt-1">{p.price.toLocaleString()} SYP</p>
              </div>
              <div className="flex items-center gap-1 shrink-0">
                <Button size="icon" variant="ghost" title={lang === "ar" ? "الأحجام والوصفات" : "Variants & recipes"} onClick={() => setVariantProduct(p)}><FlaskConical className="h-4 w-4" /></Button>
                  <Button size="icon" variant="ghost" onClick={() => openEdit(p)}><Pencil className="h-4 w-4" /></Button>
                <Button size="icon" variant="ghost" className="text-destructive" onClick={() => handleDelete(p.id)}><Trash2 className="h-4 w-4" /></Button>
              </div>
            </div>

            <div className="flex items-center justify-between mt-2 text-sm">
              <span className="text-muted-foreground">{t.featured}</span>
              <span>{p.featured ? "✓" : "—"}</span>
            </div>
          </div>
        ))}
        {filtered.length === 0 && (
          <div className="border rounded-lg p-8 text-center text-muted-foreground lg:col-span-2">
            {lang === "ar" ? "لا توجد منتجات" : "No products"}
          </div>
        )}
      </div>

      <div className="hidden xl:block border rounded-lg overflow-x-auto">
        <Table className="min-w-[640px]">
          <TableHeader>
            <TableRow>
              <TableHead>{t.name}</TableHead>
              <TableHead>{t.price}</TableHead>
              <TableHead>{t.featured}</TableHead>
              <TableHead></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {filtered.map((p) => (
              <TableRow key={p.id}>
                <TableCell className="font-medium">{lang === "ar" ? p.name_ar : p.name}</TableCell>
                <TableCell>{p.price.toLocaleString()} SYP</TableCell>
                <TableCell>{p.featured ? "✓" : "—"}</TableCell>
                <TableCell className="text-end space-x-1">
                  <Button size="icon" variant="ghost" title={lang === "ar" ? "الأحجام والوصفات" : "Variants & recipes"} onClick={() => setVariantProduct(p)}><FlaskConical className="h-4 w-4" /></Button>
                  <Button size="icon" variant="ghost" onClick={() => openEdit(p)}><Pencil className="h-4 w-4" /></Button>
                  <Button size="icon" variant="ghost" className="text-destructive" onClick={() => handleDelete(p.id)}><Trash2 className="h-4 w-4" /></Button>
                </TableCell>
              </TableRow>
            ))}
            {filtered.length === 0 && (
              <TableRow><TableCell colSpan={4} className="text-center text-muted-foreground py-8">{lang === "ar" ? "لا توجد منتجات" : "No products"}</TableCell></TableRow>
            )}
          </TableBody>
        </Table>
      </div>
      <ProductSizeMediaManager open={sizeMediaOpen} apiBaseUrl={apiBaseUrl} lang={lang} onOpenChange={setSizeMediaOpen} />
      <ProductVariantManager open={Boolean(variantProduct)} product={variantProduct} apiBaseUrl={apiBaseUrl} lang={lang} onOpenChange={(v) => { if (!v) { setVariantProduct(null); void fetchData(); } }} />
    </div>
  );
};

export default AdminProducts;
