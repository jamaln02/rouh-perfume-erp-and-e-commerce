import rouhLogo from "@/assets/rouh-logo-transparent.png";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useLanguage } from "@/hooks/useLanguage";
import { useAuth } from "@/hooks/useAuth";
import { withAuthHeaders } from "@/lib/auth";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Card, CardContent } from "@/components/ui/card";
import { Eye, Download, Printer, Search, Plus, PackageCheck, Pencil, Trash2, ClipboardCheck, ChevronRight } from "lucide-react";
import { toast } from "sonner";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { calculateResidualAlcoholMl } from "@/lib/productionRules";

interface Order {
  id: string;
  customer_id?: string | null;
  customer_name: string;
  customer_phone: string;
  customer_address: string;
  city: string;
  status: string;
  total: number;
  shipping_cost: number;
  payment_method: string;
  payment_status?: string;
  paid_amount?: number;
  remaining_amount?: number;
  notes: string | null;
  created_at: string;
  source?: string;
  order_status?: string;
  consumption_status?: string;
  prepared_at?: string | null;
  order_status_display?: string;
}

interface OrderItem {
  id: string;
  product_name: string;
  quantity: number;
  size: string | null;
  price: number;
  // Optional fields populated by the backend for business orders (variant
  // linkage + per-line discount + custom blend bottle sizing).
  line_discount_amount?: number | null;
  product_variant_id?: string | null;
  bottle_size_ml?: number | null;
}

interface CatalogItem {
  id: string;
  name: string;
  type: "product" | "inventory";
  price: number;
  size: string | null;
  stock?: number | null;
  label: string;
  product_id?: string | null;
  product_variant_id?: string | null;
  search_text?: string;
}

const statusColors: Record<string, string> = {
  pending: "bg-yellow-500/20 text-yellow-600",
  confirmed: "bg-blue-500/20 text-blue-600",
  shipped: "bg-purple-500/20 text-purple-600",
  delivered: "bg-green-500/20 text-green-600",
  cancelled: "bg-red-500/20 text-red-600",
};

const AdminOrders = () => {
  const { lang } = useLanguage();
  const { permissions, user } = useAuth();
  const isEmployee = user?.role === "employee";
  const canViewCosts = user?.role === "admin" || user?.role === "manager";
  const navigate = useNavigate();
  const returnFocusRef = useRef<HTMLElement | null>(null);
  const createOrderRequestKeyRef = useRef<string | null>(null);
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "");
  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState<Order | null>(null);
  const [items, setItems] = useState<OrderItem[]>([]);
  const [itemsLoading, setItemsLoading] = useState(false);
  const [filter, setFilter] = useState<string>("all");
  const [search, setSearch] = useState("");
  const [showCreateDialog, setShowCreateDialog] = useState(false);
  const [showEditDialog, setShowEditDialog] = useState(false);
  const [paymentDialogOpen, setPaymentDialogOpen] = useState(false);
  const [paymentOrder, setPaymentOrder] = useState<Order | null>(null);
  const [paymentAmount, setPaymentAmount] = useState("0");
  const [paymentMethod, setPaymentMethod] = useState("cash");
  const [paymentNotes, setPaymentNotes] = useState("");
  const [paymentSaving, setPaymentSaving] = useState(false);
  const [editingOrder, setEditingOrder] = useState<Order | null>(null);
  const [editItems, setEditItems] = useState<any[]>([]);
  const [editReason, setEditReason] = useState("");
  const [createItems, setCreateItems] = useState<any[]>([{ catalog_id: "", product_name: "", size: "", quantity: 1, price: 0, discount: 0, product_id: null, product_variant_id: null }]);
  const [createSubmitting, setCreateSubmitting] = useState(false);
  const [createPickerIndex, setCreatePickerIndex] = useState<number | null>(null);
  const [createPickerSearch, setCreatePickerSearch] = useState("");
  const [oilMixEditor, setOilMixEditor] = useState<{ mode: "create" | "edit"; index: number } | null>(null);
  const [oilMixRows, setOilMixRows] = useState<Array<{ oil_id: string; grams: string }>>([{ oil_id: "", grams: "" }, { oil_id: "", grams: "" }]);
  const [catalogItems, setCatalogItems] = useState<CatalogItem[]>([]);
  const [catalogLoading, setCatalogLoading] = useState(false);
  const [editForm, setEditForm] = useState({
    customer_name: "",
    customer_phone: "",
    customer_address: "",
    city: "",
    shipping_fee: 0,
    payment_method: "cash",
    payment_status: "unpaid",
    order_status: "pending",
    paid_amount: 0,
    delivery_type: "delivery",
    notes: "",
  });
  const [createForm, setCreateForm] = useState({
    customer_name: "",
    customer_phone: "",
    customer_address: "",
    city: "",
    shipping_fee: 0,
    payment_method: "cash",
    payment_status: "unpaid",
    paid_amount: 0,
    delivery_type: "delivery",
    order_notes: "",
    product_variant_id: "",
    source: "offline",
  });
const [previewItems, setPreviewItems] = useState<any[]>([]);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [consumptionItems, setConsumptionItems] = useState<any[]>([]);
  const [confirmingConsumption, setConfirmingConsumption] = useState(false);
  const [acceptingOrder, setAcceptingOrder] = useState(false);
  const [oilGrams, setOilGrams] = useState(0);
  const [bottleSizeMl, setBottleSizeMl] = useState(100);
  const [alcoholMl, setAlcoholMl] = useState(100);
  const [bagCount, setBagCount] = useState(1);
  const [availableMaterials, setAvailableMaterials] = useState<any[]>([]);
  const [materialsLoading, setMaterialsLoading] = useState(false);
  const [stockWarnings, setStockWarnings] = useState<string[]>([]);
  const [orderDetails, setOrderDetails] = useState<any>(null);
  const [rejectingOrder, setRejectingOrder] = useState(false);
  const [printingInvoice, setPrintingInvoice] = useState(false);
  const [adjustmentRequests, setAdjustmentRequests] = useState<any[]>([]);
  const [adjustmentDialogOpen, setAdjustmentDialogOpen] = useState(false);
  const [adjustmentItem, setAdjustmentItem] = useState<any>(null);
  const [adjustmentQty, setAdjustmentQty] = useState("0");
  const [adjustmentReason, setAdjustmentReason] = useState("");
  const [adjustmentReviewNote, setAdjustmentReviewNote] = useState("");

