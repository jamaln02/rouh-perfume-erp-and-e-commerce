import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertCircle,
  AlertTriangle,
  CheckCircle2,
  PackageCheck,
  Plus,
  RefreshCw,
  Search,
  ShoppingCart,
  X,
} from "lucide-react";
import { useLanguage } from "@/hooks/useLanguage";
import { useAuth } from "@/hooks/useAuth";
import { useNotification } from "@/hooks/useNotification";
import { useSearchParams } from "react-router-dom";
import { withAuthHeaders } from "@/lib/auth";
import { ErrorHandler } from "@/lib/errorHandler";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

/* ------------------------------------------------------------------ */
/*  Types                                                              */
/* ------------------------------------------------------------------ */

interface Material {
  id: number;
  name: string;
  name_ar?: string | null;
  code?: string | null;
  base_unit: string;
  material_category: string;
  current_stock?: number | string;
}

interface OrderItemVariant {
  id: string;
  size_label?: string | null;
  volume_ml?: number | null;
  product?: { id: string; name?: string | null; name_ar?: string | null } | null;
}

interface OrderItem {
  id: string;
  product_id?: string | null;
  product_variant_id?: string | null;
  product_name?: string | null;
  size?: string | null;
  quantity?: number;
  line_total?: number | string | null;
  unit_price?: number | string | null;
  variant?: OrderItemVariant | null;
  product?: { id: string; name?: string | null; name_ar?: string | null } | null;
}

/** One per-product bucket returned by the backend `per_product` field. */
interface PerProductBucket {
  order_item_id: string;
  product_id: string;
  product_name: string | null;
  size: string | null;
  quantity: number;
  variant: {
    id: string;
    size_label: string;
    volume_ml: number | null;
    bottle_shape?: string | null;
  };
  recipe: {
    id: string;
    version: number;
    is_active: boolean;
    notes: string | null;
  } | null;
  oil_percentage: number;
  alcohol_percentage: number;
  oil_ml: number | null;
  alcohol_ml: number | null;
  materials: Array<{
    material: Material;
    material_id: number;
    original_material_id: number;
    expected_qty: number;
    unit: string;
    allow_manual_override: boolean;
    recipe_item_id: number;
  }>;
}

interface RecipeResponse {
  order: {
    id: string;
    items?: OrderItem[];
    total?: number | string | null;
    customer_name?: string | null;
    customer_phone?: string | null;
  };
  material_requirements?: Array<{
    material_id: number;
    material: Material;
    expected_qty: number;
    unit: string;
    allow_manual_override?: boolean;
  }>;
  per_product?: PerProductBucket[];
  combined_recipe?: {
    mode: 'combined';
    lines: Array<{
      key: string;
      material: Material;
      material_id: number;
      unit: string;
      expected_qty: number;
      allow_manual_override: boolean;
      contributions: Array<{
        order_item_id: string;
        product_id: string;
        product_name: string | null;
        size: string | null;
        bottle_shape?: string | null;
        quantity: number;
        expected_qty: number;
        recipe_item_id: number;
        original_material_id: number;
      }>;
    }>;
    product_count: number;
    shared_line_count: number;
    product_lines: PerProductBucket[];
  };
}

interface RecipeLine {
  material_id: string;
  expected_qty: string;
  unit: string;
  consumption_rule_type?: string;
  allow_manual_override?: boolean;
}

/** A temporary material added to a specific product card for this order only.
 *  NOT persisted to the database.  Keyed by order_item_id. */
interface TempMaterial {
  tempId: string;
  material_id: number;
  material_name: string;
  expected_qty: string;
  unit: string;
}

/* ------------------------------------------------------------------ */
/*  Component                                                          */
/* ------------------------------------------------------------------ */

