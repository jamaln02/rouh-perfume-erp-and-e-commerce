import { useCallback, useEffect, useMemo, useState } from "react";
import { ArrowRight, FlaskConical, Pencil, Plus, RotateCcw, Trash2, Upload, X } from "lucide-react";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent } from "@/components/ui/card";
import { toast } from "sonner";
import { withAuthHeaders } from "@/lib/auth";

interface Variant {
  id: string;
  size_label: string;
  volume_ml: number | null;
  bottle_shape?: string | null;
  image_url?: string | null;
  image_is_reference?: boolean;
  selling_price_default: number | null;
  is_active: boolean;
  notes?: string | null;
  active_recipe?: { id: string } | null;
}

interface Material {
  id: number;
  code?: string | null;
  name: string;
  name_ar?: string | null;
  material_category: string;
  base_unit: string;
}

interface RecipeItem {
  id: number;
  material_id: number;
  expected_qty: number;
  unit: string;
  consumption_rule_type?: string;
  is_optional?: boolean;
  allow_manual_override?: boolean;
  sort_order?: number;
  notes?: string | null;
  material?: Material | null;
}

interface Recipe {
  id: string;
  version: number;
  is_active: boolean;
  items: RecipeItem[];
}

interface Props {
  open: boolean;
  product: any | null;
  apiBaseUrl: string;
  lang: "ar" | "en";
  onOpenChange: (v: boolean) => void;
}

const shapeOptions = [
  ["", "عادي / Standard"],
  ["tester", "Tester"],
  ["crystal", "Crystal"],
  ["pen", "Pen"],
  ["laser", "Laser"],
  ["zara", "Zara"],
  ["gold_crystal", "Gold Crystal"],
  ["yum_yum", "Yum Yum"],
];

const categoryLabel = (category: string, ar: boolean) => {
  if (!ar) {
    return category === "perfume_oil" ? "Oil" : category === "alcohol" ? "Alcohol" : "Packaging";
  }
  return category === "perfume_oil" ? "نوع الزيت" : category === "alcohol" ? "كحول" : "تغليف";
};

const initialVariantForm = { size_label: "", volume_ml: "", bottle_shape: "", image_url: "", image_is_reference: false, selling_price_default: "", notes: "" };
const initialRecipeItemForm = { material_id: "", expected_qty: "0", unit: "", consumption_rule_type: "fixed", notes: "" };