// Redesigned production-intake state: per-line oil + container selection
  const [prepPackageInventory, setPrepPackageInventory] = useState<any[]>([
    { id: "1", oil_id: "", oil_grams: 0, bottle_id: "", bottle_size_ml: "50ml", box_id: "", bag_id: "", quantity: 1, alcohol_ml: 50 },
  ]);
  const [prepOils, setPrepOils] = useState<any[]>([]);
  const [prepBottles, setPrepBottles] = useState<any[]>([]);
  const [prepBoxes, setPrepBoxes] = useState<any[]>([]);
  const [prepBags, setPrepBags] = useState<any[]>([]);

  // Whole-order packaging counts (boxes & bags for the ENTIRE order, not per-line)
  const [prepOrderBoxId, setPrepOrderBoxId] = useState("");
  const [prepOrderBoxCount, setPrepOrderBoxCount] = useState(1);
  const [prepOrderBagId, setPrepOrderBagId] = useState("");
  const [prepOrderBagCount, setPrepOrderBagCount] = useState(1);

  const prepBottleSizes = ["3ml", "5ml", "10ml", "15ml", "20ml", "25ml", "30ml", "50ml", "100ml"];

  const fetchOrders = useCallback(async () => {
    setLoading(true);
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/orders`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data?.message || (lang === "ar" ? "تعذر تحميل الطلبات" : "Unable to load orders"));
      setOrders((data?.orders as Order[]) || []);
    } catch (error) {
      toast.error(error instanceof Error ? error.message : (lang === "ar" ? "تعذر تحميل الطلبات" : "Unable to load orders"));
    } finally {
      setLoading(false);
    }
  }, [apiBaseUrl, lang]);

  const fetchMaterials = useCallback(async () => {
    setMaterialsLoading(true);
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/orders/materials`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      setAvailableMaterials((data?.materials as any[]) || []);
    } catch (error) {
      setAvailableMaterials([]);
    } finally {
      setMaterialsLoading(false);
    }
  }, [apiBaseUrl]);

  const loadCatalog = useCallback(async () => {
    if (!permissions.includes('orders.create') && !permissions.includes('orders.edit') && !permissions.includes('products.manage')) {
      setCatalogItems([]);
      setCatalogLoading(false);
      return;
    }
    setCatalogLoading(true);
    try {
      const productsResponse = await fetch(`${apiBaseUrl}/api/admin/products`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const productsData = await productsResponse.json();
      if (!productsResponse.ok) {
        throw new Error(productsData?.message || (lang === "ar" ? "تعذر تحميل المنتجات" : "Unable to load products"));
      }
      const products = (productsData?.products as any[]) || [];

      const built: CatalogItem[] = [];
      let productsWithoutActiveVariants = 0;

      products.forEach((product: any) => {
        const variants = Array.isArray(product.variants)
          ? product.variants.filter((v: any) => v?.is_active !== false)
          : [];
        const displayName = lang === "ar" ? (product.name_ar || product.name) : (product.name || product.name_ar);

        if (variants.length === 0) {
          productsWithoutActiveVariants += 1;
          return;
        }

        variants.forEach((variant: any) => {
          const size = String(variant.size_label || "").trim();
          if (!size) return;
          const price = Number(variant.selling_price_default ?? variant.price ?? product.price ?? 0);
          built.push({
            id: `${product.id}-${variant.id}`,
            name: displayName,
            type: "product",
            price,
            size,
            stock: Number(product.stock ?? 0),
            label: `${displayName} · ${size} · ${price.toLocaleString()} SYP`,
            search_text: `${product.name || ''} ${product.name_ar || ''} ${size}`.toLocaleLowerCase(),
            product_id: product.id,
            product_variant_id: variant.id,
          });
        });
      });

      if (built.length === 0 && products.length > 0) {
        throw new Error(lang === "ar"
          ? "المنتجات موجودة لكن لا توجد أحجام (Variants) فعّالة قابلة للبيع. أضف Variant فعّال لكل منتج ثم أعد المحاولة."
          : "Products exist, but there are no active sellable variants. Add an active variant to the products and try again.");
      }
      if (productsWithoutActiveVariants > 0) {
        toast.warning(lang === "ar"
          ? `يوجد ${productsWithoutActiveVariants} منتج بدون Variant فعّال، ولن يظهر في إنشاء الطلب.`
          : `${productsWithoutActiveVariants} products have no active variant and will not appear in order creation.`);
      }

      setCatalogItems(built);
    } catch (error) {
      toast.error(error instanceof Error ? error.message : (lang === "ar" ? "تعذر تحميل المنتجات" : "Unable to load products"));
      setCatalogItems([]);
    } finally {
      setCatalogLoading(false);
    }
  }, [apiBaseUrl, lang, permissions]);

  useEffect(() => { void fetchOrders(); void fetchMaterials(); void loadCatalog(); }, [fetchOrders, fetchMaterials, loadCatalog]);

  const parseBottleSizeMl = (size: string | null | undefined) => {
    if (!size) return 100;
    const match = size.match(/(\d+)/);
    if (!match) return 100;
    return Number(match[1]);
  };

  // An order is considered "prepared" when its consumption/production has been
  // confirmed (consumption_status = consumed) or it has a prepared_at timestamp.
  const isOrderPrepared = (order: Order | null | undefined): boolean => {
    if (!order) return false;
    const cs = String(order.consumption_status || "").toLowerCase();
    if (cs === "consumed" || cs === "confirmed") return true;
    if (order.prepared_at) return true;
    return false;
  };

  // Parse the oil_mix JSON column from an order item
  // Returns [{ oil_id, grams, percentage }]
  const parseOilMix = (item: any): Array<{ oil_id: number; grams: number; percentage: number; name?: string }> => {
    const raw = item?.oil_mix;
    if (!raw) return [];
    try {
      const parsed = typeof raw === "string" ? JSON.parse(raw) : raw;
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  };

  const openOilMixEditor = (mode: "create" | "edit", index: number) => {
    const source = mode === "create" ? createItems[index] : editItems[index];
    const rows = parseOilMix(source).map((row) => ({ oil_id: String(row.oil_id || ""), grams: String(row.grams || "") }));
    setOilMixRows(rows.length >= 2 ? rows : [{ oil_id: "", grams: "" }, { oil_id: "", grams: "" }]);
    setOilMixEditor({ mode, index });
  };

  const saveOilMixEditor = () => {
    if (!oilMixEditor) return;
    const clean = oilMixRows.filter((row) => row.oil_id && Number(row.grams) > 0);
    if (clean.length < 2) {
      toast.warning(lang === "ar" ? "الميكس يجب أن يحتوي على زيتين على الأقل" : "A custom mix must contain at least two oils");
      return;
    }
    const source = oilMixEditor.mode === "create" ? createItems[oilMixEditor.index] : editItems[oilMixEditor.index];
    const volume = parseBottleSizeMl(source?.size || source?.bottle_size_ml || "");
    const total = clean.reduce((sum, row) => sum + Number(row.grams), 0);
    if (volume > 0 && total > volume + 0.0001) {
      toast.error(lang === "ar" ? `إجمالي الزيوت (${total}غ) أكبر من حجم العبوة (${volume}مل)` : `Total oils (${total}g) exceed bottle volume (${volume}ml)`);
      return;
    }
    const oilMix = clean.map((row) => ({
      oil_id: Number(row.oil_id),
      grams: Number(row.grams),
      percentage: Number((Number(row.grams) / total * 100).toFixed(2)),
    }));
    if (oilMixEditor.mode === "create") updateCreateItem(oilMixEditor.index, { oil_mix: oilMix });
    else updateEditItem(oilMixEditor.index, { oil_mix: oilMix });
    setOilMixEditor(null);
  };

  const oilMixMaterials = availableMaterials.filter((m) => m.material_category === "perfume_oil" && m.is_active !== false);

  // Parse the packaging_items JSON column from an order item
  // Returns [{ type, id, name, quantity }]
  const parsePackagingItems = (item: any): Array<{ type: string; id: number; name?: string; quantity: number }> => {
    const raw = item?.packaging_items;
    if (!raw) return [];
    try {
      const parsed = typeof raw === "string" ? JSON.parse(raw) : raw;
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  };

// Build prep production lines from order items (for prepared orders).
  // Reads oil_mix + bottle_size_ml + packaging_items to repopulate the edit form.
  const buildPrepLinesFromOrderItems = (orderItems: any[]): any[] => {
    if (!orderItems?.length) return [{ id: "1", oil_id: "", oil_grams: 0, bottle_id: "", bottle_size_ml: "50ml", box_id: "", bag_id: "", quantity: 1, alcohol_ml: 50 }];
    const lines = orderItems.map((it, index) => {
      const oilMix = parseOilMix(it);
      const packaging = parsePackagingItems(it);
      const bottlePackaging = packaging.find((p) => p.type === "bottle");
      const boxPackaging = packaging.find((p) => p.type === "box");
      const bagPackaging = packaging.find((p) => p.type === "bag");
      const sizeLabel = it.bottle_size_ml || it.size || "50ml";
      const sizeMl = parseInt(String(sizeLabel).replace("ml", "").trim(), 10) || 50;
      const oilGrams = oilMix.length > 0 ? Number(oilMix[0].grams || 0) : 0;
      return {
        id: String(index + 1),
        oil_id: oilMix.length > 0 ? String(oilMix[0].oil_id || "") : "",
        oil_grams: oilGrams,
        bottle_id: bottlePackaging ? String(bottlePackaging.id || "") : "",
        bottle_size_ml: sizeLabel,
        box_id: boxPackaging ? String(boxPackaging.id || "") : "",
        bag_id: bagPackaging ? String(bagPackaging.id || "") : "",
        quantity: Number(it.quantity || 1),
        alcohol_ml: calculateResidualAlcoholMl(sizeMl, oilGrams),
      };
    });
    return lines;
  };

  const extractWholeOrderPackaging = (orderItems: any[]): { boxId: string; boxCount: number; bagId: string; bagCount: number } => {
    const first = orderItems[0];
    if (!first) return { boxId: "", boxCount: 1, bagId: "", bagCount: 1 };
    const packaging = parsePackagingItems(first);
    const box = packaging.find((p) => p.type === "box");
    const bag = packaging.find((p) => p.type === "bag");
    return {
      boxId: box ? String(box.id || "") : "",
      boxCount: box ? Number(box.quantity || 1) : 1,
      bagId: bag ? String(bag.id || "") : "",
      bagCount: bag ? Number(bag.quantity || 1) : 1,
    };
  };

  const findBestMaterialMatch = (name: string) => {
    const normalized = String(name || "").toLowerCase();
    if (!normalized) return availableMaterials[0] || null;

    return availableMaterials.find((material) => {
      const materialName = String(material.name || "").toLowerCase();
      const materialNameAr = String(material.name_ar || "").toLowerCase();
      return materialName.includes(normalized) || materialNameAr.includes(normalized) || normalized.includes(materialName) || normalized.includes(materialNameAr);
    }) || availableMaterials[0] || null;
  };

  const makeDefaultConsumptionRow = (item?: any) => {
    const fallbackMaterial = findBestMaterialMatch(item?.material_name || item?.name || item?.product_name || "");
    return {
      material_id: fallbackMaterial?.id ?? null,
      expected_qty: Number(item?.expected_qty || 0),
      actual_qty: Number(item?.expected_qty || 0),
      unit: fallbackMaterial?.base_unit || item?.unit || "pcs",
      notes: item?.notes || "",
      material_name: fallbackMaterial?.name || item?.material_name || item?.name || item?.product_name || "",
      box_count: Number(item?.box_count || 1),
      bag_count: Number(item?.bag_count || 1),
    };
  };

const loadAdjustmentRequests = async () => {
    if (!permissions.includes("orders.consumption.review")) return;
    try {
      const r = await fetch(`${apiBaseUrl}/api/admin/consumption-adjustments?status=pending&per_page=50`, { headers: withAuthHeaders({ Accept: "application/json" }) });
      const d = await r.json();
      setAdjustmentRequests(Array.isArray(d?.data) ? d.data : []);
    } catch { setAdjustmentRequests([]); }
  };

  useEffect(() => { void loadAdjustmentRequests(); }, [apiBaseUrl, permissions]);

  const requestConsumptionCorrection = async (item: any) => {
    setAdjustmentItem(item);
    setAdjustmentQty(String(item.actual_qty ?? 0));
    setAdjustmentReason("");
    setAdjustmentDialogOpen(true);
  };

  const submitConsumptionCorrection = async () => {
    if (!adjustmentItem || !adjustmentReason.trim()) {
      toast.error(lang === "ar" ? "سبب التعديل مطلوب" : "A correction reason is required");
      return;
    }
    const qty = Number(adjustmentQty.replace(/,/g, ""));
    if (!Number.isFinite(qty) || qty < 0) {
      toast.error(lang === "ar" ? "أدخل كمية صحيحة" : "Enter a valid quantity");
      return;
    }
    try {
      const r = await fetch(`${apiBaseUrl}/api/admin/consumption-adjustments`, {
        method: "POST", headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json" }),
        body: JSON.stringify({ order_consumption_item_id: adjustmentItem.id, requested_actual_qty: qty, reason: adjustmentReason.trim() })
      });
      const d = await r.json(); if (!r.ok || !d?.ok) throw new Error(d?.message || "Failed to submit correction");
      toast.success(lang === "ar" ? "تم إرسال طلب التعديل للمراجعة" : "Correction request submitted for review");
      setAdjustmentDialogOpen(false);
      if (selected) await openDetails(selected);
      void loadAdjustmentRequests();
    } catch (e) { toast.error(e instanceof Error ? e.message : (lang === "ar" ? "تعذر إرسال الطلب" : "Failed to submit request")); }
  };

  const reviewConsumptionCorrection = async (id: number, decision: "approved" | "rejected") => {
    const label = decision === "approved" ? (lang === "ar" ? "الموافقة على التصحيح؟" : "Approve this correction?") : (lang === "ar" ? "رفض التصحيح؟" : "Reject this correction?");
    if (!window.confirm(label)) return;
    try {
      const r = await fetch(`${apiBaseUrl}/api/admin/consumption-adjustments/${id}/review`, {
        method: "POST", headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json" }),
        body: JSON.stringify({ decision, review_note: adjustmentReviewNote || undefined })
      });
      const d = await r.json(); if (!r.ok || !d?.ok) throw new Error(d?.message || "Review failed");
      toast.success(decision === "approved" ? (lang === "ar" ? "تمت الموافقة وتعديل المخزون والتكلفة" : "Approved; stock and cost updated") : (lang === "ar" ? "تم رفض الطلب" : "Request rejected"));
      setAdjustmentReviewNote(""); void loadAdjustmentRequests(); if (selected) await openDetails(selected);
    } catch (e) { toast.error(e instanceof Error ? e.message : (lang === "ar" ? "تعذر مراجعة الطلب" : "Review failed")); }
  };

  const openDetails = async (order: Order) => {
    returnFocusRef.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    setSelected(order);
    setItemsLoading(true);
    setPreviewItems([]);
    setConsumptionItems([]);
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/orders/${order.id}`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      const orderItems = (data?.items as OrderItem[]) || [];
      setItems(orderItems);
      setOrderDetails(data);
      const firstSize = orderItems[0]?.size ?? null;
      const initialBottleSize = parseBottleSizeMl(firstSize);
      setBottleSizeMl(initialBottleSize || 100);
      setOilGrams(0);
      setAlcoholMl(initialBottleSize || 100);
      setBagCount(1);
    } finally {
      setItemsLoading(false);
    }
  };

  const openConsumptionSetup = async (order: Order) => {
    returnFocusRef.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    setSelected(order);
    setPreviewItems([]);
    setConsumptionItems([]);
    setPreviewLoading(true);
    try {
      const detailsResponse = await fetch(`${apiBaseUrl}/api/admin/orders/${order.id}`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const detailsData = await detailsResponse.json();
      const orderItems = (detailsData?.items as OrderItem[]) || [];
      setItems(orderItems);
      setOrderDetails(detailsData);
      const firstSize = orderItems[0]?.size ?? null;
      const initialBottleSize = parseBottleSizeMl(firstSize);
      setBottleSizeMl(initialBottleSize || 100);
      setOilGrams(0);
      setAlcoholMl(initialBottleSize || 100);
      setBagCount(1);

      const response = await fetch(`${apiBaseUrl}/api/admin/orders/${order.id}/consumption-preview`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      if (!response.ok) {
        throw new Error(data?.message || "Failed to load expected consumption");
      }

      const expectedItems = data?.expected_items || [];
      setPreviewItems(expectedItems);
      setConsumptionItems(buildConsumptionPayload(expectedItems));
      if (expectedItems.length === 0) {
        toast.warning(lang === "ar" ? "لا توجد مكونات استهلاك مرتبطة بهذا الطلب الآن. يمكنك إدخال الكميات يدويًا أو إضافة وصفة للمنتج." : "No consumption components are linked to this order yet. You can enter quantities manually or add a recipe for the product.");
      }
    } catch (error) {
      toast.error(error instanceof Error ? error.message : (lang === "ar" ? "تعذر تحميل إعداد الاستهلاك" : "Could not load the consumption setup"));
      setPreviewItems([]);
      setConsumptionItems([]);
    } finally {
      setPreviewLoading(false);
      setItemsLoading(false);
    }
  };

  const addManualConsumptionRow = () => {
    setConsumptionItems((prev) => [
      ...prev,
      makeDefaultConsumptionRow(),
    ]);
  };

  const updateConsumptionRow = (index: number, updates: Record<string, any>) => {
    setConsumptionItems((prev) => prev.map((row, rowIndex) => (rowIndex === index ? { ...row, ...updates } : row)));
  };

  const validateStock = (items: any[]) => {
    const warnings: string[] = [];
    items.forEach((item) => {
      if (!item.material_id) return;
      const material = availableMaterials.find((entry) => String(entry.id) === String(item.material_id));
      if (!material) return;
      const requested = Number(item.actual_qty || 0);
      const stock = Number(material.current_stock || 0);
      if (requested > stock) {
        warnings.push(`${material.name}: ${lang === "ar" ? "الكمية المطلوبة تتجاوز المخزون المتاح" : "Requested quantity exceeds available stock"} (${requested} > ${stock})`);
      }
    });
    setStockWarnings(warnings);
    return warnings;
  };

const openEditDialog = async (order: Order) => {
    returnFocusRef.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    setEditingOrder(order);
    setSelected(null);
    setShowEditDialog(true);
    setItemsLoading(true);
    setConsumptionItems([]);
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/orders/${order.id}`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      const orderItems = (data?.items as OrderItem[]) || [];
      setItems(orderItems);
      setOrderDetails(data);
      const firstSize = orderItems[0]?.size ?? null;
      const initialBottleSize = parseBottleSizeMl(firstSize);
      setBottleSizeMl(initialBottleSize || 100);
      setOilGrams(0);
      setAlcoholMl(initialBottleSize || 100);
      setBagCount(1);
      setEditForm({
        customer_name: data?.order?.customer_name || order.customer_name || "",
        customer_phone: data?.order?.customer_phone || order.customer_phone || "",
        customer_address: data?.order?.customer_address || order.customer_address || "",
        city: data?.order?.city || order.city || "",
        shipping_fee: Number(data?.order?.shipping_fee ?? order.shipping_cost ?? 0),
        payment_method: data?.order?.payment_method || order.payment_method || "cash",
        payment_status: data?.order?.payment_status || "unpaid",
        order_status: data?.order?.order_status || data?.order?.status || order.status || "pending",
        paid_amount: Number(data?.order?.paid_amount ?? 0),
delivery_type: data?.order?.delivery_type || "delivery",
        notes: data?.order?.notes || order.notes || "",
      });
      setEditReason("");
      setEditItems(orderItems.length > 0 ? orderItems.map((it) => ({
        product_name: it.product_name,
        size: it.size || "",
        quantity: Number(it.quantity || 1),
        price: Number(it.price || 0),
        discount: Number(it.line_discount_amount || 0),
        product_variant_id: it.product_variant_id || null,
        oil_mix: parseOilMix(it),
      })) : [{ product_name: "", size: "", quantity: 1, price: 0, discount: 0, product_variant_id: null }]);
    } catch {
      toast.error(lang === "ar" ? "تعذر تحميل بيانات الطلب للتعديل" : "Unable to load order for editing");
    } finally {
      setItemsLoading(false);
    }
  };

const addEditItemRow = () => {
    setEditItems((prev) => [...prev, { product_name: "", size: "", quantity: 1, price: 0, discount: 0, oil_mix: [] }]);
  };

  const removeEditItem = (index: number) => {
    setEditItems((prev) => (prev.length > 1 ? prev.filter((_, i) => i !== index) : prev));
  };

  const updateEditItem = (index: number, updates: Record<string, any>) => {
    setEditItems((prev) => prev.map((row, rowIndex) => (rowIndex === index ? { ...row, ...updates } : row)));
  };

const updateOrder = async () => {
    if (!editingOrder) return;
    let reasonForUpdate = editReason.trim();
    if (editForm.order_status === "cancelled" && !reasonForUpdate) {
      reasonForUpdate = window.prompt(lang === "ar" ? "اذكر سبب إلغاء الطلب:" : "Reason for cancelling the order:")?.trim() || "";
      if (!reasonForUpdate) {
        toast.error(lang === "ar" ? "سبب الإلغاء مطلوب" : "Cancellation reason is required");
        return;
      }
      setEditReason(reasonForUpdate);
    }
    const items = editItems.map((item) => ({
      product_name: String(item.product_name || '').trim(),
      size: String(item.size || '').trim(),
      quantity: Math.max(1, Number(item.quantity || 1)),
      price: Math.max(0, Number(item.price || 0)),
      discount: Math.max(0, Number(item.discount || 0)),
      product_id: item.product_id || null,
      product_variant_id: item.product_variant_id || null,
      oil_mix: item.oil_mix || null,
    }));
    if (items.some((item) => !item.product_name || !item.size)) {
      toast.error(lang === 'ar' ? 'أكمل اسم المنتج والحجم قبل الحفظ.' : 'Complete product name and size before saving.');
      return;
    }
    setConfirmingConsumption(true);
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/orders/${editingOrder.id}`, {
        method: 'PATCH',
        headers: withAuthHeaders({ 'Content-Type': 'application/json', Accept: 'application/json' }),
        body: JSON.stringify({
          customer_name: editForm.customer_name, customer_phone: editForm.customer_phone,
          customer_address: editForm.customer_address, city: editForm.city, notes: editForm.notes,
          payment_method: editForm.payment_method, payment_status: editForm.payment_status,
          shipping_fee: Number(editForm.shipping_fee || 0), paid_amount: Number(editForm.paid_amount || 0),
          delivery_type: editForm.delivery_type, order_status: editForm.order_status, reason: reasonForUpdate || undefined, items,
        }),
      });
      const data = await response.json();
      if (!response.ok || !data?.ok) {
        toast.error(data?.message || (lang === 'ar' ? 'فشل تحديث الطلب' : 'Failed to update order'));
        return;
      }
      toast.success(lang === 'ar' ? 'تم تحديث الطلب بنجاح' : 'Order updated successfully');
      setShowEditDialog(false); setEditingOrder(null); setEditItems([]); setSelected(null);
      void fetchOrders();
    } catch {
      toast.error(lang === 'ar' ? 'تعذر الاتصال بالخادم أثناء تحديث الطلب' : 'Unable to update order');
    } finally {
      setConfirmingConsumption(false);
    }
  };

  const searchableCreateProducts = useMemo(() => {
    const byProduct = new Map<string, { productId: string; name: string; nameAr: string; items: CatalogItem[] }>();
    const q = createPickerSearch.trim().toLocaleLowerCase();
    for (const item of catalogItems) {
      if (item.type !== "product") continue;
      const productId = String(item.product_id || "");
      const haystack = item.search_text || `${item.name} ${item.label} ${item.size || ""}`.toLocaleLowerCase();
      if (q && !haystack.includes(q)) continue;
      if (!byProduct.has(productId)) byProduct.set(productId, { productId, name: item.name, nameAr: item.name, items: [] });
      byProduct.get(productId)!.items.push(item);
    }
    return Array.from(byProduct.values()).slice(0, 60);
  }, [catalogItems, createPickerSearch]);

  const openCreatePicker = (index: number) => {
    setCreatePickerIndex(index);
    setCreatePickerSearch("");
  };

  const closeCreatePicker = () => {
    setCreatePickerIndex(null);
    setCreatePickerSearch("");
  };

  const addCreateItemRow = () => {
    setCreateItems((prev) => [...prev, { catalog_id: "", product_name: "", size: "", quantity: 1, price: 0, discount: 0, product_id: null, product_variant_id: null }]);
  };

  const updateCreateItem = (index: number, updates: Record<string, any>) => {
    setCreateItems((prev) => prev.map((row, rowIndex) => (rowIndex === index ? { ...row, ...updates } : row)));
  };

  const handleEditItemSelection = (index: number, value: string) => {
    const catalogItem = catalogItems.find((c) => String(c.id) === String(value));
    if (!catalogItem) {
      updateEditItem(index, { catalog_id: "", product_name: "", size: "", price: 0, product_variant_id: null });
      return;
    }
    updateEditItem(index, {
      catalog_id: catalogItem.id,
      product_name: catalogItem.name,
      size: catalogItem.size || "",
      price: Number(catalogItem.price || 0),
      product_id: catalogItem.product_id || null,
      product_variant_id: catalogItem.product_variant_id || null,
      oil_mix: [],
    });
  };
const handleCreateItemSelection = (index: number, value: string) => {
    const catalogItem = catalogItems.find((c) => String(c.id) === String(value));
    if (catalogItem) {
      updateCreateItem(index, {
        catalog_id: catalogItem.id,
        product_name: catalogItem.name,
        size: catalogItem.size || "",
        price: Number(catalogItem.price || 0),
        product_id: catalogItem.product_id || null,
        product_variant_id: catalogItem.product_variant_id || null,
      });
    } else {
      updateCreateItem(index, { catalog_id: "", product_name: "", size: "", price: 0, product_id: null, product_variant_id: null });
    }
  };

  const removeCreateItem = (index: number) => {
    setCreateItems((prev) => (prev.length > 1 ? prev.filter((_, i) => i !== index) : prev));
  };

  const createOfflineOrder = async () => {
    if (createSubmitting) return;

    const customerName = createForm.customer_name.trim();
    const customerPhone = createForm.customer_phone.trim();
    if (!customerName) {
      toast.error(lang === "ar" ? "اسم العميل مطلوب" : "Customer name is required");
      return;
    }
    if (!customerPhone) {
      toast.error(lang === "ar" ? "رقم الهاتف مطلوب لإنشاء العميل وتتبّع الطلب" : "Customer phone is required to create the customer and track the order");
      return;
    }

    const normalizedItems = createItems.filter((item) => item.product_name?.trim());
    if (!normalizedItems.length) {
      toast.error(lang === "ar" ? "يرجى اختيار صنف واحد على الأقل" : "Please select at least one item");
      return;
    }

    const missingVariant = normalizedItems.findIndex((item) => !item.product_variant_id);
    if (missingVariant !== -1) {
      toast.error(lang === "ar" ? `يرجى اختيار المنتج والحجم للصنف رقم ${missingVariant + 1}` : `Please select a product and size for item ${missingVariant + 1}`);
      return;
    }

    const idempotencyKey = createOrderRequestKeyRef.current ?? crypto.randomUUID();
    createOrderRequestKeyRef.current = idempotencyKey;
    setCreateSubmitting(true);

    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/orders`, {
        method: "POST",
        headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json", "Idempotency-Key": idempotencyKey }),
        body: JSON.stringify({
          ...createForm,
          customer_name: customerName,
          customer_phone: customerPhone,
          shipping_fee: Number(createForm.shipping_fee || 0),
          paid_amount: Number(createForm.paid_amount || 0),
          items: normalizedItems.map((item) => ({
            product_name: item.product_name,
            size: item.size || "",
            quantity: Number(item.quantity || 1),
            price: Number(item.price || 0),
            discount: Number(item.discount || 0),
            product_id: item.product_id || null,
            product_variant_id: item.product_variant_id || null,
            oil_mix: item.oil_mix || null,
          })),
        }),
      });

      const data = await response.json().catch(() => null);
      if (!response.ok || !data?.ok) {
        const validationMessage = data?.errors
          ? Object.values(data.errors).flat().filter(Boolean).join("\n")
          : "";
        throw new Error(
          validationMessage || data?.message ||
          (lang === "ar" ? `تعذر إنشاء الطلب (HTTP ${response.status})` : `Unable to create order (HTTP ${response.status})`)
        );
      }

      createOrderRequestKeyRef.current = null;
      toast.success(lang === "ar" ? "تم إنشاء الطلب بنجاح" : "Order created successfully");
      setShowCreateDialog(false);
      setCreateForm({
        customer_name: "", customer_phone: "", customer_address: "", city: "", shipping_fee: 0,
        payment_method: "cash", payment_status: "unpaid", paid_amount: 0, delivery_type: "delivery",
        order_notes: "", product_variant_id: "", source: "offline",
      });
      setCreateItems([{ catalog_id: "", product_name: "", size: "", quantity: 1, price: 0, discount: 0, product_id: null, product_variant_id: null }]);
      void fetchOrders();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : (lang === "ar" ? "تعذر إنشاء الطلب" : "Unable to create order"));
    } finally {
      setCreateSubmitting(false);
    }
  };

  const buildConsumptionPayload = (items: any[]) => {
    return items.map((item: any) => {
      const normalizedName = String(item?.material_name || item?.name || "").toLowerCase();
      let actualQty = Number(item.expected_qty || 0);

      if (selected?.source === "online" || selected?.source === "website" || selected?.source === "web") {
        if (normalizedName.includes("alcohol") || normalizedName.includes("الكحول") || normalizedName.includes("ethanol")) {
          actualQty = calculateResidualAlcoholMl(bottleSizeMl, oilGrams);
        } else if (normalizedName.includes("oil") || normalizedName.includes("زيت") || normalizedName.includes("fragrance") || normalizedName.includes("عطر") || normalizedName.includes("perfume")) {
          actualQty = Math.max(0, oilGrams);
        }
      }

      return {
        material_id: item.material_id || findBestMaterialMatch(item.material_name || item.name || "")?.id || null,
        expected_qty: Number(item.expected_qty || 0),
        actual_qty: actualQty,
        unit: item.unit,
        notes: item.notes || "",
        box_count: Number(item.box_count || 1),
        bag_count: Number(item.bag_count || bagCount || 1),
      };
    });
  };

  const previewConsumption = async (orderId: string) => {
    setPreviewLoading(true);
    setPreviewItems([]);
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/orders/${orderId}/consumption-preview`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      if (!response.ok) {
        throw new Error(data?.message || "Failed to load expected consumption");
      }

      const expectedItems = data?.expected_items || [];
      setPreviewItems(expectedItems);
      setConsumptionItems(buildConsumptionPayload(expectedItems));
      if (expectedItems.length === 0) {
        toast.warning(lang === "ar" ? "لا توجد مكونات استهلاك مرتبطة بهذا الطلب الآن. يمكنك إدخال الكميات يدويًا أو إضافة وصفة للمنتج." : "No consumption components are linked to this order yet. You can enter quantities manually or add a recipe for the product.");
      }
    } catch (error) {
      toast.error(error instanceof Error ? error.message : (lang === "ar" ? "تعذر تحميل إعداد الاستهلاك" : "Could not load the consumption setup"));
      setPreviewItems([]);
      setConsumptionItems([]);
    } finally {
      setPreviewLoading(false);
    }
  };

  const confirmConsumption = async (orderId: string) => {
    const warnings = validateStock(consumptionItems);
    if (warnings.length > 0) {
      toast.error(lang === "ar" ? "لا يمكن تأكيد الاستهلاك لأن بعض الكميات تتجاوز المخزون المتاح." : "Consumption cannot be confirmed because some quantities exceed the available stock.");
      return null;
    }

    setConfirmingConsumption(true);
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/orders/${orderId}/consumption-confirm`, {
        method: "POST",
        headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json" }),
        body: JSON.stringify({
          items: consumptionItems,
          production: {
            source: selected?.source || "offline",
            oil_grams: oilGrams,
            bottle_size_ml: bottleSizeMl,
            alcohol_ml: alcoholMl,
          },
        }),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data?.ok) {
        toast.error(data?.message || (lang === "ar" ? "تعذر تأكيد الاستهلاك" : "Failed to confirm consumption"));
        return null;
      }
      toast.success(lang === "ar" ? "تم تأكيد الاستهلاك وتحديث المخزون والبيع بنجاح" : "Consumption confirmed and inventory/sale updated");
      setSelected(null);
      void fetchOrders();
      return data?.whatsapp_link || null;
    } catch {
      toast.error(lang === "ar" ? "تعذر الاتصال بالخادم أثناء تأكيد الاستهلاك" : "Unable to reach the server while confirming consumption");
      return null;
    } finally {
      setConfirmingConsumption(false);
    }
  };

  const openOrderPreparation = async (order: Order) => {
    const source = String(order.source || "").toLowerCase();
    const currentStatus = String(order.status || order.order_status || "").toLowerCase();
    const isWebsite = ["online", "website", "web"].includes(source);
    const needsAcceptance = isWebsite && ["pending", "confirmed"].includes(currentStatus);

    if (needsAcceptance) {
      const accepted = await acceptOrder(order.id);
      if (!accepted) return;
    }

    navigate(`/admin/order-preparation?orderId=${encodeURIComponent(order.id)}`);
  };

  const acceptOrder = async (id: string) => {
    setAcceptingOrder(true);
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/orders/${id}/accept`, {
        method: "POST",
        headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json" }),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data?.ok) {
        toast.error(data?.message || (lang === "ar" ? "تعذر قبول الطلب" : "Failed to accept order"));
        return false;
      }
      toast.success(lang === "ar" ? "تم قبول الطلب وأصبح جاهزاً للتجهيز" : "Order accepted and ready for preparation");
      void fetchOrders();
      return true;
    } catch {
      toast.error(lang === "ar" ? "تعذر الاتصال بالخادم أثناء قبول الطلب" : "Unable to reach the server while accepting the order");
      return false;
    } finally {
      setAcceptingOrder(false);
    }
  };

  const confirmPreparedOrder = async () => {
    if (!selected) return;

    if (selected.source === "online" || selected.source === "website" || selected.source === "web") {
      const accepted = await acceptOrder(selected.id);
      if (!accepted) return;
    }

    const whatsappLink = await confirmConsumption(selected.id);
    if (whatsappLink) {
      window.open(whatsappLink, "_blank");
    }
  };

// ---- Redesigned production-intake handlers ----
  const loadOrderPreparationInventory = async () => {
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/order-preparation/inventory`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data?.error || "Failed to load inventory");
      setPrepOils(Array.isArray(data?.essential_oils) ? data.essential_oils : []);
      setPrepBottles(Array.isArray(data?.bottles) ? data.bottles : []);
      setPrepBoxes(Array.isArray(data?.boxes) ? data.boxes : []);
      setPrepBags(Array.isArray(data?.bags) ? data.bags : []);
    } catch (error) {
      toast.error(error instanceof Error ? error.message : (lang === "ar" ? "تعذر تحميل مخزون التجهيز" : "Unable to load preparation inventory"));
      setPrepOils([]);
      setPrepBottles([]);
      setPrepBoxes([]);
      setPrepBags([]);
    }
  };