const OrderPreparation = () => {
  const { lang } = useLanguage();
  const ar = lang === "ar";
  const { user } = useAuth();
  const { notify } = useNotification();
  const isEmployee = user?.role === "employee";
  const canViewCosts = user?.role === "admin" || user?.role === "manager";
  const [searchParams] = useSearchParams();
  const requestedOrderId = searchParams.get("orderId");
  const api =
    String(import.meta.env.VITE_API_URL || "");

  const [orders, setOrders] = useState<any[]>([]);
  const [selected, setSelected] = useState<any>(null);
  const [recipe, setRecipe] = useState<RecipeResponse | null>(null);
  const [stock, setStock] = useState<any>(null);
  const [stockLoading, setStockLoading] = useState(false);
  const [materials, setMaterials] = useState<Material[]>([]);
  const [actual, setActual] = useState<Record<string, string>>({});
  const [overrides, setOverrides] = useState<Record<string, number>>({});
  const [overrideFor, setOverrideFor] = useState<any>(null);
  const [search, setSearch] = useState("");
  const [source, setSource] = useState("all");
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [notes, setNotes] = useState("");
  const [success, setSuccess] = useState<any>(null);

  // Persistent material dialog: every addition is saved to the exact recipe
  // belonging to the selected order item.
  const [materialAddFor, setMaterialAddFor] = useState<PerProductBucket | null>(null);
  const [materialForm, setMaterialForm] = useState({ material_id: "", expected_qty: "1", unit: "g" });
  const [addingMaterial, setAddingMaterial] = useState(false);

  // Recipe modal state
  const [recipeModalOpen, setRecipeModalOpen] = useState(false);
  const [recipeEditFor, setRecipeEditFor] = useState<PerProductBucket | null>(null);
  const [recipeVariantId, setRecipeVariantId] = useState<string>("");
  const [recipeOrderItemId, setRecipeOrderItemId] = useState<string>("");
  const [recipeOilPct, setRecipeOilPct] = useState("32");
  const [recipeChangeReason, setRecipeChangeReason] = useState("");
  const [recipeLines, setRecipeLines] = useState<RecipeLine[]>([
    { material_id: "", expected_qty: "", unit: "", consumption_rule_type: 'fixed', allow_manual_override: true },
  ]);
  const [creatingRecipe, setCreatingRecipe] = useState(false);

  /* --------------------------- Data loading --------------------------- */

  const loadOrders = useCallback(async () => {
    setLoading(true);
    try {
      const response = await fetch(`${api}/api/admin/orders`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data?.message || "Failed to load orders");
      const filteredOrders = (data.orders || []).filter(
        (o: any) =>
          o.consumption_status !== "consumed" &&
          o.status !== "cancelled" &&
          o.order_status !== "cancelled"
      );
      setOrders(filteredOrders);
      notify(`تم تحميل ${filteredOrders.length} طلبات`, "success");
    } catch (error) {
      await ErrorHandler.handleApiError(error, { endpoint: "/api/admin/orders" }, true);
    } finally {
      setLoading(false);
    }
  }, [api, notify]);

  const loadMaterials = useCallback(async () => {
    try {
      const response = await fetch(`${api}/api/admin/order-preparation/materials`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      if (response.ok) {
        setMaterials(Array.isArray(data) ? data : []);
      }
    } catch {
      // Silently handle — materials list is optional
    }
  }, [api]);

  useEffect(() => {
    void loadOrders();
    void loadMaterials();
  }, [loadOrders, loadMaterials]);

  useEffect(() => {
    if (!requestedOrderId || !orders.length) return;
    const target = orders.find((o: any) => String(o.id) === String(requestedOrderId));
    if (target && selected?.id !== target.id) void loadRecipe(target);
  }, [requestedOrderId, orders, selected]);

  const validateStock = useCallback(async (orderId: string, actualValues: Record<string, string>, overrideValues: Record<string, number>) => {
    setStockLoading(true);
    try {
      const response = await fetch(`${api}/api/admin/order-preparation/validate-stock/${orderId}`, {
        method: "POST",
        headers: withAuthHeaders({
          "Content-Type": "application/json",
          Accept: "application/json",
        }),
        body: JSON.stringify({
          actual_quantities: actualValues,
          material_overrides: overrideValues,
        }),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) throw new Error(data?.error || data?.message || "Stock validation failed");
      setStock(data);
      return data;
    } catch (error) {
      setStock({ can_proceed: false, issues: [{ message: error instanceof Error ? error.message : "Stock validation failed" }], warnings: [] });
      return null;
    } finally {
      setStockLoading(false);
    }
  }, [api]);

  const loadRecipe = async (o: any) => {
    setSelected(o);
    setRecipe(null);
    setStock(null);
    setOverrides({});
    setActual({});
    setNotes("");
    setMaterialAddFor(null);
    setMaterialForm({ material_id: "", expected_qty: "1", unit: "g" });
    try {
      const response = await fetch(`${api}/api/admin/order-preparation/recipe/${o.id}`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data?.error || data?.message || "No recipe data");
      setRecipe(data);
      const quantities: Record<string, string> = {};
      (data.material_requirements || []).forEach((x: any) => {
        quantities[String(x.material_id)] = String(x.expected_qty);
      });
      setActual(quantities);
      await validateStock(o.id, quantities, {});
    } catch (error) {
      setRecipe({ order: { id: o.id, items: o.items || [] }, material_requirements: [], per_product: [] });
      setStock({ can_proceed: false, issues: [{ message: error instanceof Error ? error.message : "Failed to load recipe" }], warnings: [] });
      notify(ar ? "تعذر تحميل وصفات الطلب" : "Unable to load order recipes", "error");
    }
  };

  /* --------------------------- Derived state --------------------------- */

  const consumptionSummary = useMemo(() => {
    const rows = recipe?.material_requirements || [];
    const actualRows = rows.map((row) => ({ ...row, actual: Number(actual[String(row.material_id)] ?? row.expected_qty) }));
    const byUnit: Record<string, { expected: number; actual: number }> = {};
    for (const row of actualRows) {
      const unit = String(row.unit || row.material?.base_unit || "pcs");
      if (!byUnit[unit]) byUnit[unit] = { expected: 0, actual: 0 };
      byUnit[unit].expected += Number(row.expected_qty || 0);
      byUnit[unit].actual += Number(row.actual || 0);
    }
    return Object.entries(byUnit).map(([unit, values]) => ({ unit, expected: values.expected, actual: values.actual }));
  }, [recipe, actual]);

  const hasNoRecipe =
    recipe &&
    (!recipe.material_requirements || recipe.material_requirements.length === 0) &&
    (!recipe.per_product || recipe.per_product.length === 0);

  const filteredOrders = useMemo(
    () =>
      orders.filter((o) => {
        const matchesSearch =
          (o.customer_name || "").toLowerCase().includes(search.toLowerCase()) ||
          String(o.id).includes(search);
        const matchesSource = source === "all" || o.source === source;
        return matchesSearch && matchesSource;
      }),
    [orders, search, source]
  );

  const orderVariants = useMemo(
    () =>
      (selected?.items || []).map((item: any) => ({
        id: item.product_variant_id || item.variant?.id || "",
        label: `${item.product_name || item.product?.name || ""}${
          item.size ? ` (${item.size})` : ""
        }`,
      })),
    [selected]
  );

  const alternatives = useMemo(
    () =>
      materials.filter(
        (m) => m.material_category === (overrideFor?.material?.material_category || "")
      ),
    [materials, overrideFor]
  );

  /* --------------------------- Recipe modal --------------------------- */

  const openRecipeModal = (orderItem?: any) => {
    setRecipeEditFor(null);
    const targetVariantId = orderItem?.product_variant_id || orderItem?.variant?.id || orderVariants[0]?.id || "";
    setRecipeOrderItemId(orderItem?.id ? String(orderItem.id) : "");
    setRecipeVariantId(targetVariantId);
    setRecipeLines([{ material_id: "", expected_qty: "", unit: "", consumption_rule_type: 'fixed', allow_manual_override: true }]);
    setRecipeOilPct("32");
    setRecipeChangeReason("");
    setRecipeModalOpen(true);
  };

  const addRecipeLine = () =>
    setRecipeLines((prev) => [...prev, { material_id: "", expected_qty: "", unit: "", consumption_rule_type: 'fixed', allow_manual_override: true }]);
  const removeRecipeLine = (index: number) =>
    setRecipeLines((prev) => prev.filter((_, i) => i !== index));
  const updateRecipeLine = (index: number, updates: Partial<RecipeLine>) =>
    setRecipeLines((prev) => prev.map((l, i) => (i === index ? { ...l, ...updates } : l)));

  const openRecipeEdit = (bucket: PerProductBucket) => {
    if (!bucket.recipe?.id) {
      openRecipeModal((recipe?.order?.items || selected?.items || []).find((x: any) => String(x.id) === String(bucket.order_item_id)));
      return;
    }
    setRecipeEditFor(bucket);
    setRecipeOrderItemId(String(bucket.order_item_id));
    setRecipeVariantId(String(bucket.variant.id));
    setRecipeOilPct(String(bucket.oil_percentage));
    setRecipeChangeReason("");
    setRecipeLines(
      bucket.materials.map((m) => ({
        material_id: String(m.material_id),
        expected_qty: String(m.expected_qty),
        unit: m.unit,
        consumption_rule_type: (m as any).consumption_rule_type || 'fixed',
        allow_manual_override: Boolean((m as any).allow_manual_override ?? true),
      }))
    );
    setRecipeModalOpen(true);
  };

  const submitRecipe = async () => {
    if (!recipeVariantId) {
      notify(ar ? "اختر صنفاً" : "Select a variant", "warning");
      return;
    }
    if (recipeLines.some((l) => !l.material_id || !l.expected_qty)) {
      notify(ar ? "أكمل جميع صفوف المواد" : "Complete all material rows", "warning");
      return;
    }
    const editing = !!recipeEditFor;
    if (editing && isEmployee && !recipeChangeReason.trim()) {
      notify(ar ? "سبب تعديل كمية الوصفة مطلوب للموظف" : "A reason is required for employee recipe quantity changes", "warning");
      return;
    }
    const endpoint = editing
      ? `${api}/api/admin/order-preparation/recipes/update-order-item`
      : `${api}/api/admin/order-preparation/recipes/inline`;
    setCreatingRecipe(true);
    try {
      const payload = editing
        ? {
            order_id: selected?.id,
            order_item_id: recipeEditFor?.order_item_id,
            recipe_id: recipeEditFor?.recipe?.id,
            oil_percentage: Number(recipeOilPct) || 32,
            items: recipeLines.map((l) => ({
              material_id: Number(l.material_id),
              expected_qty: Number(l.expected_qty),
              unit: l.unit,
              consumption_rule_type: l.consumption_rule_type || 'fixed',
              allow_manual_override: l.allow_manual_override ?? true,
            })),
            notes: editing ? recipeChangeReason.trim() : undefined,
          }
        : {
            product_variant_id: recipeVariantId,
            order_item_id: recipeOrderItemId || undefined,
            oil_percentage: Number(recipeOilPct) || 32,
            items: recipeLines.map((l) => ({
              material_id: Number(l.material_id),
              expected_qty: Number(l.expected_qty),
              unit: l.unit,
              consumption_rule_type: l.consumption_rule_type || 'fixed',
              allow_manual_override: l.allow_manual_override ?? true,
            })),
          };
      const response = await fetch(endpoint, {
        method: editing ? "POST" : "POST",
        headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json" }),
        body: JSON.stringify(payload),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data?.message || data?.error || "Failed to save recipe");
      notify(
        editing
          ? (ar ? "تم حفظ نسخة مستقلة للوصفة لهذا المنتج فقط" : "An independent recipe snapshot was saved for this product")
          : (ar ? "تم إنشاء الوصفة بنجاح" : "Recipe created successfully"),
        "success"
      );
      setRecipeModalOpen(false);
      setRecipeEditFor(null);
      if (selected) void loadRecipe(selected);
    } catch (error) {
      await ErrorHandler.handleApiError(error, { endpoint });
    } finally {
      setCreatingRecipe(false);
    }
  };

  /* ---------------------- Persistent recipe material ---------------------- */

  const openMaterialDialog = (bucket: PerProductBucket) => {
    if (!bucket.recipe?.id) {
      openRecipeModal((recipe?.order?.items || selected?.items || []).find((x: any) => String(x.id) === String(bucket.order_item_id)));
      return;
    }
    setMaterialAddFor(bucket);
    setMaterialForm({ material_id: "", expected_qty: "1", unit: "g" });
  };

  const addMaterialToRecipe = async () => {
    if (!materialAddFor?.recipe?.id || !selected?.id || !materialForm.material_id) return;
    const material = materials.find((m) => m.id === Number(materialForm.material_id));
    if (!material || !materialForm.expected_qty || Number(materialForm.expected_qty) < 0) {
      notify(ar ? "أدخل مادة وكمية صحيحة" : "Choose a material and valid quantity", "warning");
      return;
    }

    setAddingMaterial(true);
    try {
      const response = await fetch(`${api}/api/admin/order-preparation/recipes/add-material`, {
        method: "POST",
        headers: withAuthHeaders({
          "Content-Type": "application/json",
          Accept: "application/json",
        }),
        body: JSON.stringify({
          order_id: selected.id,
          order_item_id: materialAddFor.order_item_id,
          recipe_id: materialAddFor.recipe.id,
          material_id: Number(materialForm.material_id),
          expected_qty: Number(materialForm.expected_qty),
          unit: materialForm.unit || material.base_unit,
        }),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) throw new Error(data?.error || data?.message || "Failed to add material");
      setMaterialAddFor(null);
      setMaterialForm({ material_id: "", expected_qty: "1", unit: "g" });
      await loadRecipe(selected);
      notify(ar ? "تمت إضافة المادة إلى الوصفة وحفظها" : "Material added to the recipe", "success");
    } catch (error) {
      await ErrorHandler.handleApiError(error, { endpoint: "/api/admin/order-preparation/recipes/add-material" });
    } finally {
      setAddingMaterial(false);
    }
  };

  /* --------------------------- Prepare order --------------------------- */

  const prepare = async (allowWithoutRecipe = false) => {
    if (!selected || !recipe || stockLoading) return;
    if (!allowWithoutRecipe && (recipe.per_product || []).some((b: any) => !b.recipe)) {
      notify(ar ? "أنشئ وصفة لكل منتج قبل تجهيز الطلب" : "Create a recipe for every product before preparing the order", "warning");
      return;
    }

    setSaving(true);
    try {
      const validation = await validateStock(selected.id, actual, overrides);
      if (!validation?.can_proceed) {
        notify(ar ? "لا يمكن تجهيز الطلب. راجع المخزون والوصفات." : "Preparation is blocked. Review stock and recipes.", "error");
        return;
      }

      const response = await fetch(`${api}/api/admin/order-preparation/prepare-unified`, {
        method: "POST",
        headers: withAuthHeaders({
          "Content-Type": "application/json",
          Accept: "application/json",
        }),
        body: JSON.stringify({
          order_id: selected.id,
          actual_quantities: actual,
          material_overrides: overrides,
          notes,
        }),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) throw new Error(data?.error || data?.message || "Failed to prepare order");
      setSuccess(data);
      notify(ar ? "تم تجهيز الطلب بنجاح" : "Order prepared successfully", "success");
      setStock(null);
      void loadOrders();
    } catch (error) {
      await ErrorHandler.handleApiError(error, { endpoint: "/api/admin/order-preparation/prepare-unified" });
    } finally {
      setSaving(false);
    }
  };

  useEffect(() => {
    if (!selected?.id || !recipe || stockLoading) return;
    const timer = window.setTimeout(() => {
      void validateStock(selected.id, actual, overrides);
    }, 350);
    return () => window.clearTimeout(timer);
  }, [selected?.id, recipe?.per_product, actual, overrides, validateStock]);

  /* --------------------------- Render --------------------------- */

  return (
    <div className="min-h-screen bg-gradient-to-b from-background to-muted/20 p-4 md:p-6">
      <div className="max-w-7xl mx-auto space-y-6">
        <div>
          <h1 className="text-3xl font-bold mb-2">
            {ar ? "تجهيز الطلبات" : "Order Preparation"}
          </h1>
          <p className="text-muted-foreground">
            {ar ? "تحضير الطلبات للشحن" : "Prepare orders for shipping"}
          </p>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Orders List */}
          <Card className="lg:col-span-1">
            <CardHeader className="pb-3">
              <CardTitle className="flex items-center gap-2">
                <ShoppingCart className="h-5 w-5" />
                {ar ? "الطلبات" : "Orders"}
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="flex gap-2">
                <Input
                  placeholder={ar ? "البحث..." : "Search..."}
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  className="flex-1"
                />
                <button className="p-2 rounded-lg hover:bg-muted transition-colors">
                  <Search className="h-4 w-4" />
                </button>
              </div>

              <Select value={source} onValueChange={setSource}>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{ar ? "جميع المصادر" : "All sources"}</SelectItem>
                  <SelectItem value="website">{ar ? "الموقع الإلكتروني" : "Website"}</SelectItem>
                  <SelectItem value="phone">{ar ? "الهاتف" : "Phone"}</SelectItem>
                  <SelectItem value="whatsapp">{ar ? "واتس" : "WhatsApp"}</SelectItem>
                </SelectContent>
              </Select>

              {loading ? (
                <div className="space-y-2">
                  {[...Array(3)].map((_, i) => (
                    <div key={i} className="h-14 bg-muted animate-pulse rounded-lg" />
                  ))}
                </div>
              ) : (
                <div className="space-y-2 max-h-[70vh] overflow-y-auto">
                  {filteredOrders.length === 0 ? (
                    <p className="text-center text-muted-foreground py-8 text-sm">
                      {ar ? "لا توجد طلبات" : "No orders"}
                    </p>
                  ) : (
                    filteredOrders.map((order) => (
                      <button
                        key={order.id}
                        onClick={() => void loadRecipe(order)}
                        className={`w-full text-start p-3 rounded-lg border transition-all ${
                          selected?.id === order.id
                            ? "border-primary bg-primary/5"
                            : "border-border hover:border-primary/50"
                        }`}
                      >
                        <div className="flex items-center justify-between mb-1">
                          <span className="font-medium">#{order.id}</span>
                          <Badge variant={order.status === "pending" ? "default" : "outline"}>
                            {order.status}
                          </Badge>
                        </div>
                        <p className="text-xs text-muted-foreground truncate">
                          {order.customer_name || "Unknown"}
                        </p>
                      </button>
                    ))
                  )}
                </div>
              )}
            </CardContent>
          </Card>

          {/* Order Details and Recipe */}
          <Card className="lg:col-span-2">
            {!selected ? (
              <CardContent className="py-14 text-center">
                <PackageCheck className="h-10 w-10 mx-auto mb-3 text-muted-foreground/50" />
                <p className="text-muted-foreground">
                  {ar ? "اختر طلباً لعرض التفاصيل" : "Select an order to view details"}
                </p>
              </CardContent>
            ) : (
              <>
                <CardHeader className="pb-3 border-b">
                  <div className="flex items-center justify-between">
                    <div>
                      <CardTitle>#{selected.id}</CardTitle>
                      <CardDescription>
                        {selected.customer_name} • {selected.customer_phone}
                      </CardDescription>
                    </div>
                    <Badge>{selected.status}</Badge>
                  </div>
                </CardHeader>
                <CardContent className="space-y-4 pt-4">
                  {/* Order Items Table */}
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="border-b text-muted-foreground">
                          <th className="text-start p-2">{ar ? "المنتج" : "Product"}</th>
                          <th className="text-start p-2">{ar ? "الكمية" : "Qty"}</th>
                          <th className="text-start p-2">{ar ? "السعر" : "Price"}</th>
                        </tr>
                      </thead>
                      <tbody>
                        {(() => {
                          const items: OrderItem[] =
                            recipe?.order?.items && recipe.order.items.length > 0
                              ? recipe.order.items
                              : selected?.items || [];
                          return items.map((item) => {
                            const name = item.product_name || item.product?.name || "Unknown";
                            const qty = item.quantity || 1;
                            const price = Number(item.unit_price || 0);
                            return (
                              <tr key={item.id} className="border-b">
                                <td className="p-2">
                                  <div>
                                    <p className="font-medium">{name}</p>
                                    {item.size && (
                                      <p className="text-xs text-muted-foreground">{item.size}</p>
                                    )}
                                  </div>
                                </td>
                                <td className="p-2">{qty}</td>
                                <td className="p-2">{price.toLocaleString()} SYP</td>
                              </tr>
                            );
                          });
                        })()}
                      </tbody>
                      {!isEmployee &&
                        (() => {
                          const items: OrderItem[] =
                            recipe?.order?.items && recipe.order.items.length > 0
                              ? recipe.order.items
                              : selected?.items || [];
                          if (items.length === 0) return null;
                          const grandTotal = items.reduce((sum, item) => {
                            const qty = Number(item.quantity || 1);
                            const unitPrice = Number(item.unit_price || 0);
                            return sum + Number(item.line_total || unitPrice * qty || 0);
                          }, 0);
                          const orderTotal = Number(selected?.total || grandTotal || 0);
                          return (
                            <tfoot>
                              <tr className="border-t-2 font-bold">
                                <td colSpan={2} className="p-2 text-end">
                                  {ar ? "إجمالي الطلب" : "Order total"}
                                </td>
                                <td className="p-2">{orderTotal.toLocaleString()} SYP</td>
                              </tr>
                            </tfoot>
                          );
                        })()}
                    </table>
                  </div>

                  {/* Recipe Section */}
                  {!recipe ? (
                    <div className="flex items-center justify-center py-6 bg-muted rounded-lg">
                      <RefreshCw className="animate-spin h-5 w-5 mr-2" />
                      <span className="text-sm text-muted-foreground">
                        {ar ? "جاري تحميل الوصفة..." : "Loading recipe..."}
                      </span>
                    </div>
                  ) : hasNoRecipe ? (
                    <Card className="border-amber-300 bg-amber-50/40">
                      <CardContent className="py-10 text-center space-y-4">
                        <AlertTriangle className="mx-auto h-10 w-10 text-amber-500" />
                        <div>
                          <p className="font-semibold text-amber-800 dark:text-amber-300">
                            {ar ? "لا توجد وصفة نشطة لهذا الطلب" : "No active recipe for this order"}
                          </p>
                          <p className="text-sm text-muted-foreground mt-1">
                            {ar ? "يمكنك إنشاء وصفة جديدة مباشرة" : "You can create a new recipe inline"}
                          </p>
                        </div>
                        <div className="flex justify-center gap-2 flex-wrap">
                          <Button onClick={() => openRecipeModal(recipe?.order?.items?.find((x: any) => !x.recipe_id) || recipe?.order?.items?.[0])} className="bg-gradient-gold text-white">
                            <Plus className="h-4 w-4 me-2" />
                            {ar ? "إنشاء وصفة" : "Create recipe"}
                          </Button>
                          <Button variant="outline" onClick={() => void prepare(true)}>
                            {ar ? "تجاوز وتأكيد" : "Skip and confirm"}
                          </Button>
                        </div>
                      </CardContent>
                    </Card>
                  ) : (
                    <>
                      {stock && (
                        <Card
                          className={
                            stock.can_proceed
                              ? "border-emerald-200 bg-emerald-50/50"
                              : "border-destructive/30 bg-destructive/5"
                          }
                        >
                          <CardContent className="p-4">
                            {stock.can_proceed ? (
                              <div className="flex gap-2 text-emerald-700 font-medium">
                                <CheckCircle2 className="h-5 w-5" />
                                {ar ? "المخزون كافٍ" : "Stock is sufficient"}
                              </div>
                            ) : (
                              <div className="space-y-1 text-destructive">
                                <div className="flex gap-2 font-medium">
                                  <AlertCircle className="h-5 w-5" />
                                  {ar ? "لا يمكن المتابعة" : "Preparation blocked"}
                                </div>
                                {stock.issues?.map((issue: any, idx: number) => (
                                  <div key={idx} className="text-sm">{issue.message}</div>
                                ))}
                              </div>
                            )}
                            {stock.warnings?.map((warning: any, idx: number) => (
                              <div key={idx} className="text-sm text-amber-700 mt-1">
                                {warning.message}
                              </div>
                            ))}
                          </CardContent>
                        </Card>
                      )}

                      {consumptionSummary.length > 0 && (
                        <Card className="border-primary/20">
                          <CardHeader className="pb-3"><CardTitle className="text-base">{ar ? "ملخص الاستهلاك" : "Consumption summary"}</CardTitle></CardHeader>
                          <CardContent className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            {consumptionSummary.map((row) => (
                              <div key={row.unit} className="rounded-lg border bg-muted/20 p-3">
                                <div className="text-xs text-muted-foreground">{row.unit}</div>
                                <div className="font-semibold">{ar ? "متوقع" : "Expected"}: {row.expected.toFixed(4)}</div>
                                <div className="font-semibold">{ar ? "فعلي" : "Actual"}: {row.actual.toFixed(4)}</div>
                              </div>
                            ))}
                          </CardContent>
                        </Card>
                      )}

                      {/* ====================================================== */}
                      {/*  COMBINED PREPARATION RECIPE                          */}
                      {/*  Shared materials are merged into one line; only the  */}
                      {/*  differences stay separate through contribution rows. */}
                      {/* ====================================================== */}
                      {recipe.combined_recipe && (
                        <Card className="border-primary/30 shadow-sm">
                          <CardHeader>
                            <div className="flex items-start justify-between gap-3">
                              <div>
                                <CardTitle className="text-xl">
                                  {ar ? "وصفة التجهيز الموحّدة" : "Combined preparation recipe"}
                                </CardTitle>
                                <CardDescription>
                                  {ar ? "تفاصيل مواد التجهيز لهذا الطلب." : "Preparation materials for this order."}
                                </CardDescription>
                              </div>
                              <Badge variant="outline">
                                {recipe.combined_recipe.shared_line_count} {ar ? "مشترك" : "shared"}
                              </Badge>
                            </div>
                          </CardHeader>
                          <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                              <div className="rounded-lg border bg-muted/30 p-3">
                                <div className="text-xs text-muted-foreground">{ar ? "المنتجات" : "Products"}</div>
                                <div className="font-bold text-lg">{recipe.combined_recipe.product_count}</div>
                              </div>
                              <div className="rounded-lg border bg-muted/30 p-3">
                                <div className="text-xs text-muted-foreground">{ar ? "أسطر التجهيز" : "Preparation lines"}</div>
                                <div className="font-bold text-lg">{recipe.combined_recipe.lines.length}</div>
                              </div>
                            </div>

                            <div className="overflow-x-auto rounded-lg border">
                              <table className="w-full text-sm">
                                <thead className="bg-muted/40">
                                  <tr className="border-b text-muted-foreground">
                                    <th className="text-start p-3">{ar ? "مادة التجهيز" : "Preparation material"}</th>
                                    <th className="text-start p-3">{ar ? "الإجمالي" : "Total"}</th>
                                    <th className="text-start p-3">{ar ? "المطلوب لكل منتج" : "Product contribution"}</th>
                                    <th className="text-start p-3">{ar ? "الفعلي" : "Actual"}</th>
                                    <th className="text-start p-3">{ar ? "المتاح" : "Available"}</th>
                                    <th />
                                  </tr>
                                </thead>
                                <tbody>
                                  {recipe.combined_recipe.lines.map((line) => (
                                    <tr key={line.key} className="border-b last:border-b-0 align-top">
                                      <td className="p-3 font-medium">
                                        {ar ? line.material.name_ar || line.material.name : line.material.name}
                                        <div className="text-xs text-muted-foreground">{line.material.code || ""}</div>
                                      </td>
                                      <td className="p-3 font-semibold">{Number(line.expected_qty).toFixed(2)} {line.unit}</td>
                                      <td className="p-3 min-w-[220px]">
                                        <div className="space-y-1">
                                          {line.contributions.map((c) => (
                                            <div key={`${c.order_item_id}-${c.recipe_item_id}`} className="flex items-center justify-between gap-2 rounded-md bg-muted/40 px-2 py-1">
                                              <span className="truncate">{c.product_name || "—"}{c.size ? ` (${c.size})` : ""}{c.bottle_shape ? ` • ${c.bottle_shape}` : ""}</span>
                                              <span className="font-medium whitespace-nowrap">{Number(c.expected_qty).toFixed(2)} {line.unit}</span>
                                            </div>
                                          ))}
                                        </div>
                                      </td>
                                      <td className="p-3">
                                        <Input
                                          className="w-28"
                                          type="text"
                                          inputMode="decimal"
                                          value={actual[String(line.material_id)] ?? String(line.expected_qty)}
                                          onChange={(e) => setActual((prev) => ({ ...prev, [String(line.material_id)]: e.target.value.replace(/[^0-9.]/g, "").replace(/(\..*)\./g, "$1") }))}
                                        />
                                      </td>
                                      <td className="p-3 whitespace-nowrap">{line.material.current_stock} {line.material.base_unit}</td>
                                      <td className="p-3">
                                        {line.allow_manual_override && (
                                          <Button size="sm" variant="outline" onClick={() => setOverrideFor(line)}>
                                            {ar ? "استبدال" : "Override"}
                                          </Button>
                                        )}
                                      </td>
                                    </tr>
                                  ))}
                                </tbody>
                              </table>
                            </div>

                            <div className="rounded-lg border bg-muted/20 p-4 space-y-3">
                              <div className="flex items-center justify-between">
                                <div>
                                  <h3 className="font-semibold">{ar ? "تفاصيل المنتجات والوصفات" : "Product recipe details"}</h3>
                                  <p className="text-xs text-muted-foreground">{ar ? "كل منتج يحتفظ بوصفته المستقلة؛ التعديل ينشئ نسخة خاصة بهذا الطلب." : "Each product keeps its own recipe; editing creates a snapshot for this order line."}</p>
                                </div>
                              </div>
                              <div className="grid gap-2">
                                {(recipe.per_product || []).map((bucket) => (
                                  <div key={bucket.order_item_id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-background p-3">
                                    <div>
                                      <div className="font-medium">{bucket.product_name || "—"} {bucket.size ? `• ${bucket.size}` : ""}</div>
                                      <div className="text-xs text-muted-foreground">
                                        {ar ? "الكمية" : "Qty"}: {bucket.quantity} • {ar ? "الوصفة" : "Recipe"}: {bucket.recipe ? `v${bucket.recipe.version}` : (ar ? "غير موجودة" : "Missing")}
                                      </div>
                                    </div>
                                    <div className="flex gap-2">
                                      {!bucket.recipe ? (
                                        <Button size="sm" onClick={() => openRecipeModal((recipe.order.items || []).find((x: any) => String(x.id) === String(bucket.order_item_id)))}>
                                          <Plus className="h-4 w-4 me-1" />
                                          {ar ? "إنشاء وصفة" : "Create recipe"}
                                        </Button>
                                      ) : (
                                        <Button size="sm" variant="outline" onClick={() => openRecipeEdit(bucket)}>
                                          {ar ? "تعديل نسخة المنتج" : "Edit product snapshot"}
                                        </Button>
                                      )}
                                    </div>
                                  </div>
                                ))}
                              </div>
                            </div>
                          </CardContent>
                        </Card>
                      )}

                      <Card>
                        <CardContent className="p-4 space-y-3">
                          <Textarea
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            placeholder={ar ? "ملاحظات التجهيز..." : "Preparation notes..."}
                          />
                          <div className="flex justify-end gap-2">
                            <Button
                              variant="outline"
                              onClick={() => {
                                setSelected(null);
                                setRecipe(null);
                              }}
                            >
                              {ar ? "إلغاء" : "Cancel"}
                            </Button>
                            {!stock?.can_proceed && !stockLoading && stock?.issues?.length > 0 && (
                              <div className="text-xs text-red-600 max-w-xl text-start self-center">
                                {stock.issues.map((issue: any, index: number) => (
                                  <div key={`${issue.material_id ?? 'issue'}-${index}`}>• {issue.message}</div>
                                ))}
                              </div>
                            )}
                            <Button
                              onClick={() => void prepare()}
                              disabled={saving || stockLoading || !stock?.can_proceed || (recipe?.per_product || []).some((b: any) => !b.recipe)}
                              className="bg-gradient-gold text-white"
                            >
                              {saving && <RefreshCw className="animate-spin h-4 w-4 me-2" />}
                              {ar ? "تأكيد التجهيز" : "Confirm preparation"}
                            </Button>
                          </div>
                        </CardContent>
                      </Card>
                    </>
                  )}
                </CardContent>
              </>
            )}
          </Card>
        </div>
      </div>

      {/* Material override modal */}
      {overrideFor && (
        <div
          className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4"
          onClick={() => setOverrideFor(null)}
        >
          <div
            className="bg-background rounded-xl border shadow-xl w-full max-w-md p-5 space-y-4"
            onClick={(e) => e.stopPropagation()}
          >
            <h3 className="font-semibold">{ar ? "استبدال المادة" : "Override material"}</h3>
            <p className="text-sm text-muted-foreground">
              {ar
                ? overrideFor.material.name_ar || overrideFor.material.name
                : overrideFor.material.name}
            </p>
            <Select
              onValueChange={(value) => {
                setOverrides((prev) => ({
                  ...prev,
                  [String(overrideFor.material_id)]: Number(value),
                }));
                setActual((prev) => {
                  const updated = {
                    ...prev,
                    [String(Number(value))]:
                      prev[String(overrideFor.material_id)] ?? String(overrideFor.expected_qty),
                  };
                  delete updated[String(overrideFor.material_id)];
                  return updated;
                });
                setOverrideFor(null);
              }}
            >
              <SelectTrigger>
                <SelectValue placeholder={ar ? "اختر مادة بديلة" : "Choose alternative"} />
              </SelectTrigger>
              <SelectContent>
                {alternatives.map((mat) => (
                  <SelectItem key={mat.id} value={String(mat.id)}>
                    {ar ? mat.name_ar || mat.name : mat.name} — {mat.current_stock} {mat.base_unit}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Button variant="outline" onClick={() => setOverrideFor(null)}>
              {ar ? "إلغاء" : "Cancel"}
            </Button>
          </div>
        </div>
      )}

      {/* Recipe creation modal */}
      {recipeModalOpen && (
        <div
          className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4"
          onClick={() => { setRecipeModalOpen(false); setRecipeEditFor(null); }}
        >
          <div
            className="bg-background rounded-xl border shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 space-y-4"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="flex items-center justify-between">
              <h3 className="font-semibold text-lg">
                {recipeEditFor ? (ar ? "تعديل وصفة المنتج" : "Edit product recipe") : (ar ? "إنشاء وصفة جديدة" : "Create Recipe")}
              </h3>
              <button onClick={() => { setRecipeModalOpen(false); setRecipeEditFor(null); }} className="p-1 rounded hover:bg-muted">
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="grid sm:grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <label className="text-sm font-medium">
                  {ar ? "نوع المنتج" : "Product variant"}
                </label>
                {orderVariants.length > 0 ? (
                  <Select value={recipeVariantId} onValueChange={setRecipeVariantId} disabled={!!recipeOrderItemId}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {orderVariants.map((variant) => (
                        <SelectItem key={variant.id} value={variant.id}>
                          {variant.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                ) : (
                  <p className="text-sm text-muted-foreground border rounded-lg p-2">
                    {ar ? "لا توجد أنواع مرتبطة" : "No variants linked"}
                  </p>
                )}
              </div>
              <div className="space-y-1.5">
                <label className="text-sm font-medium">
                  {ar ? "نسبة الزيت (%)" : "Oil percentage (%)"}
                </label>
                <Input
                  type="text"
                  inputMode="decimal"
                  value={recipeOilPct}
                  onChange={(e) => setRecipeOilPct(e.target.value.replace(/[^0-9.]/g, ""))}
                  placeholder="32"
                />
              </div>
            </div>

            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <label className="text-sm font-medium">{ar ? "المواد" : "Materials"}</label>
                <Button size="sm" variant="outline" onClick={addRecipeLine}>
                  <Plus className="h-4 w-4 me-1" />
                  {ar ? "إضافة" : "Add"}
                </Button>
              </div>
              <div className="space-y-2 max-h-[40vh] overflow-y-auto">
                {recipeLines.map((line, index) => {
                  return (
                    <div
                      key={index}
                      className="grid grid-cols-1 sm:grid-cols-[1fr_120px_90px_40px] gap-2 items-center"
                    >
                      <Select
                        value={line.material_id}
                        onValueChange={(value) => {
                          const mat = materials.find((x) => String(x.id) === value);
                          updateRecipeLine(index, {
                            material_id: value,
                            unit: mat?.base_unit || line.unit,
                          });
                        }}
                      >
                        <SelectTrigger>
                          <SelectValue placeholder={ar ? "اختر مادة" : "Select material"} />
                        </SelectTrigger>
                        <SelectContent>
                          {materials.map((mat) => (
                            <SelectItem key={mat.id} value={String(mat.id)}>
                              {ar ? mat.name_ar || mat.name : mat.name} ({mat.base_unit})
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <Input
                        type="text"
                        inputMode="decimal"
                        value={line.expected_qty}
                        onChange={(e) =>
                          updateRecipeLine(index, {
                            expected_qty: e.target.value.replace(/[^0-9.]/g, ""),
                          })
                        }
                        placeholder={ar ? "الكمية" : "Qty"}
                      />
                      <Input
                        type="text"
                        value={line.unit}
                        onChange={(e) => updateRecipeLine(index, { unit: e.target.value })}
                        placeholder="unit"
                      />
                      <button
                        onClick={() => removeRecipeLine(index)}
                        className="p-2 rounded hover:bg-destructive/10 text-destructive"
                        aria-label="remove"
                      >
                        <X className="h-4 w-4" />
                      </button>
                    </div>
                  );
                })}
                {recipeLines.length === 0 && (
                  <p className="text-sm text-muted-foreground">
                    {ar ? "أضف مادة واحدة على الأقل" : "Add at least one material"}
                  </p>
                )}
              </div>
            </div>

            {recipeEditFor && isEmployee && (
              <div className="space-y-2 rounded-lg border border-amber-200 bg-amber-50/50 p-3">
                <Label>{ar ? "سبب تعديل كمية الوصفة (إلزامي)" : "Reason for recipe quantity change (required)"}</Label>
                <Textarea rows={3} value={recipeChangeReason} onChange={(e) => setRecipeChangeReason(e.target.value)} placeholder={ar ? "مثال: الزبون طلب تركيز أعلى" : "Example: customer requested a stronger concentration"} />
              </div>
            )}
            <div className="flex justify-end gap-2 pt-2 border-t">
              <Button variant="outline" onClick={() => setRecipeModalOpen(false)}>
                {ar ? "إلغاء" : "Cancel"}
              </Button>
              <Button
                onClick={() => void submitRecipe()}
                disabled={creatingRecipe}
                className="bg-gradient-gold text-white"
              >
                {creatingRecipe && <RefreshCw className="animate-spin h-4 w-4 me-2" />}
                {recipeEditFor ? (ar ? "حفظ النسخة المستقلة" : "Save independent snapshot") : (ar ? "حفظ الوصفة" : "Save recipe")}
              </Button>
            </div>
          </div>
        </div>
      )}


      {/* Persistent recipe material dialog */}
      {materialAddFor && (
        <div
          className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4"
          onClick={() => !addingMaterial && setMaterialAddFor(null)}
        >
          <div
            className="bg-background rounded-xl border shadow-xl w-full max-w-lg p-6 space-y-4"
            onClick={(e) => e.stopPropagation()}
          >
            <div>
              <h3 className="font-semibold text-lg">{ar ? "إضافة مادة حقيقية للوصفة" : "Add material to recipe"}</h3>
              <p className="text-sm text-muted-foreground mt-1">
                {materialAddFor.product_name} • {materialAddFor.size}
              </p>
            </div>
            <div className="space-y-2">
              <Label>{ar ? "المادة" : "Material"}</Label>
              <Select
                value={materialForm.material_id}
                onValueChange={(value) => {
                  const material = materials.find((m) => String(m.id) === value);
                  setMaterialForm((prev) => ({
                    ...prev,
                    material_id: value,
                    unit: material?.base_unit || prev.unit,
                  }));
                }}
              >
                <SelectTrigger><SelectValue placeholder={ar ? "اختر مادة" : "Select material"} /></SelectTrigger>
                <SelectContent className="max-h-72">
                  {materials.map((mat) => (
                    <SelectItem key={mat.id} value={String(mat.id)}>
                      {ar ? mat.name_ar || mat.name : mat.name} ({mat.base_unit})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-2">
                <Label>{ar ? "الكمية" : "Quantity"}</Label>
                <Input
                  value={materialForm.expected_qty}
                  inputMode="decimal"
                  onChange={(e) => setMaterialForm((prev) => ({ ...prev, expected_qty: e.target.value.replace(/[^0-9.]/g, "") }))}
                />
              </div>
              <div className="space-y-2">
                <Label>{ar ? "الوحدة" : "Unit"}</Label>
                <Input value={materialForm.unit} onChange={(e) => setMaterialForm((prev) => ({ ...prev, unit: e.target.value }))} />
              </div>
            </div>
            <div className="flex justify-end gap-2 pt-2 border-t">
              <Button variant="outline" disabled={addingMaterial} onClick={() => setMaterialAddFor(null)}>
                {ar ? "إلغاء" : "Cancel"}
              </Button>
              <Button className="bg-gradient-gold text-white" disabled={addingMaterial || !materialForm.material_id || !materialForm.expected_qty} onClick={() => void addMaterialToRecipe()}>
                {addingMaterial && <RefreshCw className="animate-spin h-4 w-4 me-2" />}
                {ar ? "حفظ المادة" : "Save material"}
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Success modal */}
      {success && (
        <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
          <Card className="w-full max-w-md">
            <CardHeader>
              <CardTitle className="flex gap-2 text-emerald-700">
                <CheckCircle2 />
                {ar ? "تم تجهيز الطلب" : "Order prepared"}
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <p className="text-sm text-muted-foreground">
                {ar
                  ? "تم تسجيل الاستهلاك وخصم المخزون"
                  : "Consumption and stock deduction recorded"}
              </p>
              {canViewCosts && (
                <div className="space-y-2">
                  <div className="border rounded-lg p-3 flex justify-between"><span>{ar ? "تكلفة المواد" : "Material cost"}</span><b>{Number(success.total_material_cost || 0).toLocaleString()} SYP</b></div>
                  <div className="border rounded-lg p-3 flex justify-between"><span>{ar ? "إجمالي تكلفة الإنتاج" : "Total production cost"}</span><b>{Number(success.total_production_cost || success.total_material_cost || 0).toLocaleString()} SYP</b></div>
                </div>
              )}
              <Button className="w-full" onClick={() => setSuccess(null)}>
                {ar ? "إغلاق" : "Close"}
              </Button>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
};

export default OrderPreparation;