export default function ProductVariantManager({ open, product, apiBaseUrl, lang, onOpenChange }: Props) {
  const ar = lang === "ar";

  const [variants, setVariants] = useState<Variant[]>([]);
  const [materials, setMaterials] = useState<Material[]>([]);
  const [loading, setLoading] = useState(false);
  const [recipeLoading, setRecipeLoading] = useState(false);
  const [recipe, setRecipe] = useState<Recipe | null>(null);
  const [currentVariant, setCurrentVariant] = useState<Variant | null>(null);
  const [recipeOpen, setRecipeOpen] = useState(false);
  const [variantOpen, setVariantOpen] = useState(false);
  const [editingVariant, setEditingVariant] = useState<Variant | null>(null);
  const [variantForm, setVariantForm] = useState(initialVariantForm);
  const [pendingImageFile, setPendingImageFile] = useState<File | null>(null);
  const [pendingImagePreview, setPendingImagePreview] = useState<string | null>(null);
  const [imageUploading, setImageUploading] = useState(false);
  const [recipeItemOpen, setRecipeItemOpen] = useState(false);
  const [editingRecipeItem, setEditingRecipeItem] = useState<RecipeItem | null>(null);
  const [recipeItemForm, setRecipeItemForm] = useState(initialRecipeItemForm);

  const activeVariants = useMemo(() => variants.filter((variant) => variant.is_active), [variants]);

  const resolveVariantImageUrl = (imageUrl?: string | null) => {
    if (!imageUrl) return "";
    if (/^https?:\/\//i.test(imageUrl)) return imageUrl;
    return `${apiBaseUrl}${imageUrl.startsWith("/") ? imageUrl : `/${imageUrl}`}`;
  };


  const load = useCallback(async () => {
    if (!product) return;
    setLoading(true);
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/products/${product.id}/variants`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) {
        toast.error(data?.message || (ar ? "فشل تحميل الأحجام" : "Failed to load sizes"));
        return;
      }
      setVariants(Array.isArray(data?.variants) ? data.variants : []);
    } catch {
      toast.error(ar ? "تعذر الوصول إلى الخادم." : "Cannot reach the server.");
    } finally {
      setLoading(false);
    }
  }, [apiBaseUrl, product, ar]);

  const loadMaterials = useCallback(async () => {
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/recipes/materials`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) {
        toast.error(data?.message || (ar ? "فشل تحميل المواد" : "Failed to load materials"));
        return;
      }
      setMaterials(Array.isArray(data?.materials) ? data.materials : []);
    } catch {
      toast.error(ar ? "تعذر تحميل المواد." : "Cannot load materials.");
    }
  }, [apiBaseUrl, ar]);

  useEffect(() => {
    setRecipe(null);
    setCurrentVariant(null);
    setRecipeOpen(false);
    setVariantOpen(false);
    setRecipeItemOpen(false);
    setEditingVariant(null);
    setEditingRecipeItem(null);
    setVariantForm(initialVariantForm);
    setPendingImageFile(null);
    setPendingImagePreview(null);
    setRecipeItemForm(initialRecipeItemForm);
    if (open) {
      void load();
      void loadMaterials();
    }
  }, [open, product?.id, load, loadMaterials]);

  const openAddVariant = () => {
    setEditingVariant(null);
    setVariantForm(initialVariantForm);
    setPendingImageFile(null);
    setPendingImagePreview(null);
    setVariantOpen(true);
  };

  const openVariantEditor = (variant: Variant) => {
    setEditingVariant(variant);
    setVariantForm({
      size_label: variant.size_label,
      volume_ml: variant.volume_ml == null ? "" : String(variant.volume_ml),
      bottle_shape: variant.bottle_shape || "",
      image_url: variant.image_url || "",
      image_is_reference: Boolean(variant.image_is_reference),
      selling_price_default: variant.selling_price_default == null ? "" : String(variant.selling_price_default),
      notes: variant.notes || "",
    });
    setPendingImageFile(null);
    setPendingImagePreview(null);
    setVariantOpen(true);
  };

  const handleVariantImageSelected = (file: File | null) => {
    if (!file) return;
    if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) {
      toast.error(ar ? "اختر صورة JPG أو PNG أو WebP." : "Choose a JPG, PNG or WebP image.");
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      toast.error(ar ? "حجم الصورة يجب ألا يتجاوز 5 ميغابايت." : "Image size must not exceed 5 MB.");
      return;
    }
    setPendingImageFile(file);
    setPendingImagePreview(URL.createObjectURL(file));
  };

  const sizeKey = (label: string) => label.trim().toLowerCase().replace(/\s+/g, "");

  const uploadPendingVariantImage = async (sizeLabel: string) => {
    if (!pendingImageFile) return;
    const key = sizeKey(sizeLabel);
    if (!key) throw new Error(ar ? "أدخل اسم الحجم أولاً." : "Enter a size label first.");
    const formData = new FormData();
    formData.append("size_label", sizeLabel);
    formData.append("image", pendingImageFile);
    formData.append("image_is_reference", variantForm.image_is_reference ? "1" : "0");
    const response = await fetch(`${apiBaseUrl}/api/admin/size-media/${encodeURIComponent(key)}/image`, {
      method: "POST",
      headers: withAuthHeaders({ Accept: "application/json" }),
      body: formData,
    });
    const data = await response.json().catch(() => null);
    if (!response.ok) throw new Error(data?.message || data?.errors?.image?.[0] || (ar ? "فشل رفع صورة الحجم" : "Failed to upload size image"));
  };

  const deleteGlobalSizeImage = async (sizeLabel: string) => {
    const key = sizeKey(sizeLabel);
    try {
      setImageUploading(true);
      const response = await fetch(`${apiBaseUrl}/api/admin/size-media/${encodeURIComponent(key)}/image`, {
        method: "DELETE",
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) throw new Error(data?.message || (ar ? "فشل حذف صورة الحجم" : "Failed to delete size image"));
      toast.success(ar ? "تم حذف الصورة الموحدة لهذا الحجم" : "The global size image was removed");
      await load();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
    } finally {
      setImageUploading(false);
    }
  };

  const saveVariant = async () => {
    const label = variantForm.size_label.trim();
    const price = Number(variantForm.selling_price_default);
    const volume = variantForm.volume_ml.trim() === "" ? null : Number(variantForm.volume_ml);

    if (!label) {
      toast.error(ar ? "اكتب اسم الحجم أولاً." : "Enter a size label first.");
      return;
    }
    if (!Number.isFinite(price) || price < 0) {
      toast.error(ar ? "أدخل سعراً صحيحاً." : "Enter a valid price.");
      return;
    }
    if (volume !== null && (!Number.isFinite(volume) || volume < 0)) {
      toast.error(ar ? "أدخل حجماً بالمللي بشكل صحيح." : "Enter a valid volume in ml.");
      return;
    }

    const isEditing = Boolean(editingVariant);
    const url = isEditing
      ? `${apiBaseUrl}/api/admin/variants/${editingVariant!.id}`
      : `${apiBaseUrl}/api/admin/products/${product.id}/variants`;
    const body = {
      size_label: label,
      volume_ml: volume,
      bottle_shape: variantForm.bottle_shape || null,
      selling_price_default: price,
      notes: variantForm.notes || null,
      ...(isEditing ? { is_active: editingVariant!.is_active } : {}),
    };

    try {
      const response = await fetch(url, {
        method: isEditing ? "PUT" : "POST",
        headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json" }),
        body: JSON.stringify(body),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) {
        toast.error(data?.message || (ar ? "فشل حفظ الحجم" : "Failed to save size"));
        return;
      }
      const savedVariantId = String(data?.variant?.id || editingVariant?.id || "");
      if (pendingImageFile && savedVariantId) {
        setImageUploading(true);
        try {
          await uploadPendingVariantImage(label);
        } finally {
          setImageUploading(false);
        }
      }
      toast.success(ar ? (isEditing ? "تم تحديث الحجم" : "تمت إضافة الحجم") : isEditing ? "Size updated" : "Size added");
      setVariantOpen(false);
      setEditingVariant(null);
      setVariantForm(initialVariantForm);
      setPendingImageFile(null);
      if (pendingImagePreview) URL.revokeObjectURL(pendingImagePreview);
      setPendingImagePreview(null);
      await load();
    } catch {
      toast.error(ar ? "تعذر الوصول إلى الخادم." : "Cannot reach the server.");
    }
  };

  const toggleVariant = async (variant: Variant, active: boolean) => {
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/variants/${variant.id}`, {
        method: "PUT",
        headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json" }),
        body: JSON.stringify({ is_active: active }),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) {
        toast.error(data?.message || (ar ? "فشل تحديث حالة الحجم" : "Failed to update size status"));
        return;
      }
      toast.success(ar ? (active ? "تمت إعادة تفعيل الحجم" : "تم إلغاء تفعيل الحجم") : active ? "Size restored" : "Size disabled");
      await load();
    } catch {
      toast.error(ar ? "تعذر الوصول إلى الخادم." : "Cannot reach the server.");
    }
  };

  const openRecipe = async (variant: Variant) => {
    setCurrentVariant(variant);
    setRecipeOpen(true);
    setRecipe(null);
    setRecipeLoading(true);
    try {
      let recipeId = variant.active_recipe?.id;
      if (!recipeId) {
        const createResponse = await fetch(`${apiBaseUrl}/api/admin/recipes`, {
          method: "POST",
          headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json" }),
          body: JSON.stringify({ product_variant_id: variant.id, is_active: true }),
        });
        const createData = await createResponse.json().catch(() => null);
        if (!createResponse.ok) {
          toast.error(createData?.message || (ar ? "فشل إنشاء الوصفة" : "Failed to create recipe"));
          setRecipeOpen(false);
          return;
        }
        setRecipe(createData.recipe);
        return;
      }

      const response = await fetch(`${apiBaseUrl}/api/admin/recipes/${recipeId}`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) {
        toast.error(data?.message || (ar ? "فشل تحميل الوصفة" : "Failed to load recipe"));
        setRecipeOpen(false);
        return;
      }
      setRecipe(data.recipe);
    } catch {
      toast.error(ar ? "تعذر الوصول إلى الخادم." : "Cannot reach the server.");
      setRecipeOpen(false);
    } finally {
      setRecipeLoading(false);
    }
  };

  const closeRecipe = () => {
    setRecipeOpen(false);
    setCurrentVariant(null);
    setRecipe(null);
    setRecipeItemOpen(false);
  };

  const openAddRecipeItem = () => {
    setEditingRecipeItem(null);
    setRecipeItemForm(initialRecipeItemForm);
    setRecipeItemOpen(true);
  };

  const openRecipeItemEditor = (item: RecipeItem) => {
    setEditingRecipeItem(item);
    setRecipeItemForm({
      material_id: String(item.material_id),
      expected_qty: String(item.expected_qty ?? 0),
      unit: item.unit,
      consumption_rule_type: item.consumption_rule_type || "fixed",
      notes: item.notes || "",
    });
    setRecipeItemOpen(true);
  };

  const materialForForm = materials.find((material) => String(material.id) === recipeItemForm.material_id) || null;

  const saveRecipeItem = async () => {
    if (!recipe) return;
    const materialId = Number(recipeItemForm.material_id);
    const qty = Number(recipeItemForm.expected_qty);
    if (!Number.isInteger(materialId) || materialId <= 0) {
      toast.error(ar ? "اختر مادة." : "Choose a material.");
      return;
    }
    if (!Number.isFinite(qty) || qty < 0) {
      toast.error(ar ? "أدخل كمية صحيحة." : "Enter a valid quantity.");
      return;
    }

    const isEditing = Boolean(editingRecipeItem);
    const url = isEditing
      ? `${apiBaseUrl}/api/admin/recipes/${recipe.id}/items/${editingRecipeItem!.id}`
      : `${apiBaseUrl}/api/admin/recipes/${recipe.id}/items`;
    const body = {
      material_id: materialId,
      expected_qty: qty,
      unit: recipeItemForm.unit || materialForForm?.base_unit || "pcs",
      consumption_rule_type: recipeItemForm.consumption_rule_type,
      rule_config: {},
      is_optional: false,
      allow_manual_override: true,
      sort_order: isEditing ? (editingRecipeItem!.sort_order ?? editingRecipeItem!.id) : ((recipe.items?.length || 0) + 1),
      notes: recipeItemForm.notes || null,
    };

    try {
      const response = await fetch(url, {
        method: isEditing ? "PUT" : "POST",
        headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json" }),
        body: JSON.stringify(body),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) {
        toast.error(data?.message || (ar ? "فشل حفظ مادة الوصفة" : "Failed to save recipe material"));
        return;
      }
      toast.success(ar ? (isEditing ? "تم تحديث مادة الوصفة" : "تمت إضافة مادة الوصفة") : isEditing ? "Recipe material updated" : "Recipe material added");
      setRecipeItemOpen(false);
      setEditingRecipeItem(null);
      setRecipeItemForm(initialRecipeItemForm);
      const refreshed = await fetch(`${apiBaseUrl}/api/admin/recipes/${recipe.id}`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const refreshedData = await refreshed.json().catch(() => null);
      if (refreshed.ok) setRecipe(refreshedData.recipe);
    } catch {
      toast.error(ar ? "تعذر الوصول إلى الخادم." : "Cannot reach the server.");
    }
  };

  const deleteRecipeItem = async (item: RecipeItem) => {
    if (!recipe) return;
    if (!window.confirm(ar ? "حذف هذه المادة من الوصفة؟" : "Remove this material from the recipe?")) return;
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/recipes/${recipe.id}/items/${item.id}`, {
        method: "DELETE",
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) {
        toast.error(data?.message || (ar ? "فشل حذف مادة الوصفة" : "Failed to delete recipe material"));
        return;
      }
      toast.success(ar ? "تم حذف مادة الوصفة" : "Recipe material removed");
      setRecipe((previous) => previous ? { ...previous, items: previous.items.filter((entry) => entry.id !== item.id) } : previous);
    } catch {
      toast.error(ar ? "تعذر الوصول إلى الخادم." : "Cannot reach the server.");
    }
  };

  const sortedItems = (recipe?.items || []).slice().sort((a, b) => (a.sort_order ?? a.id) - (b.sort_order ?? b.id));

  return (
    <>
      <Dialog open={open} onOpenChange={(value) => { if (!value) closeRecipe(); onOpenChange(value); }}>
        <DialogContent className="w-[95vw] max-w-3xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>
              {recipeOpen
                ? ar
                  ? `وصفة ${product?.name_ar || product?.name || "المنتج"} — ${currentVariant?.size_label || ""}`
                  : `Recipe ${product?.name || "Product"} — ${currentVariant?.size_label || ""}`
                : ar
                  ? `الأحجام والوصفات — ${product?.name_ar || product?.name || "المنتج"}`
                  : `Sizes & Recipes — ${product?.name || "Product"}`}
            </DialogTitle>
          </DialogHeader>

          {!recipeOpen ? (
            <div className="space-y-4">
              <div className="flex items-center justify-between gap-3 rounded-lg border bg-muted/20 p-3">
                <div>
                  <div className="font-medium">{ar ? "أحجام هذا المنتج" : "Product sizes"}</div>
                  <div className="text-sm text-muted-foreground">
                    {ar ? "أضف أي حجم، عدّل الاسم والسعر أو أوقفه. لا يوجد قالب ثابت مفروض على المنتجات." : "Add any size, edit its label and price, or disable it. Products are not locked to a fixed size list."}
                  </div>
                </div>
                <Button onClick={openAddVariant} className="bg-gradient-gold shrink-0">
                  <Plus className="h-4 w-4 me-1" /> {ar ? "إضافة حجم" : "Add size"}
                </Button>
              </div>

              {loading ? (
                <div className="py-8 text-center text-muted-foreground">{ar ? "جارٍ التحميل…" : "Loading…"}</div>
              ) : variants.length === 0 ? (
                <Card><CardContent className="p-6 text-center text-sm text-muted-foreground">{ar ? "لا توجد أحجام لهذا المنتج بعد." : "This product has no sizes yet."}</CardContent></Card>
              ) : (
                <div className="space-y-2">
                  {variants.map((variant) => (
                    <Card key={variant.id} className={variant.is_active ? "" : "opacity-60"}>
                      <CardContent className="p-3">
                        <div className="flex flex-col lg:flex-row lg:items-center gap-3">
                          <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2 flex-wrap">
                              <div className="font-medium">{variant.size_label}</div>
                              {!variant.is_active && <span className="text-xs rounded-full border px-2 py-0.5">{ar ? "غير فعال" : "Inactive"}</span>}
                            </div>
                            <div className="text-sm text-muted-foreground">
                              {Number(variant.selling_price_default || 0).toLocaleString()} SYP
                              {variant.volume_ml != null ? ` · ${Number(variant.volume_ml).toLocaleString()} ml` : ""}
                              {variant.bottle_shape ? ` · ${variant.bottle_shape}` : ""}
                            </div>
                          </div>
                          <div className="flex items-center gap-1 flex-wrap">
                            <Button size="sm" variant="ghost" onClick={() => openVariantEditor(variant)} title={ar ? "تعديل الحجم والسعر" : "Edit size and price"}>
                              <Pencil className="h-4 w-4 me-1" /> {ar ? "تعديل" : "Edit"}
                            </Button>
                            <Button size="sm" variant="outline" disabled={!variant.is_active} onClick={() => void openRecipe(variant)}>
                              <FlaskConical className="h-4 w-4 me-1" /> {ar ? "الوصفة" : "Recipe"}
                            </Button>
                            {variant.is_active ? (
                              <Button size="sm" variant="ghost" className="text-destructive" onClick={() => void toggleVariant(variant, false)}>
                                <Trash2 className="h-4 w-4 me-1" /> {ar ? "إلغاء" : "Disable"}
                              </Button>
                            ) : (
                              <Button size="sm" variant="ghost" onClick={() => void toggleVariant(variant, true)}>
                                <RotateCcw className="h-4 w-4 me-1" /> {ar ? "إعادة" : "Restore"}
                              </Button>
                            )}
                          </div>
                        </div>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              )}

              <div className="text-xs text-muted-foreground">
                {ar ? `الأحجام الفعالة حالياً: ${activeVariants.length}` : `Active sizes: ${activeVariants.length}`}
              </div>
            </div>
          ) : (
            <div className="space-y-4">
              <Button variant="outline" onClick={closeRecipe}>
                <ArrowRight className="h-4 w-4 me-2" /> {ar ? "رجوع للأحجام" : "Back to sizes"}
              </Button>

              {recipeLoading ? (
                <div className="py-8 text-center text-muted-foreground">{ar ? "جارٍ تحميل الوصفة…" : "Loading recipe…"}</div>
              ) : (
                <Card>
                  <CardContent className="p-4 space-y-4">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <div className="font-medium">{ar ? "مواد الوصفة" : "Recipe materials"}</div>
                        <p className="text-sm text-muted-foreground">
                          {ar ? "تُنشأ الوصفة افتراضياً بخمسة مواد أساسية، ويمكنك تعديل الكميات أو تبديل المادة أو إضافة وحذف أي مادة حسب حاجتك." : "New recipes start with five common materials, but every line remains editable: change quantity, replace materials, add or remove lines."}
                        </p>
                      </div>
                      <Button onClick={openAddRecipeItem} className="bg-gradient-gold shrink-0">
                        <Plus className="h-4 w-4 me-1" /> {ar ? "إضافة مادة" : "Add material"}
                      </Button>
                    </div>

                    {sortedItems.length === 0 ? (
                      <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        {ar ? "الوصفة فارغة. أضف المواد التي تحتاجها." : "The recipe is empty. Add the materials you need."}
                      </div>
                    ) : (
                      <div className="space-y-2">
                        {sortedItems.map((item, index) => (
                          <div key={item.id} className="rounded-lg border p-3">
                            <div className="flex items-start gap-3">
                              <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium">{index + 1}</div>
                              <div className="min-w-0 flex-1">
                                <div className="font-medium truncate">
                                  {ar ? item.material?.name_ar || item.material?.name || "مادة" : item.material?.name || "Material"}
                                </div>
                                <div className="text-xs text-muted-foreground mt-0.5">
                                  {item.material ? categoryLabel(item.material.material_category, ar) : ""} · {item.unit}
                                </div>
                                {item.notes && <div className="text-xs text-muted-foreground mt-1">{item.notes}</div>}
                              </div>
                              <div className="text-sm whitespace-nowrap">{Number(item.expected_qty || 0).toLocaleString()} {item.unit}</div>
                              <div className="flex items-center gap-1 shrink-0">
                                <Button size="icon" variant="ghost" onClick={() => openRecipeItemEditor(item)} title={ar ? "تعديل" : "Edit"}><Pencil className="h-4 w-4" /></Button>
                                <Button size="icon" variant="ghost" className="text-destructive" onClick={() => void deleteRecipeItem(item)} title={ar ? "حذف" : "Delete"}><Trash2 className="h-4 w-4" /></Button>
                              </div>
                            </div>
                          </div>
                        ))}
                      </div>
                    )}
                  </CardContent>
                </Card>
              )}
            </div>
          )}
        </DialogContent>
      </Dialog>

      <Dialog open={variantOpen} onOpenChange={setVariantOpen}>
        <DialogContent className="w-[95vw] max-w-xl max-h-[90vh] flex flex-col overflow-hidden">
          <DialogHeader className="shrink-0">
            <DialogTitle>{ar ? (editingVariant ? "تعديل الحجم" : "إضافة حجم") : editingVariant ? "Edit size" : "Add size"}</DialogTitle>
          </DialogHeader>
          <div className="min-h-0 flex-1 overflow-y-auto pe-1 pb-2">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div className="md:col-span-2">
              <Label>{ar ? "اسم الحجم" : "Size label"}</Label>
              <Input value={variantForm.size_label} onChange={(e) => setVariantForm((previous) => ({ ...previous, size_label: e.target.value }))} placeholder="50ml / 5ml pen / 75ml ..." autoFocus />
            </div>
            <div>
              <Label>{ar ? "الحجم بالمل" : "Volume (ml)"}</Label>
              <Input inputMode="decimal" value={variantForm.volume_ml} onChange={(e) => setVariantForm((previous) => ({ ...previous, volume_ml: e.target.value.replace(/[^0-9.]/g, "").replace(/(\..*)\./g, "$1") }))} placeholder={ar ? "يستنتج تلقائياً من الاسم" : "Optional"} />
            </div>
            <div>
              <Label>{ar ? "شكل الزجاجة" : "Bottle shape"}</Label>
              <select value={variantForm.bottle_shape} onChange={(e) => setVariantForm((previous) => ({ ...previous, bottle_shape: e.target.value }))} className="mt-1 h-10 w-full rounded-lg border bg-background px-3 text-sm">
                {shapeOptions.map(([value, label]) => <option key={value} value={value}>{ar ? (value ? value : "عادي") : label}</option>)}
              </select>
            </div>
            <div className="md:col-span-2 space-y-3">
              <div className="flex items-center justify-between gap-2"><Label>{ar ? "الصورة الموحدة لهذا الحجم" : "Global image for this size"}</Label><span className="text-[11px] text-muted-foreground">{ar ? "تُطبق على جميع المنتجات" : "Applies to all products"}</span></div>
              {(pendingImagePreview || variantForm.image_url) && (
                <div className="relative overflow-hidden rounded-xl border bg-muted/20">
                  <img
                    src={pendingImagePreview || resolveVariantImageUrl(variantForm.image_url)}
                    alt={ar ? "معاينة صورة الحجم" : "Variant image preview"}
                    className="w-full aspect-video object-contain bg-background"
                  />
                  <div className="absolute top-2 end-2 flex gap-2">
                    {pendingImageFile ? (
                      <Button type="button" size="icon" variant="secondary" onClick={() => { URL.revokeObjectURL(pendingImagePreview || ""); setPendingImageFile(null); setPendingImagePreview(null); }} title={ar ? "إلغاء الصورة الجديدة" : "Discard new image"}>
                        <X className="h-4 w-4" />
                      </Button>
                    ) : editingVariant?.image_url ? (
                      <Button type="button" size="icon" variant="destructive" disabled={imageUploading} onClick={() => void deleteGlobalSizeImage(variantForm.size_label)} title={ar ? "حذف الصورة الموحدة" : "Delete global image"}>
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    ) : null}
                  </div>
                </div>
              )}
              <label className="flex min-h-12 cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-border bg-muted/20 px-4 text-sm font-medium hover:border-primary hover:bg-primary/5 transition-colors">
                <Upload className="h-4 w-4" />
                <span>{ar ? "اختيار صورة من الكمبيوتر" : "Choose image from computer"}</span>
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  className="sr-only"
                  onChange={(e) => handleVariantImageSelected(e.target.files?.[0] || null)}
                />
              </label>
              <p className="text-xs text-muted-foreground">
                {ar ? "JPG أو PNG أو WebP، وبحد أقصى 5 ميغابايت. هذه الصورة مشتركة بين جميع المنتجات التي تستخدم هذا الحجم." : "JPG, PNG or WebP, up to 5 MB. This image is shared by every product using this size."}
              </p>
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={variantForm.image_is_reference} onChange={(e) => setVariantForm((previous) => ({ ...previous, image_is_reference: e.target.checked }))} />
                {ar ? "صورة توضيحية" : "Illustrative image"}
              </label>
            </div>
            <div className="md:col-span-2">
              <Label>{ar ? "سعر البيع" : "Selling price"}</Label>
              <Input inputMode="decimal" value={variantForm.selling_price_default} onChange={(e) => setVariantForm((previous) => ({ ...previous, selling_price_default: e.target.value.replace(/[^0-9.]/g, "").replace(/(\..*)\./g, "$1") }))} placeholder="6500" />
            </div>
            <div className="md:col-span-2">
              <Label>{ar ? "ملاحظات" : "Notes"}</Label>
              <Input value={variantForm.notes} onChange={(e) => setVariantForm((previous) => ({ ...previous, notes: e.target.value }))} />
            </div>
          </div>
          </div>
          <DialogFooter className="shrink-0 border-t bg-background pt-3 sticky bottom-0">
            <Button variant="outline" onClick={() => setVariantOpen(false)}>{ar ? "إلغاء" : "Cancel"}</Button>
            <Button onClick={() => void saveVariant()}>{ar ? "حفظ" : "Save"}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={recipeItemOpen} onOpenChange={setRecipeItemOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{ar ? (editingRecipeItem ? "تعديل مادة الوصفة" : "إضافة مادة للوصفة") : editingRecipeItem ? "Edit recipe material" : "Add recipe material"}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div>
              <Label>{ar ? "المادة" : "Material"}</Label>
              <select
                value={recipeItemForm.material_id}
                onChange={(e) => {
                  const selected = materials.find((material) => String(material.id) === e.target.value);
                  setRecipeItemForm((previous) => ({ ...previous, material_id: e.target.value, unit: selected?.base_unit || previous.unit }));
                }}
                className="mt-1 h-10 w-full rounded-lg border bg-background px-3 text-sm"
              >
                <option value="">{ar ? "اختر مادة…" : "Choose a material…"}</option>
                {materials.map((material) => (
                  <option key={material.id} value={material.id}>
                    {ar ? material.name_ar || material.name : material.name} · {material.base_unit}
                  </option>
                ))}
              </select>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label>{ar ? "الكمية" : "Quantity"}</Label>
                <Input inputMode="decimal" value={recipeItemForm.expected_qty} onChange={(e) => setRecipeItemForm((previous) => ({ ...previous, expected_qty: e.target.value.replace(/[^0-9.]/g, "").replace(/(\..*)\./g, "$1") }))} />
              </div>
              <div>
                <Label>{ar ? "الوحدة" : "Unit"}</Label>
                <Input value={recipeItemForm.unit} onChange={(e) => setRecipeItemForm((previous) => ({ ...previous, unit: e.target.value }))} placeholder={materialForForm?.base_unit || "pcs"} />
              </div>
            </div>
            <div>
              <Label>{ar ? "قاعدة الاستهلاك" : "Consumption rule"}</Label>
              <select value={recipeItemForm.consumption_rule_type} onChange={(e) => setRecipeItemForm((previous) => ({ ...previous, consumption_rule_type: e.target.value }))} className="mt-1 h-10 w-full rounded-lg border bg-background px-3 text-sm">
                <option value="fixed">fixed</option>
                <option value="per_bottle">per_bottle</option>
                <option value="percentage">percentage</option>
                <option value="ratio">ratio</option>
                <option value="packaging_rule">packaging_rule</option>
              </select>
            </div>
            <div>
              <Label>{ar ? "ملاحظات" : "Notes"}</Label>
              <Input value={recipeItemForm.notes} onChange={(e) => setRecipeItemForm((previous) => ({ ...previous, notes: e.target.value }))} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setRecipeItemOpen(false)}>{ar ? "إلغاء" : "Cancel"}</Button>
            <Button onClick={() => void saveRecipeItem()}>{ar ? "حفظ" : "Save"}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