const updatePrepLine = (lineId: string, updates: Record<string, any>) => {
    setPrepPackageInventory((prev) => prev.map((line) => {
      if (line.id !== lineId) return line;
      const updated = { ...line, ...updates };

      // When a bottle is selected, auto-derive the bottle size from the selected bottle's size_ml
      if (updates.bottle_id !== undefined) {
        const bottle = prepBottles.find((b) => String(b.id) === String(updated.bottle_id));
        if (bottle && bottle.size_ml) {
          const sizeRaw = String(bottle.size_ml).replace("ml", "").trim();
          const sizeLabel = /^\d+$/.test(sizeRaw) ? `${sizeRaw}ml` : String(bottle.size_ml);
          updated.bottle_size_ml = sizeLabel;
        }
      }

      // Auto-calculate alcohol when oil grams or bottle size changes
      if (updates.oil_grams !== undefined || updates.bottle_size_ml !== undefined || updates.bottle_id !== undefined) {
        const sizeMl = parseInt(String(updated.bottle_size_ml).replace("ml", ""), 10) || 0;
        updated.alcohol_ml = calculateResidualAlcoholMl(sizeMl, Number(updated.oil_grams || 0));
      }

      return updated;
    }));
  };

  // Auto-select the oil for a line based on the order item's product name (best-effort match)
  const autoSelectOilForLine = (lineId: string, productName: string) => {
    const normalized = String(productName || "").toLowerCase();
    const match = prepOils.find((oil) => {
      const n = String(oil.name || "").toLowerCase();
      const nAr = String(oil.name_ar || "").toLowerCase();
      return n.includes(normalized) || nAr.includes(normalized) || normalized.includes(n) || normalized.includes(nAr);
    });
    if (match) {
      updatePrepLine(lineId, { oil_id: String(match.id) });
    }
  };

  const addPrepLine = () => {
    setPrepPackageInventory((prev) => [
      ...prev,
      { id: String(prev.length + 1), oil_id: "", oil_grams: 0, bottle_id: "", bottle_size_ml: "50ml", box_id: "", bag_id: "", quantity: 1, alcohol_ml: 50 },
    ]);
  };

  const removePrepLine = (lineId: string) => {
    setPrepPackageInventory((prev) => (prev.length > 1 ? prev.filter((l) => l.id !== lineId) : prev));
  };

  const prepLineStock = (line: any) => {
    const oil = prepOils.find((o) => String(o.id) === String(line.oil_id));
    const bottle = prepBottles.find((b) => String(b.id) === String(line.bottle_id));
    const box = prepBoxes.find((b) => String(b.id) === String(line.box_id));
    const bag = prepBags.find((b) => String(b.id) === String(line.bag_id));
    const qty = Math.max(1, Number(line.quantity || 1));
    const oilReq = Number(line.oil_grams || 0) * qty;
    const issues: string[] = [];
    if (oil && oilReq > Number(oil.current_stock_grams || 0)) {
      issues.push(`${oil.name} ${lang === "ar" ? "الزيت غير متوفر" : "oil short"}: ${oilReq} > ${oil.current_stock_grams}g`);
    }
    if (bottle && qty > Number(bottle.current_stock || 0)) {
      issues.push(`${bottle.name} ${lang === "ar" ? "الزجاجات غير كافية" : "bottles short"}: ${qty} > ${bottle.current_stock}`);
    }
    if (box && qty > Number(box.current_stock || 0)) {
      issues.push(`${box.name} ${lang === "ar" ? "الصناديق غير كافية" : "boxes short"}: ${qty} > ${box.current_stock}`);
    }
    if (bag && qty > Number(bag.current_stock || 0)) {
      issues.push(`${bag.name} ${lang === "ar" ? "الأكياس غير كافية" : "bags short"}: ${qty} > ${bag.current_stock}`);
    }
    return issues;
  };

  const validatePrepLines = () => {
    const warnings: string[] = [];
    prepPackageInventory.forEach((line, index) => {
      if (!line.oil_id) {
        warnings.push(`${lang === "ar" ? "المنتج" : "Product"} ${index + 1}: ${lang === "ar" ? "اختر نوع الزيت" : "select an oil"}`);
      }
      if (Number(line.oil_grams) <= 0) {
        warnings.push(`${lang === "ar" ? "المنتج" : "Product"} ${index + 1}: ${lang === "ar" ? "أدخل كمية الزيت" : "enter oil grams"}`);
      }
      if (!line.bottle_id) {
        warnings.push(`${lang === "ar" ? "المنتج" : "Product"} ${index + 1}: ${lang === "ar" ? "اختر الزجاجة" : "select a bottle"}`);
      }
      warnings.push(...prepLineStock(line));
    });
    return warnings;
  };

  const [deletingOrder, setDeletingOrder] = useState(false);

  const deleteOrder = async (order: Order) => {
    if (!window.confirm(lang === "ar" ? "هل أنت متأكد من حذف هذا الطلب؟" : "Are you sure you want to delete this order?")) return;
    setDeletingOrder(true);
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/orders/${order.id}`, {
        method: "DELETE",
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      if (!response.ok || !data?.ok) {
        toast.error(data?.message || (lang === "ar" ? "فشل حذف الطلب" : "Failed to delete order"));
        return;
      }
toast.success(lang === "ar" ? "تم حذف الطلب بنجاح" : "Order deleted successfully");
      setSelected(null);
      setShowEditDialog(false);
      setEditingOrder(null);
      setEditItems([]);
      void fetchOrders();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : (lang === "ar" ? "فشل حذف الطلب" : "Failed to delete order"));
    } finally {
      setDeletingOrder(false);
    }
  };

  const rejectOrder = async (order: Order) => {
    const reason = window.prompt(lang === "ar" ? "اذكر سبب رفض الطلب:" : "Reason for rejecting the order:")?.trim() || "";
    if (!reason) {
      toast.error(lang === "ar" ? "سبب الرفض مطلوب" : "A rejection reason is required");
      return;
    }
    setRejectingOrder(true);
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/orders/${order.id}/status`, {
        method: "PATCH",
        headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json" }),
        body: JSON.stringify({ status: "cancelled", reason }),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data?.ok) {
        toast.error(data?.message || (lang === "ar" ? "تعذر رفض الطلب" : "Failed to reject order"));
        return;
      }
      toast.success(lang === "ar" ? "تم رفض الطلب وتسجيل السبب في سجل العمليات" : "Order rejected and reason recorded in the audit log");
      setSelected(null);
      void fetchOrders();
    } catch {
      toast.error(lang === "ar" ? "تعذر الاتصال بالخادم أثناء رفض الطلب" : "Unable to reach the server while rejecting the order");
    } finally {
      setRejectingOrder(false);
    }
  };

const confirmPreparedOrderRedesigned = async () => {
    const warnings = validatePrepLines();
    if (warnings.length > 0) {
      toast.error(lang === "ar" ? "لا يمكن تأكيد الطلب: " + warnings.join(" · ") : "Cannot confirm order: " + warnings.join(" · "));
      return;
    }
    if (!selected) return;

    // Validate whole-order box/bag selection
    if (!prepOrderBoxId) {
      toast.error(lang === "ar" ? "يرجى اختيار الصندوق للطلب" : "Please select a box for the order");
      return;
    }
    if (!prepOrderBagId) {
      toast.error(lang === "ar" ? "يرجى اختيار الحقيبة للطلب" : "Please select a bag for the order");
      return;
    }
    const box = prepBoxes.find((b) => String(b.id) === String(prepOrderBoxId));
    const bag = prepBags.find((b) => String(b.id) === String(prepOrderBagId));
    if (box && Number(prepOrderBoxCount) > Number(box.current_stock || 0)) {
      toast.error(lang === "ar" ? `الصناديق غير كافية: ${prepOrderBoxCount} > ${box.current_stock}` : `Not enough boxes: ${prepOrderBoxCount} > ${box.current_stock}`);
      return;
    }
    if (bag && Number(prepOrderBagCount) > Number(bag.current_stock || 0)) {
      toast.error(lang === "ar" ? `الأكياس غير كافية: ${prepOrderBagCount} > ${bag.current_stock}` : `Not enough bags: ${prepOrderBagCount} > ${bag.current_stock}`);
      return;
    }

    setConfirmingConsumption(true);
    try {
      // Build payload matching the working OrderPreparationController::prepareOrder endpoint
      const orderData = {
        customer_info: {
          name: selected.customer_name,
          phone: selected.customer_phone,
          address: selected.customer_address,
          order_source: selected.source === "online" || selected.source === "website" || selected.source === "web" ? "website" : "physical_store",
        },
        website_order_id: selected.id,
        customer_id: selected.customer_id || null,
        items: prepPackageInventory.map((line) => ({
          essential_oil_id: Number(line.oil_id),
          oil_grams: Number(line.oil_grams || 0),
          bottle_size_ml: line.bottle_size_ml,
          bottle_id: Number(line.bottle_id),
          box_id: Number(prepOrderBoxId),
          box_count: Number(prepOrderBoxCount),
          bag_id: Number(prepOrderBagId),
          bag_count: Number(prepOrderBagCount),
          quantity: Number(line.quantity || 1),
        })),
      };


      const response = await fetch(`${apiBaseUrl}/api/admin/order-preparation/prepare`, {
        method: "POST",
        headers: withAuthHeaders({ "Content-Type": "application/json" }),
        body: JSON.stringify(orderData),
      });
      const data = await response.json();

      if (!response.ok || !data?.ok) {
        toast.error(data?.message || "Failed to confirm order");
        return;
      }

      toast.success(lang === "ar" ? "تم تأكيد الطلب وتحديث المخزون والمبيعات" : "Order confirmed and inventory/sales updated");
      setSelected(null);
      void fetchOrders();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : (lang === "ar" ? "فشل تأكيد الطلب" : "Failed to confirm order"));
    } finally {
      setConfirmingConsumption(false);
    }
  };

async function initRedesignedPrep() {
    await loadOrderPreparationInventory();
  }

  const openPaymentDialog = (order: Order) => {
    setPaymentOrder(order);
    const remaining = Math.max(0, Number(order.remaining_amount ?? (Number(order.total || 0) - Number(order.paid_amount || 0))));
    setPaymentAmount(remaining > 0 ? String(remaining) : "0");
    setPaymentMethod(order.payment_method || "cash");
    setPaymentNotes("");
    setPaymentDialogOpen(true);
  };

  const registerPayment = async () => {
    if (!paymentOrder) return;
    const amount = Number(paymentAmount);
    if (!Number.isFinite(amount) || amount <= 0) {
      toast.error(lang === "ar" ? "أدخل مبلغ دفعة صحيح" : "Enter a valid payment amount");
      return;
    }
    setPaymentSaving(true);
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/orders/${paymentOrder.id}/payments`, {
        method: "POST",
        headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json", "Idempotency-Key": crypto.randomUUID() }),
        body: JSON.stringify({ amount, payment_method: paymentMethod, notes: paymentNotes || undefined }),
      });
      const data = await response.json();
      if (!response.ok || !data?.ok) throw new Error(data?.message || "Failed to register payment");
      toast.success(lang === "ar" ? "تم تسجيل الدفعة وتحديث حالة الدفع" : "Payment recorded and payment status updated");
      setPaymentDialogOpen(false);
      const refreshed = await fetch(`${apiBaseUrl}/api/admin/orders/${paymentOrder.id}`, { headers: withAuthHeaders({ Accept: "application/json" }) });
      if (refreshed.ok) {
        const details = await refreshed.json();
        setSelected((details?.order as Order) || paymentOrder);
        setOrderDetails(details);
      }
      await fetchOrders();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : (lang === "ar" ? "تعذر تسجيل الدفعة" : "Unable to register payment"));
    } finally {
      setPaymentSaving(false);
    }
  };

  const updateStatus = async (id: string, status: string) => {
    let reason = '';
    if (status === 'cancelled') {
      reason = window.prompt(lang === 'ar' ? 'اذكر سبب إلغاء الطلب:' : 'Reason for cancellation:')?.trim() || '';
      if (!reason) {
        toast.error(lang === 'ar' ? 'سبب الإلغاء مطلوب' : 'Cancellation reason is required');
        return;
      }
    }
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/orders/${id}/status`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...withAuthHeaders() },
        body: JSON.stringify({ status, reason }),
      });
      const data = await response.json();
      if (!response.ok || !data?.ok) throw new Error(data?.message || (lang === 'ar' ? 'تعذر تحديث الحالة' : 'Failed to update status'));
      toast.success(lang === 'ar' ? 'تم تحديث حالة الطلب' : 'Order status updated');
      void fetchOrders();
      if (data.whatsapp_link) window.open(data.whatsapp_link, '_blank', 'noopener,noreferrer');
    } catch (error) {
      toast.error(error instanceof Error ? error.message : (lang === 'ar' ? 'تعذر تحديث الحالة' : 'Failed to update status'));
    }
  };

  const statuses = ["pending", "confirmed", "shipped", "delivered", "cancelled"];
  const statusLabel = (s: string) => {
    const map: Record<string, { ar: string; en: string }> = {
      pending: { ar: "طلبات معلقة", en: "Pending" },
      confirmed: { ar: "مؤكد", en: "Confirmed" },
      shipped: { ar: "Shipped", en: "Shipped" },
      delivered: { ar: "Delivered", en: "Delivered" },
      cancelled: { ar: "Cancelled", en: "Cancelled" },
    };
    return map[s]?.[lang] || s;
  };

  const filtered = orders.filter((o) => {
    if (filter !== "all" && o.status !== filter) return false;
    if (search) {
      const q = search.toLowerCase();
      return (
        o.customer_name.toLowerCase().includes(q) ||
        o.customer_phone.toLowerCase().includes(q) ||
        o.id.toLowerCase().includes(q)
      );
    }
    return true;
  });

  const exportCsv = () => {
    const headers = ["Order ID", "Customer", "Phone", "City", "Address", "Total", "Shipping", "Payment", "Status", "Date"];
    const rows = filtered.map((o) => [
      o.id,
      o.customer_name,
      o.customer_phone,
      o.city,
      o.customer_address,
      o.total,
      o.shipping_cost,
      o.payment_method,
      o.status,
      new Date(o.created_at).toISOString(),
    ]);
    const csv = [headers, ...rows]
      .map((r) => r.map((c) => `"${String(c ?? "").replace(/"/g, '""')}"`).join(","))
      .join("\n");
    const blob = new Blob(["\uFEFF" + csv], { type: "text/csv;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `rouh-orders-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success(lang === "ar" ? "تم التصدير" : "Exported");
  };

  const printInvoice = async () => {
    if (!selected || printingInvoice) return;

    const w = window.open("", "_blank", "width=900,height=1000");
    if (!w) {
      toast.error(lang === "ar" ? "تعذر فتح نافذة الطباعة. اسمح بالنوافذ المنبثقة ثم أعد المحاولة." : "Unable to open the print window. Allow pop-ups and try again.");
      return;
    }

    setPrintingInvoice(true);
    w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>ROUH Invoice</title></head><body style="font-family:system-ui,sans-serif;padding:32px">${
      lang === "ar" ? "جاري تجهيز الفاتورة…" : "Preparing invoice…"
    }</body></html>`);
    w.document.close();

    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/orders/${selected.id}`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data?.message || (lang === "ar" ? "تعذر تحميل بيانات الفاتورة" : "Unable to load invoice data"));

      const order = (data?.order || selected) as any;
      const freshItems = Array.isArray(data?.items) ? data.items : [];
      if (freshItems.length === 0) {
        throw new Error(lang === "ar" ? "لا توجد أصناف مرتبطة بهذا الطلب." : "No items are associated with this order.");
      }

      setItems(freshItems as OrderItem[]);
      setOrderDetails(data);

      const escapeHtml = (value: unknown) => String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\"/g, "&quot;")
        .replace(/'/g, "&#39;");

      const money = (value: unknown) => Number(value || 0).toLocaleString();
      const itemRows = freshItems.map((item: any) => {
        const productName = item.product_name || item.product?.name_ar || item.product?.name || "-";
        const size = item.size || item.variant?.size_label || item.bottle_size_ml || "-";
        const quantity = Number(item.quantity || 0);
        const unitPrice = Number(item.unit_price ?? item.price ?? 0);
        const lineDiscount = Number(item.line_discount_amount ?? 0);
        const subtotal = Number(item.line_total ?? Math.max(0, unitPrice * quantity - lineDiscount));
        return `
          <tr>
            <td>${escapeHtml(productName)}</td>
            <td>${escapeHtml(size)}</td>
            <td style="text-align:center">${escapeHtml(quantity)}</td>
            <td style="text-align:right;white-space:nowrap">${escapeHtml(money(unitPrice))}</td>
            <td style="text-align:right;white-space:nowrap">${escapeHtml(money(subtotal))}</td>
          </tr>`;
      }).join("");

      const invoiceNumber = data?.invoice?.invoice_number || `ORDER-${String(order.id || selected.id).slice(0, 8)}`;
      const direction = lang === "ar" ? "rtl" : "ltr";
      const invoiceTitle = escapeHtml(`${lang === "ar" ? "فاتورة" : "Invoice"} ${invoiceNumber}`);
      const createdAt = escapeHtml(new Date(order.created_at || selected.created_at).toLocaleString());
      const customerName = escapeHtml(order.customer_name || selected.customer_name);
      const customerPhone = escapeHtml(order.customer_phone || selected.customer_phone);
      const city = escapeHtml(order.city || order.customer_city || selected.city);
      const paymentMethod = escapeHtml(order.payment_method || data?.invoice?.payment_method || selected.payment_method || "-");
      const address = escapeHtml(order.customer_address || selected.customer_address || "-");
      const safeLogo = escapeHtml(rouhLogo);
      const shipping = money(order.shipping_fee ?? order.shipping_cost ?? selected.shipping_cost);
      const subtotal = freshItems.reduce((sum: number, item: any) => {
        const qty = Number(item.quantity || 0);
        const unitPrice = Number(item.unit_price ?? item.price ?? 0);
        const discount = Number(item.line_discount_amount ?? 0);
        const lineTotal = Number(item.line_total ?? Math.max(0, unitPrice * qty - discount));
        return sum + Math.max(0, lineTotal);
      }, 0);
      const totalDiscount = freshItems.reduce((sum: number, item: any) => sum + Math.max(0, Number(item.line_discount_amount ?? 0)), 0);
      const total = money(order.total ?? selected.total);
      const discountRow = totalDiscount > 0.005
        ? `<div>${lang === "ar" ? "إجمالي الخصم" : "Total discount"}: ${escapeHtml(money(totalDiscount))} SYP</div>`
        : "";

      w.document.open();
      w.document.write(`<!doctype html><html dir="${direction}"><head><meta charset="utf-8"><title>${invoiceTitle}</title>
        <style>
          * { box-sizing: border-box; }
          body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; padding: 32px; color: #222; margin: 0; }
          h1 { color: #b8860b; margin: 0; font-size: 28px; }
          .brand { display:flex; justify-content:space-between; align-items:flex-start; gap:24px; border-bottom: 2px solid #b8860b; padding-bottom:16px; margin-bottom:24px; }
          .logo-bar { text-align:center; margin-bottom:16px; }
          .logo-bar img { max-height:150px; max-width:460px; width:auto; height:auto; object-fit:contain; display:block; margin:0 auto; }
          .info { display:grid; grid-template-columns:1fr 1fr; gap:8px 24px; margin-bottom:24px; font-size:14px; }
          .info strong { color:#666; font-weight:500; }
          table { width:100%; border-collapse:collapse; margin-bottom:16px; font-size:14px; }
          th, td { padding:10px; border-bottom:1px solid #eee; vertical-align:top; }
          th { background:#faf6ed; }
          .totals { margin-inline-start:auto; max-width:360px; text-align:right; font-size:14px; }
          .totals .grand { font-size:18px; font-weight:700; color:#b8860b; margin-top:8px; }
          .empty { padding:24px; text-align:center; border:1px dashed #ccc; }
          @media print { body { padding:0; } .no-print { display:none; } }
        </style></head><body>
        <div class="logo-bar"><img src="${safeLogo}" alt="Rouh" /></div>
        <div class="brand">
          <div><h1 style="font-size:18px;margin:0 0 4px">Rouh - روح</h1><div style="color:#888;font-size:13px">contact@rouh.shop</div></div>
          <div style="text-align:right"><div style="font-weight:bold">${lang === "ar" ? "فاتورة" : "INVOICE"}</div>
            <div style="font-family:monospace;color:#888">${escapeHtml(invoiceNumber)}</div>
            <div style="color:#888;font-size:12px">${createdAt}</div></div>
        </div>
        <div class="info">
          <div><strong>${lang === "ar" ? "العميل" : "Customer"}:</strong> ${customerName}</div>
          <div><strong>${lang === "ar" ? "رقم الهاتف" : "Phone"}:</strong> ${customerPhone}</div>
          <div><strong>${lang === "ar" ? "المدينة" : "City"}:</strong> ${city}</div>
          <div><strong>${lang === "ar" ? "الدفع" : "Payment"}:</strong> ${paymentMethod}</div>
          <div style="grid-column:1/-1"><strong>${lang === "ar" ? "العنوان" : "Address"}:</strong> ${address}</div>
        </div>
        <table><thead><tr>
          <th>${lang === "ar" ? "المنتج" : "Product"}</th>
          <th>${lang === "ar" ? "الحجم" : "Size"}</th>
          <th style="text-align:center">${lang === "ar" ? "الكمية" : "Qty"}</th>
          <th style="text-align:right">${lang === "ar" ? "السعر" : "Price"}</th>
          <th style="text-align:right">${lang === "ar" ? "الإجمالي" : "Subtotal"}</th>
        </tr></thead><tbody>${itemRows || `<tr><td class="empty" colspan="5">${lang === "ar" ? "لا توجد أصناف" : "No items"}</td></tr>`}</tbody></table>
        <div class="totals">
          <div>${lang === "ar" ? "إجمالي الأصناف" : "Items subtotal"}: ${escapeHtml(money(subtotal))} SYP</div>
          ${discountRow}
          <div>${lang === "ar" ? "الشحن" : "Shipping"}: ${escapeHtml(shipping)} SYP</div>
          <div class="grand">${lang === "ar" ? "الإجمالي" : "Total"}: ${escapeHtml(total)} SYP</div>
        </div>
        <script>window.onload=()=>{setTimeout(()=>window.print(),100);}</script>
        </body></html>`);
      w.document.close();
    } catch (error) {
      w.close();
      toast.error(error instanceof Error ? error.message : (lang === "ar" ? "تعذر تجهيز الفاتورة" : "Unable to prepare the invoice"));
    } finally {
      setPrintingInvoice(false);
    }
  };

  if (loading) return <div className="flex justify-center p-8"><div className="animate-spin h-8 w-8 border-2 border-primary border-t-transparent rounded-full" /></div>;

  return (
    <div className="p-6 space-y-6 space-x-2">
      {permissions.includes("orders.consumption.review") && adjustmentRequests.length > 0 && (
        <Card className="border-amber-200 bg-amber-50/40">
          <CardContent className="p-4 space-y-3">
            <div className="flex items-center justify-between gap-3"><div><div className="font-semibold">{lang === "ar" ? "طلبات تصحيح الاستهلاك" : "Consumption correction requests"}</div><div className="text-sm text-muted-foreground">{lang === "ar" ? "مراجعة تصحيحات الكميات التي طلبها الموظفون" : "Review quantity corrections requested by staff"}</div></div><Badge variant="outline">{adjustmentRequests.length}</Badge></div>
            <div className="space-y-2">{adjustmentRequests.map((r:any)=><div key={r.id} className="rounded-xl border bg-background p-3 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3"><div className="text-sm"><div className="font-medium">{r.item?.material?.name || "Material"}</div><div className="text-muted-foreground">{lang === "ar" ? "الكمية" : "Qty"}: {r.old_actual_qty} → {r.requested_actual_qty} · {lang === "ar" ? "السبب" : "Reason"}: {r.reason}</div><div className="text-xs text-muted-foreground">{r.requester?.name || r.requested_by} · {r.order_id}</div></div><div className="flex gap-2"><Button size="sm" onClick={()=>void reviewConsumptionCorrection(r.id,"approved")}><ClipboardCheck className="h-4 w-4 me-1"/>{lang === "ar" ? "موافقة" : "Approve"}</Button><Button size="sm" variant="outline" onClick={()=>void reviewConsumptionCorrection(r.id,"rejected")}>{lang === "ar" ? "رفض" : "Reject"}</Button></div></div>)}</div>
          </CardContent>
        </Card>
      )}
      <div className="flex flex-wrap items-center justify-between gap-3 mb-6 space-x-4">
        <h1 className="text-2xl font-display font-bold">{lang === "ar" ? "إدارة الطلبات" : "Manage Orders"}</h1>
        <div className="flex gap-2">
          {!isEmployee && <Button onClick={() => { createOrderRequestKeyRef.current = null; setShowCreateDialog(true); }} className="gap-2">
            <Plus className="h-4 w-4" />
            {lang === "ar" ? "إنشاء طلب جديد" : "Create New Order"}
          </Button>}
          {!isEmployee && <Button onClick={exportCsv} variant="outline" className="gap-2">
            <Download className="h-4 w-4" />
            {lang === "ar" ? "تصدير CSV" : "Export CSV"}
          </Button>}
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-3 mb-4">
        <div className="relative flex-1 min-w-[200px] max-w-md">
          <Search className="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
          <Input
            placeholder={lang === "ar" ? "ابحث بالاسم أو الهاتف..." : "Search by name or phone..."}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="ps-9"
          />
        </div>
        <Select value={filter} onValueChange={setFilter}>
          <SelectTrigger className="w-40">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{lang === "ar" ? "الكل" : "All"}</SelectItem>
            {statuses.map((s) => <SelectItem key={s} value={s}>{statusLabel(s)}</SelectItem>)}
          </SelectContent>
        </Select>
        <div className="text-sm text-muted-foreground">{filtered.length} / {orders.length}</div>
      </div>

      <div className="border rounded-lg overflow-x-auto">
        <Table className="min-w-[720px]">
          <TableHeader>
            <TableRow>
              <TableHead>{lang === "ar" ? "العميل" : "Customer"}</TableHead>
              <TableHead>{lang === "ar" ? "رقم الهاتف" : "Phone"}</TableHead>
              <TableHead>{lang === "ar" ? "المدينة" : "City"}</TableHead>
              {!isEmployee && <TableHead>{lang === "ar" ? "الإجمالي" : "Total"}</TableHead>}
              <TableHead>{lang === "ar" ? "الحالة" : "Status"}</TableHead>
              <TableHead>{lang === "ar" ? "الدفع" : "Payment"}</TableHead>
              <TableHead>{lang === "ar" ? "التاريخ" : "Date"}</TableHead>
              <TableHead>{lang === "ar" ? "التفاصيل" : "Details"}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {filtered.map((o) => (
              <TableRow key={o.id}>
                <TableCell className="font-medium">{o.customer_name}</TableCell>
                <TableCell>{o.customer_phone}</TableCell>
                <TableCell>{o.city}</TableCell>
                {!isEmployee && <TableCell>{Number(o.total || 0).toLocaleString()} SYP</TableCell>}
                <TableCell>
                  {permissions.includes('orders.edit') ? (
                    <Select value={o.order_status || o.status} onValueChange={(v) => updateStatus(o.id, v)}>
                      <SelectTrigger className="w-32">
                        <Badge className={statusColors[o.order_status || o.status] || ""}>{statusLabel(o.order_status || o.status)}</Badge>
                      </SelectTrigger>
                      <SelectContent>
                        {statuses.map((st) => <SelectItem key={st} value={st}>{statusLabel(st)}</SelectItem>)}
                      </SelectContent>
                    </Select>
                  ) : (
                    <Select value={o.status} onValueChange={(v) => updateStatus(o.id, v)}>
                      <SelectTrigger className="w-32">
                        <Badge className={statusColors[o.status] || ""}>{statusLabel(o.status)}</Badge>
                      </SelectTrigger>
                      <SelectContent>
                        {statuses.map((s) => <SelectItem key={s} value={s}>{statusLabel(s)}</SelectItem>)}
                      </SelectContent>
                    </Select>
                  )}
                </TableCell>
                <TableCell>
                  {permissions.includes("orders.edit") && Number(o.remaining_amount ?? 0) > 0 ? (
                    <Button size="sm" variant="outline" className="gap-1" onClick={() => openPaymentDialog(o)} title={lang === "ar" ? "تسجيل دفعة" : "Register payment"}>
                      {o.payment_status === "partial" ? (lang === "ar" ? "جزئي" : "Partial") : (lang === "ar" ? "غير مدفوع" : "Unpaid")}
                    </Button>
                  ) : (o.payment_status === "paid" ? (lang === "ar" ? "مدفوع" : "Paid") : o.payment_status === "partial" ? (lang === "ar" ? "جزئي" : "Partial") : (lang === "ar" ? "غير مدفوع" : "Unpaid"))}
                </TableCell>
                <TableCell className="text-muted-foreground text-sm">{new Date(o.created_at).toLocaleDateString()}</TableCell>
                <TableCell>
                  <div className="flex gap-2">
                <Button size="sm" variant="outline" onClick={() => openDetails(o)}>
                  <Eye className="h-4 w-4 mr-1" />
                  {lang === "ar" ? "عرض" : "View"}
                </Button>
                {permissions.includes('orders.edit') && (
                  <Button size="sm" variant="outline" onClick={() => openEditDialog(o)}>
                    <Pencil className="h-4 w-4 mr-1" />
                    {lang === "ar" ? "تعديل" : "Edit"}
                  </Button>
                )}
                {!isOrderPrepared(o) ? (
                  <Button size="sm" variant="secondary" onClick={() => { void openOrderPreparation(o); }}>
                    <PackageCheck className="h-4 w-4 mr-1" />
                    {lang === "ar" ? "تجهيز" : "Prepare"}
                  </Button>
                ) : (
                  <Button size="sm" variant="ghost" disabled className="text-muted-foreground">
                    <PackageCheck className="h-4 w-4 mr-1" />
                    {lang === "ar" ? "مجهز" : "Prepared"}
                  </Button>
                )}
                {!isEmployee && <Button size="sm" variant="outline" className="text-red-600 border-red-200 hover:bg-red-50" onClick={() => void deleteOrder(o)} disabled={deletingOrder}>
                  <Trash2 className="h-4 w-4 mr-1" />
                  {lang === "ar" ? "حذف" : "Delete"}
                </Button>}
              </div>
                </TableCell>
              </TableRow>
            ))}
            {filtered.length === 0 && <TableRow><TableCell colSpan={isEmployee ? 7 : 8} className="text-center py-8 text-muted-foreground">{lang === "ar" ? "لا توجد طلبات" : "No orders"}</TableCell></TableRow>}
          </TableBody>
        </Table>
      </div>

      <Dialog open={!!selected} onOpenChange={(open) => !open && setSelected(null)}>
        <DialogContent className="w-[calc(100vw-1rem)] sm:max-w-3xl max-h-[92vh] overflow-y-auto" onCloseAutoFocus={(event) => { event.preventDefault(); returnFocusRef.current?.focus(); }}>
          <DialogHeader>
            <div className="flex items-center justify-between gap-2 pe-6">
              <DialogTitle>{lang === "ar" ? "تفاصيل الطلب" : "Order Details"}</DialogTitle>
              <DialogDescription className="sr-only">{lang === "ar" ? "تفاصيل الطلب وحالة التجهيز" : "Order details and preparation status"}</DialogDescription>
              <div className="flex gap-2">
              <Button size="sm" variant="outline" onClick={printInvoice} className="gap-2" disabled={itemsLoading || printingInvoice}>
                <Printer className="h-4 w-4" />
                {printingInvoice ? (lang === "ar" ? "جاري تجهيز الفاتورة…" : "Preparing…") : (lang === "ar" ? "طباعة الفاتورة" : "Print Invoice")}
              </Button>
              {selected && permissions.includes("orders.edit") && (selected.payment_status !== "paid" || Number(selected.remaining_amount || 0) > 0) && (
                <Button size="sm" variant="outline" onClick={() => openPaymentDialog(selected)} className="gap-2">
                  {lang === "ar" ? "تسجيل دفعة" : "Register Payment"}
                </Button>
              )}
              {selected && !isEmployee && <Button size="sm" variant="outline" className="gap-2 text-red-600 border-red-200 hover:bg-red-50" onClick={() => void deleteOrder(selected)} disabled={deletingOrder}>
                <Trash2 className="h-4 w-4" />
                {lang === "ar" ? "حذف" : "Delete"}
              </Button>}
              </div>
            </div>
          </DialogHeader>
          {selected && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <span className="text-muted-foreground">{lang === "ar" ? "رقم الطلب:" : "Order ID:"}</span>{" "}
                  <span className="font-mono">{selected.id.slice(0, 8)}</span>
                </div>
                <div>
                  <span className="text-muted-foreground">{lang === "ar" ? "المصدر:" : "Source:"}</span>{" "}
                  <span>{selected.source === "offline" ? (lang === "ar" ? "Store / Manual" : "Store / Manual") : (lang === "ar" ? "Website" : "Website")}</span>
                </div>
                <div>
                  <span className="text-muted-foreground">{lang === "ar" ? "التاريخ:" : "Date:"}</span>{" "}
                  {new Date(selected.created_at).toLocaleString()}
                </div>
                <div>
                  <span className="text-muted-foreground">{lang === "ar" ? "العميل:" : "Customer:"}</span>{" "}
                  {selected.customer_name}
                </div>
                <div>
                  <span className="text-muted-foreground">{lang === "ar" ? "مرجع العميل:" : "Customer reference:"}</span>{" "}
                  {selected.customer_id
                    ? (() => {
                        const value = String(selected.customer_id);
                        return value.length > 8 ? `${value.slice(0, 8)}...` : value;
                      })()
                    : (lang === "ar" ? "-" : "-")}
                </div>
                <div>
                  <span className="text-muted-foreground">{lang === "ar" ? "الهاتف:" : "Phone:"}</span>{" "}
                  {selected.customer_phone}
                </div>
                <div>
                  <span className="text-muted-foreground">{lang === "ar" ? "المدينة:" : "City:"}</span>{" "}
                  {selected.city}
                </div>
                <div>
                  <span className="text-muted-foreground">{lang === "ar" ? "الدفع:" : "Payment:"}</span>{" "}
                  {selected.payment_method}
                </div>
                <div className="col-span-2">
                  <span className="text-muted-foreground">{lang === "ar" ? "العنوان:" : "Address:"}</span>{" "}
                  {selected.customer_address}
                </div>
                <div className="col-span-2">
                  <span className="text-muted-foreground">{lang === "ar" ? "ملاحظة سير العمل:" : "Workflow note:"}</span>{" "}
                  {selected.source === "online" || selected.source === "website" || selected.source === "web"
                    ? (lang === "ar" ? "طلب موقع - يحتاج قبول قبل التجهيز" : "Website order - requires acceptance before production")
                    : (lang === "ar" ? "طلب يدوي - جاهز للتجهيز" : "Manual order - ready for production")}
                </div>
                {canViewCosts && orderDetails?.cost_snapshot && (
                  <div className="col-span-2 rounded-lg border bg-muted/30 p-3 space-y-2">
                    <div className="font-semibold">{lang === "ar" ? "الربح / الخسارة" : "Profit / Loss"}</div>
                    <div className="grid grid-cols-2 gap-3 text-sm">
                      <div><span className="text-muted-foreground">{lang === "ar" ? "الإيراد:" : "Revenue:"}</span> {Number(orderDetails.cost_snapshot.revenue_subtotal || 0).toLocaleString()} SYP</div>
                      <div><span className="text-muted-foreground">{lang === "ar" ? "التكلفة:" : "COGS:"}</span> {Number(orderDetails.cost_snapshot.total_cogs || 0).toLocaleString()} SYP</div>
                      <div><span className="text-muted-foreground">{lang === "ar" ? "الربح:" : "Profit:"}</span> {Number(orderDetails.cost_snapshot.gross_profit || 0).toLocaleString()} SYP</div>
                      <div><span className="text-muted-foreground">{lang === "ar" ? "الصافي:" : "Net:"}</span> {Number(orderDetails.cost_snapshot.net_profit || 0).toLocaleString()} SYP</div>
                    </div>
                  </div>
                )}
                {orderDetails?.consumption?.items?.length > 0 && (
                  <div className="col-span-2 rounded-lg border bg-muted/30 p-3 space-y-2">
                    <div className="font-semibold">{lang === "ar" ? "تفاصيل الاستهلاك المؤكد" : "Confirmed consumption details"}</div>
                    <div className="space-y-1 text-sm">
                      {orderDetails.consumption.items.map((row: any, index: number) => (
                        <div key={index} className="flex justify-between gap-2 border-b pb-1">
                          <span>{row.material?.name || row.notes || (lang === "ar" ? "مادة" : "Material")}</span>
                          <span className="flex items-center gap-2"><span>{Number(row.actual_qty || 0).toLocaleString()} {row.unit || "pcs"}</span>{permissions.includes("orders.consumption.adjust") && <Button size="sm" variant="ghost" onClick={()=>void requestConsumptionCorrection(row)}>{lang === "ar" ? "طلب تصحيح" : "Request correction"}</Button>}</span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
                {selected.notes && (
                  <div className="col-span-2">
                    <span className="text-muted-foreground">{lang === "ar" ? "ملاحظات:" : "Notes:"}</span>{" "}
                    {selected.notes}
                  </div>
                )}
              </div>

              <div className="rounded-lg border bg-gradient-to-br from-primary/5 to-background p-4">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div>
                    <h3 className="font-semibold">{lang === "ar" ? "تجهيز الطلب" : "Order Preparation"}</h3>
                    <p className="text-sm text-muted-foreground mt-1">
                      {lang === "ar"
                        ? "التجهيز يتم من صفحة التجهيز الموحدة، حيث تُحمّل الوصفة ويتم التحقق من المخزون وتسجيل الاستهلاك."
                        : "Preparation is handled in the unified preparation page with recipe loading, stock validation and consumption tracking."}
                    </p>
                  </div>
                  {!isOrderPrepared(selected) && (
                    <Button onClick={() => selected && void openOrderPreparation(selected)} className="gap-2 shrink-0">
                      <PackageCheck className="h-4 w-4" />
                      {lang === "ar" ? "الانتقال إلى صفحة التجهيز" : "Open Preparation"}
                    </Button>
                  )}
                </div>
              </div>
              <div>
                <h3 className="font-semibold mb-2">{lang === "ar" ? "العناصر" : "Items"}</h3>
                {itemsLoading ? (
                  <div className="text-sm text-muted-foreground">{lang === "ar" ? "جاري التحميل..." : "Loading..."}</div>
                ) : (
                  <div className="border rounded-lg overflow-hidden">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>{lang === "ar" ? "المنتج" : "Product"}</TableHead>
                          <TableHead>{lang === "ar" ? "الحجم" : "Size"}</TableHead>
                          <TableHead>{lang === "ar" ? "الكمية" : "Qty"}</TableHead>
                          {!isEmployee && <TableHead>{lang === "ar" ? "سعر" : "Price"}</TableHead>}
                          {!isEmployee && <TableHead>{lang === "ar" ? "الإجمالي الفرعي" : "Subtotal"}</TableHead>}
                        </TableRow>
                      </TableHeader>
<TableBody>
                        {items.map((it) => (
                          <TableRow key={it.id}>
                            <TableCell className="font-medium">{it.product_name}</TableCell>
                            <TableCell>{it.size || "-"}</TableCell>
                            <TableCell>{it.quantity}</TableCell>
                            {!isEmployee && <TableCell>{Number(it.price || 0).toLocaleString()} SYP</TableCell>}
                            {!isEmployee && <TableCell>{(Number(it.price || 0) * it.quantity).toLocaleString()} SYP</TableCell>}
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>
                )}
              </div>

              {/* Step 1 - Prepared oils & packaging used (from oil_mix) */}
              {isOrderPrepared(selected) && items.some((it) => parseOilMix(it).length > 0) && (
                <div className="rounded-2xl border bg-gradient-to-br from-emerald-50/80 to-white p-4">
                  <div className="font-semibold text-sm mb-2">{lang === "ar" ? "🧪 الزيوت المستخدمة في التجهيز" : "🧪 Oils used in preparation"}</div>
                  <div className="space-y-3">
                    {items.map((it, idx) => {
                      const oilMix = parseOilMix(it);
                      const packaging = parsePackagingItems(it);
                      if (oilMix.length === 0) return null;
                      const oilName = (oil: any) => {
                        const found = prepOils.find((o) => String(o.id) === String(oil.oil_id));
                        return found ? (lang === "ar" ? found.name_ar || found.name : found.name) : oil.name || (lang === "ar" ? "زيت" : "Oil");
                      };
                      return (
                        <div key={idx} className="rounded-xl border bg-white/80 p-3">
                          <div className="flex items-start justify-between gap-3">
                            <div className="font-medium text-sm">{it.product_name || (lang === "ar" ? "عطر" : "Perfume")} · {it.size || "-"} · {it.quantity} {lang === "ar" ? "قطعة" : "pcs"}</div>
                          </div>
                          <div className="mt-2 space-y-1 text-sm">
                            {oilMix.map((oil, oi) => (
                              <div key={oi} className="flex justify-between gap-2 border-b pb-1">
                                <span>{oilName(oil)}</span>
                                <span className="text-muted-foreground">
                                  {Number(oil.grams || 0).toLocaleString()}{lang === "ar" ? " غرام" : " g"}
                                  {oil.percentage ? ` · ${Number(oil.percentage).toLocaleString()}%` : ""}
                                </span>
                              </div>
                            ))}
                            {packaging.length > 0 && (
                              <div className="pt-1 text-xs text-muted-foreground">
                                {lang === "ar" ? "التغليف:" : "Packaging:"}{" "}
                                {packaging.map((p, pi) => (
                                  <span key={pi} className="inline-block mr-2">
                                    {p.name || p.type} × {p.quantity}
                                  </span>
                                ))}
                              </div>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              )}

              {!isEmployee && <div className="border-t pt-4 space-y-1 text-sm">
                <div className="flex justify-between">
                  <span className="text-muted-foreground">{lang === "ar" ? "الشحن:" : "Shipping:"}</span>
                  <span>{Number(selected.shipping_cost || 0).toLocaleString()} SYP</span>
                </div>
                <div className="flex justify-between font-bold text-base">
                  <span>{lang === "ar" ? "المجموع:" : "Total:"}</span>
                  <span>{Number(selected.total || 0).toLocaleString()} SYP</span>
                </div>
              </div>}
            </div>
          )}
        </DialogContent>
      </Dialog>

      <Dialog open={paymentDialogOpen} onOpenChange={setPaymentDialogOpen}><DialogContent className="w-[calc(100vw-1rem)] sm:max-w-lg"><DialogHeader><DialogTitle>{lang === "ar" ? "تسجيل دفعة" : "Register Payment"}</DialogTitle><DialogDescription>{paymentOrder ? (lang === "ar" ? `الفاتورة: ${paymentOrder.id.slice(0,8)} — المتبقي: ${Number(paymentOrder.remaining_amount ?? (Number(paymentOrder.total||0)-Number(paymentOrder.paid_amount||0))).toLocaleString()} SYP` : `Order: ${paymentOrder.id.slice(0,8)} — Remaining: ${Number(paymentOrder.remaining_amount ?? (Number(paymentOrder.total||0)-Number(paymentOrder.paid_amount||0))).toLocaleString()} SYP`) : ""}</DialogDescription></DialogHeader><div className="space-y-4"><div className="grid sm:grid-cols-2 gap-3"><div><label className="text-sm font-medium">{lang === "ar" ? "المبلغ" : "Amount"}</label><Input type="text" inputMode="decimal" value={paymentAmount} onChange={e=>setPaymentAmount(e.target.value)} placeholder="0.00" /></div><div><label className="text-sm font-medium">{lang === "ar" ? "طريقة الدفع" : "Payment method"}</label><Select value={paymentMethod} onValueChange={setPaymentMethod}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="cash">{lang === "ar" ? "نقدي" : "Cash"}</SelectItem><SelectItem value="cash_on_delivery">{lang === "ar" ? "دفع عند الاستلام" : "Cash on delivery"}</SelectItem><SelectItem value="sham_cash">{lang === "ar" ? "شام كاش" : "Sham Cash"}</SelectItem><SelectItem value="bank_transfer">{lang === "ar" ? "حوالة بنكية" : "Bank transfer"}</SelectItem></SelectContent></Select></div></div><Textarea value={paymentNotes} onChange={e=>setPaymentNotes(e.target.value)} placeholder={lang === "ar" ? "ملاحظات الدفعة (اختياري)" : "Payment notes (optional)"} /></div><div className="flex justify-end gap-2"><Button variant="outline" onClick={()=>setPaymentDialogOpen(false)}>{lang === "ar" ? "إلغاء" : "Cancel"}</Button><Button onClick={()=>void registerPayment()} disabled={paymentSaving}>{paymentSaving ? (lang === "ar" ? "جارٍ الحفظ..." : "Saving...") : (lang === "ar" ? "تسجيل الدفعة" : "Record Payment")}</Button></div></DialogContent></Dialog>
<Dialog open={adjustmentDialogOpen} onOpenChange={setAdjustmentDialogOpen}><DialogContent className="w-[calc(100vw-1rem)] sm:max-w-lg"><DialogHeader><DialogTitle>{lang === "ar" ? "طلب تصحيح استهلاك" : "Consumption correction request"}</DialogTitle><DialogDescription>{lang === "ar" ? "اطلب تعديل الكمية الفعلية بعد تسجيل الاستهلاك. سيحتاج الطلب إلى موافقة المدير/المالك." : "Request a correction to actual consumption. A manager/owner must approve it."}</DialogDescription></DialogHeader><div className="space-y-4"><div className="rounded-lg border p-3 text-sm"><div className="font-medium">{adjustmentItem?.material?.name || adjustmentItem?.material_name || "Material"}</div><div className="text-muted-foreground">{lang === "ar" ? "الكمية الحالية" : "Current actual"}: {adjustmentItem?.actual_qty}</div></div><div className="space-y-2"><label className="text-sm font-medium">{lang === "ar" ? "الكمية الصحيحة" : "Correct actual quantity"}</label><Input type="text" inputMode="decimal" value={adjustmentQty} onChange={e=>setAdjustmentQty(e.target.value)}/></div><div className="space-y-2"><label className="text-sm font-medium">{lang === "ar" ? "سبب التعديل" : "Reason"}</label><Textarea rows={4} value={adjustmentReason} onChange={e=>setAdjustmentReason(e.target.value)} placeholder={lang === "ar" ? "مثال: تم استهلاك 32غ بدلاً من 30غ" : "Example: actual usage was 32g instead of 30g"}/></div></div><div className="flex justify-end gap-2"><Button variant="outline" onClick={()=>setAdjustmentDialogOpen(false)}>{lang === "ar" ? "إلغاء" : "Cancel"}</Button><Button onClick={()=>void submitConsumptionCorrection()}>{lang === "ar" ? "إرسال للمراجعة" : "Submit for review"}</Button></div></DialogContent></Dialog>

  <Dialog open={showEditDialog} onOpenChange={(open) => {
        setShowEditDialog(open);
        if (!open) { setEditingOrder(null); setEditItems([]); setSelected(null); setEditReason(""); }
      }}>
        <DialogContent className="w-[calc(100vw-1rem)] sm:max-w-4xl max-h-[92vh] overflow-y-auto" onCloseAutoFocus={(event) => { event.preventDefault(); returnFocusRef.current?.focus(); }}>
          <DialogHeader className="pe-8">
            <DialogTitle className="text-xl sm:text-2xl">{lang === "ar" ? "تعديل الطلب" : "Edit Order"}</DialogTitle>
            <DialogDescription>
              {isOrderPrepared(editingOrder) ? (lang === "ar" ? "هذا الطلب مجهز مسبقاً. تعديل الأصناف سيعكس الاستهلاك السابق ويفتح الطلب للتجهيز من جديد، مع الحفاظ على الأثر المالي وحركات المخزون." : "This order is already prepared. Changing its items will reverse the previous consumption and reopen preparation, while preserving financial and inventory audit history.") : (lang === "ar" ? "يمكن تعديل بيانات الطلب. إذا تم تعديل أصناف طلب مجهز، سيُعاد فتح التجهيز تلقائياً." : "You can edit the order. Changing items on a prepared order will automatically reopen preparation.")}
            </DialogDescription>
          </DialogHeader>

          {editingOrder && (
            <div className="space-y-5">
              {isOrderPrepared(editingOrder) ? (
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                  {lang === "ar" ? "هذا الطلب مجهز بالفعل. يمكنك تعديل الأصناف بصلاحية المدير أو المالك؛ سيتم عكس الاستهلاك السابق وإعادة فتح التجهيز تلقائياً." : "This order is already prepared. Authorized managers/owners can change its items; prior consumption will be reversed and preparation reopened automatically."}
                </div>
              ) : null}

              <section className="rounded-2xl border p-4 sm:p-5 space-y-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <h3 className="font-semibold">{lang === "ar" ? "بيانات العميل" : "Customer information"}</h3>
                    <p className="text-xs text-muted-foreground mt-1">{lang === "ar" ? "البيانات الأساسية للطلب" : "Basic order information"}</p>
                  </div>
                  <Badge variant="outline" className="font-mono">#{editingOrder.id.slice(0, 8)}</Badge>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-1.5"><label className="text-sm font-medium">{lang === "ar" ? "اسم العميل" : "Customer"}</label><Input value={editForm.customer_name} onChange={(e) => setEditForm({ ...editForm, customer_name: e.target.value })} /></div>
                  <div className="space-y-1.5"><label className="text-sm font-medium">{lang === "ar" ? "رقم الهاتف" : "Phone"}</label><Input value={editForm.customer_phone} onChange={(e) => setEditForm({ ...editForm, customer_phone: e.target.value })} /></div>
                  <div className="space-y-1.5"><label className="text-sm font-medium">{lang === "ar" ? "المدينة" : "City"}</label><Input value={editForm.city} onChange={(e) => setEditForm({ ...editForm, city: e.target.value })} /></div>
                  <div className="space-y-1.5"><label className="text-sm font-medium">{lang === "ar" ? "نوع التسليم" : "Delivery"}</label><Select value={editForm.delivery_type} onValueChange={(value) => setEditForm({ ...editForm, delivery_type: value })}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="delivery">{lang === "ar" ? "توصيل" : "Delivery"}</SelectItem><SelectItem value="pickup">{lang === "ar" ? "استلام" : "Pickup"}</SelectItem></SelectContent></Select></div>
                  <div className="sm:col-span-2 space-y-1.5"><label className="text-sm font-medium">{lang === "ar" ? "العنوان" : "Address"}</label><Textarea rows={2} value={editForm.customer_address} onChange={(e) => setEditForm({ ...editForm, customer_address: e.target.value })} /></div>
                </div>
              </section>

              <section className="rounded-2xl border p-4 sm:p-5 space-y-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <h3 className="font-semibold">{lang === "ar" ? "الأصناف المطلوبة" : "Requested items"}</h3>
                    <p className="text-xs text-muted-foreground mt-1">{lang === "ar" ? "اختر المنتج والحجم من الكتالوج ثم حدّد الكمية والسعر." : "Choose a product and size, then set quantity and price."}</p>
                  </div>
                  <Button size="sm" variant="outline" onClick={addEditItemRow} className="gap-2"><Plus className="h-4 w-4" />{lang === "ar" ? "إضافة صنف" : "Add item"}</Button>
                </div>
                <div className="space-y-3">
                  {editItems.map((item, index) => {
                    const matched = catalogItems.find((c) => c.name === item.product_name && (c.size || "") === (item.size || ""));
                    const selectedCatalogId = item.catalog_id || matched?.id || "";
                    const lineTotal = Math.max(0, Number(item.price || 0) * Number(item.quantity || 0) - Number(item.discount || 0));
                    return (
                      <div key={`${index}-${item.product_name}-${item.size}`} className="rounded-xl border bg-muted/10 p-3 sm:p-4">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_120px_140px_120px_48px] items-end">
                          <div className="space-y-1.5 sm:col-span-2 lg:col-span-1">
                            <label className="text-xs text-muted-foreground">{lang === "ar" ? "المنتج والحجم" : "Product & size"}</label>
                            <Select value={selectedCatalogId} onValueChange={(value) => handleEditItemSelection(index, value)} disabled={false}>
                              <SelectTrigger><SelectValue placeholder={lang === "ar" ? "اختر المنتج" : "Select product"} /></SelectTrigger>
                              <SelectContent className="max-h-80">
                                {catalogItems.map((c) => <SelectItem key={c.id} value={c.id}>{c.name} · {c.size}</SelectItem>)}
                              </SelectContent>
                            </Select>
                          </div>
                          <div className="space-y-1.5"><label className="text-xs text-muted-foreground">{lang === "ar" ? "الكمية" : "Qty"}</label><Input type="text" inputMode="decimal" min="1" step="1" value={String(item.quantity || 1)} disabled={false} onChange={(e) => updateEditItem(index, { quantity: e.target.value })} /></div>
                          <div className="space-y-1.5"><label className="text-xs text-muted-foreground">{lang === "ar" ? "سعر الوحدة" : "Unit price"}</label><Input type="text" inputMode="decimal" min="0" step="1" value={String(item.price || 0)} disabled={false} onChange={(e) => updateEditItem(index, { price: e.target.value })} /></div>
                          <div className="space-y-1.5"><label className="text-xs text-muted-foreground">{lang === "ar" ? "الخصم" : "Discount"}</label><Input type="text" inputMode="decimal" min="0" step="1" value={String(item.discount || 0)} disabled={false} onChange={(e) => updateEditItem(index, { discount: e.target.value })} /></div>
                          <div className="flex items-center gap-1">
                            <Button type="button" size="sm" variant={parseOilMix(item).length ? "default" : "outline"} onClick={() => openOilMixEditor("edit", index)} className="text-xs">{lang === "ar" ? (parseOilMix(item).length ? `ميكس ${parseOilMix(item).length}` : "ميكس زيوت") : (parseOilMix(item).length ? `Mix ${parseOilMix(item).length}` : "Oil mix")}</Button>
                            <Button type="button" size="icon" variant="ghost" className="text-destructive" onClick={() => removeEditItem(index)} disabled={isOrderPrepared(editingOrder) || editItems.length <= 1} aria-label={lang === "ar" ? "حذف الصنف" : "Remove item"}><Trash2 className="h-4 w-4" /></Button>
                          </div>
                        </div>
                        <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                          <span className="text-muted-foreground">{lang === "ar" ? "إجمالي السطر" : "Line total"}</span>
                          <span className="font-semibold">{lineTotal.toLocaleString()} SYP</span>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </section>

              <section className="rounded-2xl border p-4 sm:p-5 space-y-4">
                <h3 className="font-semibold">{lang === "ar" ? "الحالة والدفع" : "Status & payment"}</h3>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                  <div className="space-y-1.5"><label className="text-xs text-muted-foreground">{lang === "ar" ? "حالة الطلب" : "Order status"}</label><Select value={editForm.order_status} onValueChange={(value) => setEditForm({ ...editForm, order_status: value })}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="pending">{lang === "ar" ? "جديد" : "Pending"}</SelectItem><SelectItem value="confirmed">{lang === "ar" ? "مؤكد" : "Confirmed"}</SelectItem><SelectItem value="shipped">{lang === "ar" ? "تم الشحن" : "Shipped"}</SelectItem><SelectItem value="delivered">{lang === "ar" ? "تم التسليم" : "Delivered"}</SelectItem><SelectItem value="cancelled">{lang === "ar" ? "ملغى" : "Cancelled"}</SelectItem></SelectContent></Select></div>
                  <div className="space-y-1.5"><label className="text-xs text-muted-foreground">{lang === "ar" ? "طريقة الدفع" : "Payment method"}</label><Select value={editForm.payment_method} onValueChange={(value) => setEditForm({ ...editForm, payment_method: value })}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="cash">{lang === "ar" ? "نقدي" : "Cash"}</SelectItem><SelectItem value="bank_transfer">{lang === "ar" ? "تحويل بنكي" : "Bank transfer"}</SelectItem><SelectItem value="sham_cash">{lang === "ar" ? "شام كاش" : "Sham Cash"}</SelectItem><SelectItem value="cash_on_delivery">{lang === "ar" ? "دفع عند الاستلام" : "Cash on delivery"}</SelectItem></SelectContent></Select></div>
                  <div className="space-y-1.5"><label className="text-xs text-muted-foreground">{lang === "ar" ? "المبلغ المدفوع" : "Paid amount"}</label><Input type="text" inputMode="decimal" min="0" step="1" value={String(editForm.paid_amount)} onChange={(e) => setEditForm({ ...editForm, paid_amount: e.target.value as any })} /></div>
                  <div className="space-y-1.5"><label className="text-xs text-muted-foreground">{lang === "ar" ? "حالة الدفع" : "Payment status"}</label><Select value={editForm.payment_status} onValueChange={(value) => { const total = Number(editingOrder?.total || 0); const paid = value === 'paid' ? total : value === 'unpaid' ? 0 : Number(editForm.paid_amount || 0); setEditForm({ ...editForm, payment_status: value, paid_amount: paid }); }}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="unpaid">{lang === "ar" ? "غير مدفوع" : "Unpaid"}</SelectItem><SelectItem value="partial">{lang === "ar" ? "مدفوع جزئياً" : "Partially paid"}</SelectItem><SelectItem value="paid">{lang === "ar" ? "مدفوع" : "Paid"}</SelectItem></SelectContent></Select></div>
                  <div className="space-y-1.5 sm:col-span-2 lg:col-span-1"><label className="text-xs text-muted-foreground">{lang === "ar" ? "الشحن" : "Shipping"}</label><Input type="text" inputMode="decimal" min="0" step="1" value={String(editForm.shipping_fee)} onChange={(e) => setEditForm({ ...editForm, shipping_fee: e.target.value as any })} /></div>
                </div>
                {editForm.order_status === "cancelled" && <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900">{lang === "ar" ? "سيُطلب سبب الإلغاء قبل الحفظ وسيُسجل ضمن سجل العمليات." : "A cancellation reason is required before saving and will be recorded in the audit log."}</div>}
              </section>

              <section className="rounded-2xl border p-4 sm:p-5 space-y-2">
                <label className="text-sm font-medium">{lang === "ar" ? "ملاحظات الطلب" : "Order notes"}</label>
                <Textarea rows={4} value={editForm.notes} onChange={(e) => setEditForm({ ...editForm, notes: e.target.value })} placeholder={lang === "ar" ? "اكتب ملاحظات داخلية أو تشغيلية عن الطلب..." : "Add internal or operational notes about the order..."} />
              </section>

              <div className="rounded-xl bg-muted/30 border p-4 flex flex-wrap items-center justify-between gap-3">
                <div><div className="text-xs text-muted-foreground">{lang === "ar" ? "إجمالي الطلب الحالي" : "Current order total"}</div><div className="text-2xl font-bold">{Number(editingOrder.total || 0).toLocaleString()} SYP</div></div>
                <div className="text-sm text-muted-foreground">{lang === "ar" ? "تتحدث القيمة النهائية بعد حفظ التعديلات." : "The final total is recalculated when the changes are saved."}</div>
              </div>

              <div className="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-2 border-t pt-4">
                {!isEmployee && <Button variant="outline" className="text-destructive border-red-200 hover:bg-red-50" onClick={() => void deleteOrder(editingOrder)} disabled={deletingOrder}><Trash2 className="h-4 w-4 me-2" />{deletingOrder ? (lang === "ar" ? "جاري الحذف..." : "Deleting...") : (lang === "ar" ? "حذف الطلب" : "Delete order")}</Button>}
                <div className="flex flex-col-reverse sm:flex-row gap-2 sm:ms-auto">
                  <Button variant="outline" onClick={() => setShowEditDialog(false)}>{lang === "ar" ? "إلغاء" : "Cancel"}</Button>
                  <Button onClick={() => void updateOrder()} disabled={confirmingConsumption}>{confirmingConsumption ? (lang === "ar" ? "جاري الحفظ..." : "Saving...") : (lang === "ar" ? "حفظ التعديلات" : "Save changes")}</Button>
                </div>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>

      <Dialog open={createPickerIndex !== null} onOpenChange={(open) => { if (!open) closeCreatePicker(); }}>
        <DialogContent className="w-[calc(100vw-1rem)] sm:max-w-2xl max-h-[88vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{lang === "ar" ? "اختيار المنتج والحجم" : "Choose product & size"}</DialogTitle>
            <DialogDescription>{lang === "ar" ? "ابحث باسم المنتج ثم اختر الحجم مباشرة. هذا أسرع من القائمة الطويلة." : "Search by product name, then select the size directly."}</DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="relative"><Search className="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" /><Input autoFocus className="ps-9" placeholder={lang === "ar" ? "ابحث باسم المنتج أو الحجم..." : "Search product or size..."} value={createPickerSearch} onChange={(e) => setCreatePickerSearch(e.target.value)} /></div>
            <div className="space-y-2">
              {searchableCreateProducts.length === 0 ? (
                <div className="rounded-lg border p-8 text-center text-muted-foreground">{lang === "ar" ? "لا توجد نتائج" : "No products found"}</div>
              ) : searchableCreateProducts.map((group) => (
                <div key={group.productId} className="rounded-xl border p-3">
                  <div className="font-semibold mb-2">{group.nameAr || group.name}</div>
                  <div className="flex flex-wrap gap-2">
                    {group.items.map((catalogItem) => (
                      <Button key={catalogItem.id} type="button" variant={createPickerIndex !== null && String(createItems[createPickerIndex]?.catalog_id) === String(catalogItem.id) ? "default" : "outline"} size="sm" onClick={() => { if (createPickerIndex !== null) handleCreateItemSelection(createPickerIndex, String(catalogItem.id)); closeCreatePicker(); }}>
                        {catalogItem.size || (lang === "ar" ? "بدون حجم" : "No size")} · {Number(catalogItem.price || 0).toLocaleString()} SYP
                      </Button>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </div>
        </DialogContent>
      </Dialog>

      <Dialog open={showCreateDialog} onOpenChange={(open) => { if (!open) createOrderRequestKeyRef.current = null; setShowCreateDialog(open); }}>
        <DialogContent className="w-[calc(100vw-1rem)] sm:max-w-3xl max-h-[92vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{lang === "ar" ? "إنشاء طلب جديد" : "Create New Order"}</DialogTitle>
            <DialogDescription>{lang === "ar" ? "إنشاء طلب يدوي وإرساله إلى دورة التجهيز." : "Create a manual order and send it to the preparation workflow."}</DialogDescription>
          </DialogHeader>
          <div className="grid gap-4 md:grid-cols-2">
            <div className="md:col-span-2 rounded border bg-muted/30 p-3 text-sm text-muted-foreground">
              {lang === "ar" ? "يمكنك اختيار الأصناف مباشرة من المنتجات والمخزون وإضافة أكثر من صنف لنفس الطلب." : "You can pick items directly from the available products and inventory list, and add multiple items to the same order."}
            </div>
            <div className="md:col-span-2">
              <label className="mb-1 block text-sm text-muted-foreground">{lang === "ar" ? "نوع الطلب" : "Order type"}</label>
              <Select value={createForm.source} onValueChange={(value) => setCreateForm({ ...createForm, source: value })}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="offline">{lang === "ar" ? "طلب يدوي / متجر" : "Manual Store Order"}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <Input placeholder={lang === "ar" ? "اسم العميل" : "Customer name"} value={createForm.customer_name} onChange={(e) => setCreateForm({ ...createForm, customer_name: e.target.value })} />
            <Input placeholder={lang === "ar" ? "رقم الهاتف" : "Phone"} value={createForm.customer_phone} onChange={(e) => setCreateForm({ ...createForm, customer_phone: e.target.value })} />
            <Input placeholder={lang === "ar" ? "العنوان" : "Address"} value={createForm.customer_address} onChange={(e) => setCreateForm({ ...createForm, customer_address: e.target.value })} />
            <Input placeholder={lang === "ar" ? "المدينة" : "City"} value={createForm.city} onChange={(e) => setCreateForm({ ...createForm, city: e.target.value })} />
            <Input type="text" inputMode="decimal" placeholder={lang === "ar" ? "الشحن" : "Shipping"} value={String(createForm.shipping_fee)} onChange={(e) => setCreateForm({ ...createForm, shipping_fee: e.target.value as any })} />
            <Select value={createForm.payment_method} onValueChange={(value) => setCreateForm({ ...createForm, payment_method: value })}>
              <SelectTrigger><SelectValue placeholder={lang === "ar" ? "طريقة الدفع" : "Payment method"} /></SelectTrigger>
              <SelectContent>
                <SelectItem value="cash">{lang === "ar" ? "نقدي" : "Cash"}</SelectItem>
                <SelectItem value="cash_on_delivery">{lang === "ar" ? "دفع عند الاستلام" : "Cash on delivery"}</SelectItem>
                <SelectItem value="sham_cash">{lang === "ar" ? "شام كاش" : "Sham Cash"}</SelectItem>
                <SelectItem value="bank_transfer">{lang === "ar" ? "حوالة بنكية" : "Bank transfer"}</SelectItem>
              </SelectContent>
            </Select>
            <Select value={createForm.payment_status} onValueChange={(value) => setCreateForm({ ...createForm, payment_status: value })}>
              <SelectTrigger><SelectValue placeholder={lang === "ar" ? "حالة الدفع" : "Payment status"} /></SelectTrigger>
              <SelectContent>
                <SelectItem value="unpaid">{lang === "ar" ? "غير مدفوع" : "Unpaid"}</SelectItem>
                <SelectItem value="partial">{lang === "ar" ? "مدفوع جزئيًا" : "Partially paid"}</SelectItem>
                <SelectItem value="paid">{lang === "ar" ? "مدفوع بالكامل" : "Paid"}</SelectItem>
              </SelectContent>
            </Select>
            <Input type="text" inputMode="decimal" placeholder={lang === "ar" ? "المبلغ المدفوع" : "Paid amount"} value={String(createForm.paid_amount)} onChange={(e) => setCreateForm({ ...createForm, paid_amount: e.target.value as any })} />
            <Select value={createForm.delivery_type} onValueChange={(value) => setCreateForm({ ...createForm, delivery_type: value })}>
              <SelectTrigger><SelectValue placeholder={lang === "ar" ? "نوع التسليم" : "Delivery type"} /></SelectTrigger>
              <SelectContent>
                <SelectItem value="delivery">{lang === "ar" ? "توصيل" : "Delivery"}</SelectItem>
                <SelectItem value="store_pickup">{lang === "ar" ? "استلام من المتجر" : "Store pickup"}</SelectItem>
              </SelectContent>
            </Select>
            <Textarea className="md:col-span-2" placeholder={lang === "ar" ? "ملاحظات الطلب" : "Order notes"} value={createForm.order_notes} onChange={(e) => setCreateForm({ ...createForm, order_notes: e.target.value })} />
            <div className="md:col-span-2">
              <div className="mb-2 flex items-center justify-between">
                <label className="text-sm font-medium">{lang === "ar" ? "العناصر" : "Items"}</label>
                <Button type="button" size="sm" variant="outline" onClick={addCreateItemRow}>{lang === "ar" ? "إضافة صنف" : "Add item"}</Button>
              </div>
              {catalogLoading ? (
                <div className="text-sm text-muted-foreground">{lang === "ar" ? "جاري تحميل الأصناف..." : "Loading catalog..."}</div>
              ) : (
<div className="space-y-2">
                  {createItems.map((item, index) => {
                    const selectedCatalogItem = catalogItems.find((c) => String(c.id) === String(item.catalog_id));
                    return (
                      <div key={`${index}-${item.product_name}`} className="grid gap-2 rounded border p-2 md:grid-cols-5 items-center">
                        <Button type="button" variant="outline" className="justify-between text-start h-auto min-h-10 whitespace-normal" onClick={() => openCreatePicker(index)}>
                          <span className={selectedCatalogItem ? "text-foreground" : "text-muted-foreground"}>{selectedCatalogItem ? `${selectedCatalogItem.name}${selectedCatalogItem.size ? ` · ${selectedCatalogItem.size}` : ""}` : (lang === "ar" ? "اختيار المنتج والحجم" : "Choose product & size")}</span>
                          <ChevronRight className="h-4 w-4 shrink-0" />
                        </Button>
                        <Input placeholder={lang === "ar" ? "الحجم" : "Size"} value={item.size || ""} onChange={(e) => updateCreateItem(index, { size: e.target.value })} />
                        <Input inputMode="numeric" placeholder={lang === "ar" ? "الكمية" : "Qty"} value={String(item.quantity || 1)} onChange={(e) => updateCreateItem(index, { quantity: e.target.value as any })} />
                        <Input type="text" inputMode="decimal" placeholder={lang === "ar" ? "سعر" : "Price"} value={String(item.price || 0)} onChange={(e) => updateCreateItem(index, { price: e.target.value })} />
                        <div className="flex items-center gap-1">
                          <Button type="button" size="sm" variant={parseOilMix(item).length ? "default" : "outline"} onClick={() => openOilMixEditor("create", index)} className="text-xs">{lang === "ar" ? (parseOilMix(item).length ? `ميكس ${parseOilMix(item).length}` : "ميكس زيوت") : (parseOilMix(item).length ? `Mix ${parseOilMix(item).length}` : "Oil mix")}</Button>
                          <Button type="button" size="sm" variant="ghost" className="text-red-600 hover:bg-red-50" onClick={() => removeCreateItem(index)} disabled={createItems.length <= 1}>
                          <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          </div>
          <div className="mt-4 flex justify-end gap-2">
            <Button variant="outline" onClick={() => setShowCreateDialog(false)}>{lang === "ar" ? "إلغاء" : "Cancel"}</Button>
            <Button onClick={() => void createOfflineOrder()} disabled={createSubmitting || catalogLoading}>{createSubmitting ? (lang === "ar" ? "جاري إنشاء الطلب..." : "Creating...") : (lang === "ar" ? "إنشاء" : "Create")}</Button>
          </div>
        </DialogContent>
      </Dialog>

      <Dialog open={!!oilMixEditor} onOpenChange={(open) => { if (!open) setOilMixEditor(null); }}>
        <DialogContent className="w-[calc(100vw-1rem)] sm:max-w-xl">
          <DialogHeader>
            <DialogTitle>{lang === "ar" ? "ميكس الزيوت للطلب" : "Custom oil mix"}</DialogTitle>
            <DialogDescription>{lang === "ar" ? "حدد زيتين أو أكثر والكمية بالغرام لكل عبوة. النظام يحسب النسب والكحول المتبقي تلقائياً." : "Choose at least two oils and the grams per bottle. The system calculates percentages and remaining alcohol automatically."}</DialogDescription>
          </DialogHeader>
          <div className="space-y-3">
            {oilMixRows.map((row, index) => (
              <div key={index} className="grid grid-cols-[1fr_120px_40px] gap-2 items-center">
                <Select value={row.oil_id} onValueChange={(value) => setOilMixRows((prev) => prev.map((r, i) => i === index ? { ...r, oil_id: value } : r))}>
                  <SelectTrigger><SelectValue placeholder={lang === "ar" ? "اختر الزيت" : "Select oil"} /></SelectTrigger>
                  <SelectContent className="max-h-72">
                    {oilMixMaterials.map((oil) => <SelectItem key={oil.id} value={String(oil.id)}>{oil.name_ar || oil.name}</SelectItem>)}
                  </SelectContent>
                </Select>
                <Input inputMode="decimal" placeholder={lang === "ar" ? "غرام" : "Grams"} value={row.grams} onChange={(e) => setOilMixRows((prev) => prev.map((r, i) => i === index ? { ...r, grams: e.target.value.replace(/[^0-9.]/g, "") } : r))} />
                <Button type="button" size="icon" variant="ghost" className="text-destructive" onClick={() => setOilMixRows((prev) => prev.length > 2 ? prev.filter((_, i) => i !== index) : prev)}><Trash2 className="h-4 w-4" /></Button>
              </div>
            ))}
            <Button type="button" variant="outline" onClick={() => setOilMixRows((prev) => [...prev, { oil_id: "", grams: "" }])}><Plus className="h-4 w-4 me-2" />{lang === "ar" ? "إضافة زيت" : "Add oil"}</Button>
            <div className="rounded-lg border bg-muted/30 p-3 text-sm">
              <div className="flex justify-between"><span>{lang === "ar" ? "إجمالي الزيت/عبوة" : "Total oil / bottle"}</span><b>{oilMixRows.reduce((sum, row) => sum + Number(row.grams || 0), 0).toLocaleString()} g</b></div>
            </div>
          </div>
          <div className="flex justify-end gap-2 pt-2 border-t">
            <Button variant="outline" onClick={() => setOilMixEditor(null)}>{lang === "ar" ? "إلغاء" : "Cancel"}</Button>
            <Button onClick={saveOilMixEditor}>{lang === "ar" ? "حفظ الميكس" : "Save mix"}</Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default AdminOrders;

