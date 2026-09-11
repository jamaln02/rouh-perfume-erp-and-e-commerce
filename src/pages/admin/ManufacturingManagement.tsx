import { useCallback, useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useLanguage } from "@/hooks/useLanguage";
import { useAuth } from "@/hooks/useAuth";
import { withAuthHeaders } from "@/lib/auth";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from "@/components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Plus, Edit, Trash2, AlertTriangle, Package, Droplets,
  TrendingUp, DollarSign, Factory, ShoppingCart, Receipt,
  ArrowRight, Play, CheckCircle, XCircle, Settings, Search,
} from "lucide-react";

/* ------------------------------------------------------------------ */
/*  Types                                                              */
/* ------------------------------------------------------------------ */

interface Material {
  id: number;
  code: string;
  name: string;
  name_ar: string | null;
  material_category: string;
  subcategory: string | null;
  base_unit: string;
  current_stock: number | string;
  min_stock: number | string;
  avg_unit_cost: number | string;
  currency: string;
  is_active: boolean;
  supplier_name: string | null;
}

interface Product {
  id: string;
  name: string;
  name_ar: string | null;
}

interface ProductMapping {
  id: number;
  product_id: string;
  material_id: number;
  is_default: boolean;
  notes: string | null;
  product?: Product;
  material?: Material;
}

interface ProductionOrderItem {
  id: number;
  actual_qty: number;
  unit: string;
  material?: Material;
  orderItem?: { product_name: string; quantity: number };
}

interface ProductionOrder {
  id: number;
  order_id: string;
  status: string;
  labor_cost: number;
  electricity_cost: number;
  overhead_cost: number;
  total_material_cost: number;
  total_production_cost: number;
  order?: { customer_name: string | null };
  items?: ProductionOrderItem[];
}

interface Sale {
  id: number;
  product_name: string | null;
  customer_name: string | null;
  quantity: number;
  total_price: number;
  profit: number;
  sale_date: string | null;
}

interface FinancialData {
  revenue?: number;
  netProfit?: number;
  sales_by_source?: Array<{ count: number }>;
}

interface MaterialForm {
  code: string;
  name: string;
  name_ar: string;
  material_category: string;
  subcategory: string;
  base_unit: string;
  track_fractional: boolean;
  current_stock: string;
  min_stock: string;
  avg_unit_cost: string;
  currency: string;
  exchange_rate: string;
  supplier_name: string;
  is_active: boolean;
  notes: string;
}

interface MappingForm {
  product_id: string;
  material_id: string;
  is_default: boolean;
  notes: string;
}

interface MaterialUsageRow {
  material_id: string;
  actual_qty: string;
  is_packaging: boolean;
  is_optional: boolean;
}

interface ProductionForm {
  materials: MaterialUsageRow[];
  labor_cost: string;
  electricity_cost: string;
  overhead_cost: string;
  notes: string;
}

/* ------------------------------------------------------------------ */
/*  Helpers                                                            */
/* ------------------------------------------------------------------ */

const num = (v: number | string | null | undefined): number =>
  Number(v ?? 0) || 0;

const extractArray = (data: unknown): any[] =>
  Array.isArray(data) ? data : Array.isArray((data as any)?.data) ? (data as any).data : [];

/* ------------------------------------------------------------------ */
/*  Component                                                          */
/* ------------------------------------------------------------------ */

const ManufacturingManagement = () => {
  const { user } = useAuth();
  const canViewCosts = user?.role === "admin" || user?.role === "manager";
  const navigate = useNavigate();
  const { lang } = useLanguage();
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "");

  const ar = lang === "ar";

  const [activeTab, setActiveTab] = useState("materials");

  // Unified materials
  const [unifiedMaterials, setUnifiedMaterials] = useState<Material[]>([]);
  const [materialsLoading, setMaterialsLoading] = useState(false);
  const [materialsFilter, setMaterialsFilter] = useState("all");
  const [materialsSearch, setMaterialsSearch] = useState("");
  const [materialDialog, setMaterialDialog] = useState(false);
  const [editingMaterial, setEditingMaterial] = useState<Material | null>(null);
  const [materialForm, setMaterialForm] = useState<MaterialForm>({
    code: "",
    name: "",
    name_ar: "",
    material_category: "perfume_oil",
    subcategory: "general",
    base_unit: "g",
    track_fractional: true,
    current_stock: "0",
    min_stock: "0",
    avg_unit_cost: "0",
    currency: "SYP",
    exchange_rate: "1",
    supplier_name: "",
    is_active: true,
    notes: "",
  });

  // Sales & financial
  const [sales, setSales] = useState<Sale[]>([]);
  const [salesLoading, setSalesLoading] = useState(false);
  const [financial, setFinancial] = useState<FinancialData | null>(null);
  const [financialLoading, setFinancialLoading] = useState(false);

  // Production
  const [productionOrders, setProductionOrders] = useState<ProductionOrder[]>([]);
  const [productionLoading, setProductionLoading] = useState(false);
  const [selectedOrder, setSelectedOrder] = useState<ProductionOrder | null>(null);
  const [materialUsageDialog, setMaterialUsageDialog] = useState(false);
  const [productionForm, setProductionForm] = useState<ProductionForm>({
    materials: [],
    labor_cost: "0",
    electricity_cost: "0",
    overhead_cost: "0",
    notes: "",
  });

  // Product mappings
  const [productMappings, setProductMappings] = useState<ProductMapping[]>([]);
  const [availableMaterials, setAvailableMaterials] = useState<Material[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [mappingDialog, setMappingDialog] = useState(false);
  const [editingMapping, setEditingMapping] = useState<ProductMapping | null>(null);
  const [mappingForm, setMappingForm] = useState<MappingForm>({
    product_id: "",
    material_id: "",
    is_default: true,
    notes: "",
  });

  /* ------------------------- Data loading ------------------------- */

  const loadSales = useCallback(async () => {
    setSalesLoading(true);
    try {
      const res = await fetch(`${apiBaseUrl}/api/admin/inventory/sales`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      setSales(extractArray(await res.json()));
    } catch {
      setSales([]);
    } finally {
      setSalesLoading(false);
    }
  }, [apiBaseUrl]);

  const loadFinancial = useCallback(async () => {
    setFinancialLoading(true);
    try {
      const res = await fetch(`${apiBaseUrl}/api/admin/inventory/financial-dashboard`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      setFinancial(await res.json());
    } catch {
      setFinancial(null);
    } finally {
      setFinancialLoading(false);
    }
  }, [apiBaseUrl]);

  const loadProductionOrders = useCallback(async () => {
    setProductionLoading(true);
    try {
      const res = await fetch(`${apiBaseUrl}/api/admin/manufacturing/production-orders`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      setProductionOrders(extractArray(await res.json()));
    } catch {
      setProductionOrders([]);
    } finally {
      setProductionLoading(false);
    }
  }, [apiBaseUrl]);

  const loadProductMappings = useCallback(async () => {
    try {
      const res = await fetch(`${apiBaseUrl}/api/admin/manufacturing/product-mappings`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      setProductMappings(extractArray(await res.json()));
    } catch {
      setProductMappings([]);
    }
  }, [apiBaseUrl]);

  const loadAvailableMaterials = useCallback(async () => {
    try {
      const res = await fetch(`${apiBaseUrl}/api/admin/manufacturing/materials`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      setAvailableMaterials(extractArray(await res.json()));
    } catch {
      setAvailableMaterials([]);
    }
  }, [apiBaseUrl]);

  const loadProducts = useCallback(async () => {
    try {
      const res = await fetch(`${apiBaseUrl}/api/admin/products`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await res.json();
      setProducts(extractArray(data?.products ?? data));
    } catch {
      setProducts([]);
    }
  }, [apiBaseUrl]);

  const loadUnifiedMaterials = useCallback(async () => {
    setMaterialsLoading(true);
    try {
      const res = await fetch(`${apiBaseUrl}/api/admin/inventory/materials`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      setUnifiedMaterials(extractArray(await res.json()));
    } catch {
      setUnifiedMaterials([]);
    } finally {
      setMaterialsLoading(false);
    }
  }, [apiBaseUrl]);

  useEffect(() => {
    void loadSales();
    void loadFinancial();
    void loadProductionOrders();
    void loadProductMappings();
    void loadAvailableMaterials();
    void loadUnifiedMaterials();
    void loadProducts();
  }, [activeTab, loadSales, loadFinancial, loadProductionOrders, loadProductMappings, loadAvailableMaterials, loadUnifiedMaterials, loadProducts]);

  /* ------------------------- Derived data ------------------------- */

  const filteredMaterials = useMemo(() => {
    let list = unifiedMaterials;
    if (materialsFilter !== "all") {
      list = list.filter((m) => m.material_category === materialsFilter);
    }
    if (materialsSearch.trim()) {
      const q = materialsSearch.toLowerCase();
      list = list.filter(
        (m) =>
          m.name.toLowerCase().includes(q) ||
          (m.name_ar && m.name_ar.includes(materialsSearch)) ||
          (m.code && m.code.toLowerCase().includes(q)),
      );
    }
    return list;
  }, [unifiedMaterials, materialsFilter, materialsSearch]);

  const lowStockMaterials = useMemo(
    () => unifiedMaterials.filter((m) => m.is_active && num(m.current_stock) <= num(m.min_stock)),
    [unifiedMaterials],
  );

  const totalStockValue = useMemo(() => {
    return unifiedMaterials
      .filter((m) => m.is_active)
      .reduce((sum, m) => sum + num(m.avg_unit_cost) * num(m.current_stock), 0);
  }, [unifiedMaterials]);

  /* ------------------------- Material CRUD ------------------------- */

  const resetMaterialForm = () => {
    setMaterialForm({
      code: "", name: "", name_ar: "", material_category: "perfume_oil",
      subcategory: "general", base_unit: "g", track_fractional: true,
      current_stock: "0", min_stock: "0", avg_unit_cost: "0",
      currency: "SYP", exchange_rate: "1", supplier_name: "",
      is_active: true, notes: "",
    });
  };

  const handleMaterialSubmit = async () => {
    if (!materialForm.name.trim()) {
      toast.error(ar ? "الاسم مطلوب" : "Name is required");
      return;
    }
    const url = editingMaterial
      ? `${apiBaseUrl}/api/admin/inventory/materials/${editingMaterial.id}`
      : `${apiBaseUrl}/api/admin/inventory/materials`;
    const payload = {
      ...materialForm,
      current_stock: num(materialForm.current_stock),
      min_stock: num(materialForm.min_stock),
      avg_unit_cost: num(materialForm.avg_unit_cost),
      exchange_rate: num(materialForm.exchange_rate),
    };
    try {
      const res = await fetch(url, {
        method: editingMaterial ? "PATCH" : "POST",
        headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json" }),
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.error || data?.message || "Failed to save material");
      toast.success(editingMaterial ? (ar ? "تم تحديث المادة" : "Material updated") : (ar ? "تم إضافة المادة" : "Material added"));
      setMaterialDialog(false);
      setEditingMaterial(null);
      resetMaterialForm();
      void loadUnifiedMaterials();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
    }
  };

  const handleEditMaterial = (material: Material) => {
    setEditingMaterial(material);
    setMaterialForm({
      code: material.code || "",
      name: material.name || "",
      name_ar: material.name_ar || "",
      material_category: material.material_category || "perfume_oil",
      subcategory: material.subcategory || "general",
      base_unit: material.base_unit || "g",
      track_fractional: Boolean(material.is_active),
      current_stock: String(material.current_stock ?? "0"),
      min_stock: String(material.min_stock ?? "0"),
      avg_unit_cost: String(material.avg_unit_cost ?? "0"),
      currency: material.currency || "SYP",
      exchange_rate: "1",
      supplier_name: material.supplier_name || "",
      is_active: Boolean(material.is_active),
      notes: "",
    });
    setMaterialDialog(true);
  };

  const handleDeleteMaterial = async (id: number) => {
    if (!confirm(ar ? "هل أنت متأكد من حذف هذه المادة؟" : "Are you sure you want to delete this material?")) return;
    try {
      const res = await fetch(`${apiBaseUrl}/api/admin/inventory/materials/${id}`, {
        method: "DELETE",
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await res.json();
      if (res.ok) {
        toast.success(ar ? "تم حذف المادة" : "Material deleted");
        void loadUnifiedMaterials();
      } else {
        toast.error(data?.error || "Failed to delete material");
      }
    } catch (error) {
      toast.error(error instanceof Error ? error.message : "Error deleting material");
    }
  };

  /* ------------------------- Mapping CRUD ------------------------- */

  const handleMappingSave = async () => {
    if (!mappingForm.product_id || !mappingForm.material_id) {
      toast.error(ar ? "اختر المنتج والمادة" : "Select product and material");
      return;
    }
    const url = editingMapping
      ? `${apiBaseUrl}/api/admin/manufacturing/product-mappings/${editingMapping.id}`
      : `${apiBaseUrl}/api/admin/manufacturing/product-mappings`;
    try {
      const res = await fetch(url, {
        method: editingMapping ? "PATCH" : "POST",
        headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json" }),
        body: JSON.stringify(mappingForm),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.error || data?.message || "Failed to save mapping");
      toast.success(ar ? "تم حفظ الربط" : "Mapping saved");
      setMappingDialog(false);
      setEditingMapping(null);
      setMappingForm({ product_id: "", material_id: "", is_default: true, notes: "" });
      void loadProductMappings();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
    }
  };

  const openMappingEdit = (mapping: ProductMapping) => {
    setEditingMapping(mapping);
    setMappingForm({
      product_id: String(mapping.product_id || ""),
      material_id: String(mapping.material_id || ""),
      is_default: Boolean(mapping.is_default),
      notes: mapping.notes || "",
    });
    setMappingDialog(true);
  };

  const handleDeleteMapping = async (id: number) => {
    try {
      const res = await fetch(`${apiBaseUrl}/api/admin/manufacturing/product-mappings/${id}`, {
        method: "DELETE",
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      if (res.ok) {
        toast.success(ar ? "تم حذف الربط" : "Mapping deleted");
        void loadProductMappings();
      } else {
        toast.error(ar ? "فشل الحذف" : "Delete failed");
      }
    } catch {
      toast.error(ar ? "فشل الحذف" : "Delete failed");
    }
  };

  /* ------------------------- Production actions ------------------------- */

  const handleAddMaterialUsage = () => {
    setProductionForm((prev) => ({
      ...prev,
      materials: [...prev.materials, { material_id: "", actual_qty: "0", is_packaging: false, is_optional: false }],
    }));
  };

  const handleRemoveMaterialUsage = (index: number) => {
    setProductionForm((prev) => ({
      ...prev,
      materials: prev.materials.filter((_, i) => i !== index),
    }));
  };

  const handleMaterialUsageChange = (index: number, field: keyof MaterialUsageRow, value: string | boolean) => {
    setProductionForm((prev) => ({
      ...prev,
      materials: prev.materials.map((row, i) => (i === index ? { ...row, [field]: value } : row)),
    }));
  };

  const handleProductionDialog = (order: ProductionOrder) => {
    setSelectedOrder(order);
    setProductionForm({ materials: [], labor_cost: "0", electricity_cost: "0", overhead_cost: "0", notes: "" });
    setMaterialUsageDialog(true);
  };

  const handleCreateProductionOrder = async (orderId: string) => {
    try {
      const res = await fetch(`${apiBaseUrl}/api/admin/manufacturing/production-orders`, {
        method: "POST",
        headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json" }),
        body: JSON.stringify({ order_id: orderId }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.error || data?.message || "Failed to create production order");
      const newOrder = data.production_order ?? data;
      setSelectedOrder(newOrder);
      setProductionForm({ materials: [], labor_cost: "0", electricity_cost: "0", overhead_cost: "0", notes: "" });
      setMaterialUsageDialog(true);
      void loadProductionOrders();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
    }
  };

  const handleStartProduction = async (productionOrderId: number) => {
    try {
      const res = await fetch(`${apiBaseUrl}/api/admin/manufacturing/production-orders/${productionOrderId}/start`, {
        method: "POST",
        headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json" }),
        body: JSON.stringify({
          materials: productionForm.materials.filter((m) => m.material_id),
          labor_cost: num(productionForm.labor_cost),
          electricity_cost: num(productionForm.electricity_cost),
          overhead_cost: num(productionForm.overhead_cost),
          notes: productionForm.notes,
        }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.error || data?.message || "Failed to start production");
      toast.success(ar ? "تم بدء الإنتاج" : "Production started");
      setMaterialUsageDialog(false);
      void loadProductionOrders();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
    }
  };

  const handleCompleteProduction = async (productionOrderId: number) => {
    try {
      const res = await fetch(`${apiBaseUrl}/api/admin/manufacturing/production-orders/${productionOrderId}/complete`, {
        method: "POST",
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.error || data?.message || "Failed to complete production");
      toast.success(ar ? "تم إكمال الإنتاج" : "Production completed");
      void loadProductionOrders();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
    }
  };

  const handleCancelProduction = async (productionOrderId: number) => {
    if (!confirm(ar ? "هل تريد إلغاء هذا الإنتاج؟" : "Cancel this production?")) return;
    try {
      const res = await fetch(`${apiBaseUrl}/api/admin/manufacturing/production-orders/${productionOrderId}/cancel`, {
        method: "POST",
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.error || data?.message || "Failed to cancel production");
      toast.success(ar ? "تم إلغاء الإنتاج" : "Production cancelled");
      void loadProductionOrders();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
    }
  };

  /* ------------------------- Translations ------------------------- */

  const t = {
    title: ar ? "إدارة التصنيع الشاملة" : "Unified Manufacturing Management",
    subtitle: ar ? "نظام إدارة كامل للمواد الخام والإنتاج والمبيعات والماليات" : "Complete system for raw materials, production, sales, and finance",
    materials: ar ? "المواد الخام" : "Raw Materials",
    production: ar ? "الإنتاج" : "Production",
    productMappings: ar ? "ربط المنتجات" : "Product Mappings",
    sales: ar ? "المبيعات" : "Sales",
    lowStock: ar ? "مخزون منخفض" : "Low Stock",
    name: ar ? "الاسم" : "Name",
    nameAr: ar ? "الاسم بالعربية" : "Name (Arabic)",
    currentStock: ar ? "المخزون الحالي" : "Current Stock",
    minStock: ar ? "الحد الأدنى" : "Min Stock",
    supplier: ar ? "المورد" : "Supplier",
    notes: ar ? "ملاحظات" : "Notes",
    cancel: ar ? "إلغاء" : "Cancel",
    save: ar ? "حفظ" : "Save",
    edit: ar ? "تعديل" : "Edit",
    delete: ar ? "حذف" : "Delete",
    totalStockValue: ar ? "قيمة المخزون الإجمالية" : "Total Stock Value",
    totalSales: ar ? "إجمالي المبيعات" : "Total Sales",
    totalProfit: ar ? "إجمالي الأرباح" : "Total Profit",
    pendingOrders: ar ? "الطلبات المعلقة" : "Pending Orders",
    startProduction: ar ? "بدء الإنتاج" : "Start Production",
    completeProduction: ar ? "إكمال الإنتاج" : "Complete Production",
    cancelProduction: ar ? "إلغاء الإنتاج" : "Cancel Production",
    enterMaterials: ar ? "إدخال المواد المستخدمة" : "Enter Materials Used",
    addMaterial: ar ? "إضافة مادة" : "Add Material",
    material: ar ? "المادة" : "Material",
    quantity: ar ? "الكمية" : "Quantity",
    packaging: ar ? "تغليف" : "Packaging",
    optional: ar ? "اختياري" : "Optional",
    laborCost: ar ? "تكلفة العمالة" : "Labor Cost",
    electricityCost: ar ? "تكلفة الكهرباء" : "Electricity Cost",
    overheadCost: ar ? "تكلفة التشغيل" : "Overhead Cost",
    totalProductionCost: ar ? "إجمالي تكلفة الإنتاج" : "Total Production Cost",
    product: ar ? "المنتج" : "Product",
    internalMaterial: ar ? "المادة الداخلية" : "Internal Material",
    planned: ar ? "مخطط" : "Planned",
    inProgress: ar ? "قيد التنفيذ" : "In Progress",
    completed: ar ? "مكتمل" : "Completed",
    cancelled: ar ? "ملغي" : "Cancelled",
    productionOrders: ar ? "أوامر الإنتاج" : "Production Orders",
    noProductionOrders: ar ? "لا توجد أوامر إنتاج" : "No production orders",
    customerOrder: ar ? "طلب العميل" : "Customer Order",
    productionCost: ar ? "تكلفة الإنتاج" : "Production Cost",
    materialCost: ar ? "تكلفة المواد" : "Material Cost",
    unifiedMaterials: ar ? "المواد الموحدة" : "Unified Materials",
    allCategories: ar ? "جميع الفئات" : "All Categories",
    perfumeOil: ar ? "زيت عطر" : "Perfume Oil",
    alcohol: ar ? "كحول" : "Alcohol",
    packagingCat: ar ? "تغليف" : "Packaging",
    other: ar ? "أخرى" : "Other",
    search: ar ? "بحث" : "Search",
    editMaterial: ar ? "تعديل مادة" : "Edit Material",
    addMaterialTitle: ar ? "إضافة مادة جديدة" : "Add new material",
    materialCode: ar ? "رمز المادة" : "Material Code",
    subcategory: ar ? "الفئة الفرعية" : "Subcategory",
    baseUnit: ar ? "الوحدة الأساسية" : "Base Unit",
    trackFractional: ar ? "تتبع الكسور" : "Track Fractional",
    avgUnitCost: ar ? "متوسط تكلفة الوحدة" : "Avg Unit Cost",
    currency: ar ? "العملة" : "Currency",
    exchangeRate: ar ? "سعر الصرف" : "Exchange Rate",
    isActive: ar ? "نشط" : "Active",
    stock: ar ? "مخزون" : "Stock",
    cost: ar ? "تكلفة" : "Cost",
    category: ar ? "الفئة" : "Category",
    active: ar ? "نشط" : "Active",
    inactive: ar ? "غير نشط" : "Inactive",
    noMaterials: ar ? "لا توجد مواد" : "No materials found",
    noMappings: ar ? "لا توجد روابط منتجات" : "No product mappings found",
    noSales: ar ? "لا توجد مبيعات بعد" : "No sales yet",
    salesAppearHere: ar ? "المبيعات ستظهر هنا تلقائياً عند تجهيز الطلبات" : "Sales will appear here automatically when orders are prepared",
    salesRecords: ar ? "سجل المبيعات" : "Sales Records",
    salesDesc: ar ? "جميع المبيعات المسجلة من الطلبات المجهزة والإدخالات اليدوية" : "All sales recorded from prepared orders and manual entries",
    totalRevenue: ar ? "إجمالي الإيرادات (SYP)" : "Total Revenue (SYP)",
    totalProfitShort: ar ? "إجمالي الربح (SYP)" : "Total Profit (SYP)",
    salesCount: ar ? "عدد المبيعات" : "Sales Count",
    addMapping: ar ? "إضافة ربط" : "Add Mapping",
    currentMappings: ar ? "الروابط الحالية" : "Current Mappings",
    editMapping: ar ? "تعديل ربط المنتج" : "Edit Product Mapping",
    addMappingTitle: ar ? "إضافة ربط منتج" : "Add Product Mapping",
    mappingDesc: ar ? "حدد المنتج والمادة التي سيُستخدم منها عند التجهيز" : "Choose the product and the material used during preparation",
    selectProduct: ar ? "اختر المنتج" : "Select product",
    selectMaterial: ar ? "اختر المادة" : "Select material",
    setDefault: ar ? "اجعل هذا الربط افتراضياً" : "Set as default mapping",
    default: ar ? "افتراضي" : "Default",
    override: ar ? "بديل" : "Override",
    materialsUsed: ar ? "المواد المستخدمة" : "Materials Used",
    additionalCosts: ar ? "التكاليف الإضافية (اختياري)" : "Additional Costs (Optional)",
    productionNotes: ar ? "ملاحظات الإنتاج" : "Production notes",
    manageUnified: ar ? "إدارة المواد الخام الموحدة للتصنيع" : "Manage unified raw materials for manufacturing",
    manageProduction: ar ? "إدارة أوامر الإنتاج واستهلاك المواد" : "Manage production orders and material consumption",
    mapProducts: ar ? "ربط المنتجات بالمواد الداخلية للتصنيع" : "Map products to internal manufacturing materials",
    enterActualMaterials: ar ? "أدخل المواد والكميات الفعلية المستخدمة في الإنتاج" : "Enter actual materials and quantities used in production",
    editMaterialDesc: ar ? "تعديل مادة" : "Edit material",
    orders: ar ? "طلبات" : "orders",
  };

  /* ------------------------- Render ------------------------- */

  if (materialsLoading && unifiedMaterials.length === 0) {
    return (
      <div className="flex justify-center p-8">
        <div className="animate-spin h-8 w-8 border-2 border-primary border-t-transparent rounded-full" />
      </div>
    );
  }

  const statusBadge = (status: string) => {
    const map: Record<string, "default" | "secondary" | "outline" | "destructive"> = {
      completed: "default",
      in_progress: "secondary",
      planned: "outline",
    };
    const label = status === "completed" ? t.completed : status === "in_progress" ? t.inProgress : status === "planned" ? t.planned : t.cancelled;
    return <Badge variant={map[status] ?? "destructive"}>{label}</Badge>;
  };

  return (
    <div className="space-y-6 p-7">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-display font-bold">{t.title}</h1>
          <p className="text-muted-foreground text-sm">{t.subtitle}</p>
        </div>
        <Badge variant="outline" className="bg-green-50 text-green-700 border-green-200">
          <Factory className="h-4 w-4 mr-1" />
          {ar ? "نظام تصنيع العطور الموحد" : "Unified Perfume Manufacturing System"}
        </Badge>
      </div>

      {/* Overview Cards */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">{t.totalStockValue}</CardTitle>
            <Package className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{totalStockValue.toLocaleString()}</div>
            <p className="text-xs text-muted-foreground">SYP</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">{t.totalSales}</CardTitle>
            <DollarSign className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{financialLoading ? "..." : num(financial?.revenue).toLocaleString()}</div>
            <p className="text-xs text-muted-foreground">SYP</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">{t.totalProfit}</CardTitle>
            <TrendingUp className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className={`text-2xl font-bold ${financialLoading ? "" : num(financial?.netProfit) >= 0 ? "text-green-600" : "text-red-600"}`}>
              {financialLoading ? "..." : num(financial?.netProfit).toLocaleString()}
            </div>
            <p className="text-xs text-muted-foreground">SYP</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">{t.pendingOrders}</CardTitle>
            <ShoppingCart className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {financial ? num(financial.sales_by_source?.reduce((s, x) => s + num(x.count), 0)) : 0}
            </div>
            <p className="text-xs text-muted-foreground">{t.orders}</p>
          </CardContent>
        </Card>
      </div>

      {/* Low Stock Alerts */}
      {lowStockMaterials.length > 0 && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <div className="flex items-center gap-2 mb-2">
            <AlertTriangle className="h-5 w-5 text-red-600" />
            <span className="font-semibold text-red-800">{t.lowStock}</span>
          </div>
          <div className="flex flex-wrap gap-2">
            {lowStockMaterials.map((m) => (
              <Badge key={m.id} variant="destructive">
                {m.name_ar || m.name} ({num(m.current_stock)} {m.base_unit})
              </Badge>
            ))}
          </div>
        </div>
      )}

      {/* Tabs */}
      <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
        <TabsList className="grid w-full grid-cols-4">
          <TabsTrigger value="materials">
            <Droplets className="h-4 w-4 mr-2" />
            {t.materials}
          </TabsTrigger>
          <TabsTrigger value="production">
            <Factory className="h-4 w-4 mr-2" />
            {t.production}
          </TabsTrigger>
          <TabsTrigger value="mappings">
            <Settings className="h-4 w-4 mr-2" />
            {t.productMappings}
          </TabsTrigger>
          <TabsTrigger value="sales">
            <ShoppingCart className="h-4 w-4 mr-2" />
            {t.sales}
          </TabsTrigger>
        </TabsList>

        {/* Materials Tab */}
        <TabsContent value="materials" className="space-y-4">
          <div className="flex justify-between items-center">
            <div>
              <h2 className="text-lg font-semibold">{t.unifiedMaterials}</h2>
              <p className="text-sm text-muted-foreground">{t.manageUnified}</p>
            </div>
            <Button
              onClick={() => {
                setEditingMaterial(null);
                resetMaterialForm();
                setMaterialDialog(true);
              }}
            >
              <Plus className="h-4 w-4 mr-2" />
              {t.addMaterial}
            </Button>
          </div>

          {/* Filters */}
          <div className="flex gap-4 items-center flex-wrap">
            <div className="flex gap-2 flex-wrap">
              {[
                { v: "all", label: t.allCategories },
                { v: "perfume_oil", label: t.perfumeOil },
                { v: "alcohol", label: t.alcohol },
                { v: "packaging", label: t.packagingCat },
                { v: "other", label: t.other },
              ].map((cat) => (
                <Button
                  key={cat.v}
                  variant={materialsFilter === cat.v ? "default" : "outline"}
                  size="sm"
                  onClick={() => setMaterialsFilter(cat.v)}
                >
                  {cat.label}
                </Button>
              ))}
            </div>
            <div className="flex-1 relative min-w-[200px]">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder={t.search}
                value={materialsSearch}
                onChange={(e) => setMaterialsSearch(e.target.value)}
                className="pl-10"
              />
            </div>
          </div>

          {/* Materials List */}
          <Card>
            <CardContent className="p-0">
              {filteredMaterials.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground">{t.noMaterials}</div>
              ) : (
                <div className="divide-y">
                  {filteredMaterials.map((material) => (
                    <div key={material.id} className="p-4 hover:bg-muted/50 transition-colors">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                          <div
                            className={`w-3 h-3 rounded-full ${
                              material.material_category === "perfume_oil"
                                ? "bg-purple-500"
                                : material.material_category === "alcohol"
                                  ? "bg-blue-500"
                                  : material.material_category === "packaging"
                                    ? "bg-green-500"
                                    : "bg-gray-500"
                            }`}
                          />
                          <div>
                            <div className="font-semibold">{material.name}</div>
                            {material.name_ar && (
                              <div className="text-sm text-muted-foreground">{material.name_ar}</div>
                            )}
                            <div className="text-xs text-muted-foreground">
                              {material.code} • {material.subcategory}
                            </div>
                          </div>
                        </div>
                        <div className="flex items-center gap-4">
                          <div className="text-right">
                            <div className="text-sm">
                              <span className="text-muted-foreground">{t.stock}:</span>
                              <span
                                className={`font-medium ${
                                  num(material.current_stock) <= num(material.min_stock) ? "text-red-600" : ""
                                }`}
                              >
                                {" "}
                                {num(material.current_stock).toLocaleString()} {material.base_unit}
                              </span>
                            </div>
                            <div className="text-xs text-muted-foreground">
                              {canViewCosts && <>{t.cost}: {num(material.avg_unit_cost).toLocaleString()} {material.currency}/{material.base_unit}</>}
                            </div>
                          </div>
                          <Badge variant={material.is_active ? "default" : "secondary"}>
                            {material.is_active ? t.active : t.inactive}
                          </Badge>
                          <div className="flex gap-1">
                            <Button size="sm" variant="outline" onClick={() => handleEditMaterial(material)}>
                              <Edit className="h-3 w-3" />
                            </Button>
                            <Button size="sm" variant="destructive" onClick={() => void handleDeleteMaterial(material.id)}>
                              <Trash2 className="h-3 w-3" />
                            </Button>
                          </div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Production Tab */}
        <TabsContent value="production" className="space-y-4">
          <div className="flex justify-between items-center">
            <div>
              <h2 className="text-lg font-semibold">{t.production}</h2>
              <p className="text-sm text-muted-foreground">{t.manageProduction}</p>
            </div>
          </div>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Factory className="h-5 w-5" />
                {t.productionOrders}
              </CardTitle>
            </CardHeader>
            <CardContent>
              {productionLoading ? (
                <div className="flex justify-center p-4">
                  <div className="animate-spin h-6 w-6 border-2 border-primary border-t-transparent rounded-full" />
                </div>
              ) : productionOrders.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground">{t.noProductionOrders}</div>
              ) : (
                <div className="space-y-4">
                  {productionOrders.map((order) => (
                    <div key={order.id} className="border rounded-lg p-4 space-y-3">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                          {statusBadge(order.status)}
                          <span className="font-medium">
                            {t.customerOrder}: {order.order?.customer_name || "N/A"}
                          </span>
                        </div>
                        <div className="flex gap-2">
                          {order.status === "planned" && (
                            <Button size="sm" onClick={() => handleProductionDialog(order)}>
                              <Play className="h-4 w-4 mr-1" />
                              {t.startProduction}
                            </Button>
                          )}
                          {order.status === "in_progress" && (
                            <>
                              <Button size="sm" onClick={() => navigate(`/admin/orders?open=${encodeURIComponent(order.order_id)}`)}>
                                <CheckCircle className="h-4 w-4 mr-1" />
                                {ar ? "فتح الطلب للإكمال الآمن" : "Open order safely"}
                              </Button>
                              <Button size="sm" variant="destructive" onClick={() => void handleCancelProduction(order.id)}>
                                <XCircle className="h-4 w-4 mr-1" />
                                {t.cancelProduction}
                              </Button>
                            </>
                          )}
                        </div>
                      </div>

                      {order.items && order.items.length > 0 && (
                        <div className="space-y-2">
                          <h4 className="font-medium text-sm">{t.materialsUsed}:</h4>
                          {order.items.map((item) => (
                            <div key={item.id} className="flex items-center justify-between text-sm bg-muted/50 p-2 rounded">
                              <div className="flex items-center gap-2">
                                <Package className="h-4 w-4" />
                                <span>{item.material?.name}</span>
                                <Badge variant="outline">{item.material?.material_category}</Badge>
                              </div>
                              <div className="text-right">
                                <span className="font-medium">
                                  {item.actual_qty} {item.unit}
                                </span>
                                {canViewCosts && <span className="text-muted-foreground ml-2">
                                  ({num(item.actual_qty) * num(item.material?.avg_unit_cost)} SYP)
                                </span>}
                              </div>
                            </div>
                          ))}
                        </div>
                      )}

                      {canViewCosts && num(order.total_production_cost) > 0 && (
                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                          <div className="font-medium text-sm mb-2">{t.productionCost}:</div>
                          <div className="grid grid-cols-2 gap-2 text-sm">
                            <div>
                              <span className="text-muted-foreground">{t.materialCost}:</span>
                              <span className="font-medium"> {num(order.total_material_cost)} SYP</span>
                            </div>
                            <div>
                              <span className="text-muted-foreground">{t.laborCost}:</span>
                              <span className="font-medium"> {num(order.labor_cost)} SYP</span>
                            </div>
                            <div>
                              <span className="text-muted-foreground">{t.electricityCost}:</span>
                              <span className="font-medium"> {num(order.electricity_cost)} SYP</span>
                            </div>
                            <div>
                              <span className="text-muted-foreground">{t.overheadCost}:</span>
                              <span className="font-medium"> {num(order.overhead_cost)} SYP</span>
                            </div>
                          </div>
                          <div className="border-t border-blue-200 mt-2 pt-2 font-bold">
                            {t.totalProductionCost}: {num(order.total_production_cost)} SYP
                          </div>
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Product Mappings Tab */}
        <TabsContent value="mappings" className="space-y-4">
          <div className="flex justify-between items-center">
            <div>
              <h2 className="text-lg font-semibold">{t.productMappings}</h2>
              <p className="text-sm text-muted-foreground">{t.mapProducts}</p>
            </div>
            <Button
              onClick={() => {
                setEditingMapping(null);
                setMappingForm({ product_id: "", material_id: "", is_default: true, notes: "" });
                setMappingDialog(true);
              }}
            >
              <Plus className="h-4 w-4 mr-2" />
              {t.addMapping}
            </Button>
          </div>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Settings className="h-5 w-5" />
                {t.currentMappings}
              </CardTitle>
            </CardHeader>
            <CardContent>
              {productMappings.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground">{t.noMappings}</div>
              ) : (
                <div className="space-y-3">
                  {productMappings.map((mapping) => (
                    <div key={mapping.id} className="flex items-center justify-between border rounded-lg p-4">
                      <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2">
                          <Receipt className="h-5 w-5 text-blue-600" />
                          <div>
                            <div className="font-medium">{mapping.product?.name}</div>
                            <div className="text-sm text-muted-foreground">{t.product}</div>
                          </div>
                        </div>
                        <ArrowRight className="h-4 w-4 text-muted-foreground" />
                        <div className="flex items-center gap-2">
                          <Package className="h-5 w-5 text-green-600" />
                          <div>
                            <div className="font-medium">{mapping.material?.name}</div>
                            <div className="text-sm text-muted-foreground">{t.internalMaterial}</div>
                          </div>
                        </div>
                      </div>
                      <div className="flex items-center gap-2">
                        <Badge variant={mapping.is_default ? "default" : "outline"}>
                          {mapping.is_default ? t.default : t.override}
                        </Badge>
                        <Button size="sm" variant="outline" onClick={() => openMappingEdit(mapping)}>
                          <Edit className="h-4 w-4" />
                        </Button>
                        <Button size="sm" variant="destructive" onClick={() => void handleDeleteMapping(mapping.id)}>
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Sales Tab */}
        <TabsContent value="sales" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <ShoppingCart className="h-5 w-5" />
                {t.salesRecords}
              </CardTitle>
              <CardDescription>{t.salesDesc}</CardDescription>
            </CardHeader>
            <CardContent>
              {salesLoading ? (
                <div className="flex justify-center py-8">
                  <div className="animate-spin h-8 w-8 border-2 border-primary border-t-transparent rounded-full" />
                </div>
              ) : sales.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground">
                  <ShoppingCart className="h-12 w-12 mx-auto mb-4 opacity-50" />
                  <p className="font-medium">{t.noSales}</p>
                  <p className="text-sm mt-2">{t.salesAppearHere}</p>
                </div>
              ) : (
                <div className="space-y-3">
                  {/* Summary */}
                  <div className="grid grid-cols-3 gap-3 mb-4">
                    <div className="rounded-lg bg-primary/10 p-3 text-center">
                      <div className="text-2xl font-bold">
                        {financialLoading ? "..." : num(financial?.revenue).toLocaleString()}
                      </div>
                      <div className="text-xs text-muted-foreground">{t.totalRevenue}</div>
                    </div>
                    <div className="rounded-lg bg-green-50 p-3 text-center">
                      <div className="text-2xl font-bold text-green-600">
                        {financialLoading ? "..." : num(financial?.netProfit).toLocaleString()}
                      </div>
                      <div className="text-xs text-muted-foreground">{t.totalProfitShort}</div>
                    </div>
                    <div className="rounded-lg bg-blue-50 p-3 text-center">
                      <div className="text-2xl font-bold text-blue-600">{sales.length}</div>
                      <div className="text-xs text-muted-foreground">{t.salesCount}</div>
                    </div>
                  </div>
                  {/* Table */}
                  <div className="border rounded-lg overflow-x-auto">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="bg-muted/50">
                          <th className="text-start p-2">{t.product}</th>
                          <th className="text-start p-2">{ar ? "العميل" : "Customer"}</th>
                          <th className="text-end p-2">{t.quantity}</th>
                          <th className="text-end p-2">{ar ? "السعر" : "Price"}</th>
                          <th className="text-end p-2">{ar ? "الربح" : "Profit"}</th>
                          <th className="text-end p-2">{ar ? "التاريخ" : "Date"}</th>
                        </tr>
                      </thead>
                      <tbody>
                        {sales.map((sale) => (
                          <tr key={sale.id} className="border-t hover:bg-muted/30">
                            <td className="p-2 font-medium">{sale.product_name || t.product}</td>
                            <td className="p-2">{sale.customer_name || "-"}</td>
                            <td className="p-2 text-end">{num(sale.quantity).toLocaleString()}</td>
                            <td className="p-2 text-end">{num(sale.total_price).toLocaleString()} SYP</td>
                            <td className="p-2 text-end text-green-600">{num(sale.profit).toLocaleString()} SYP</td>
                            <td className="p-2 text-end text-muted-foreground">
                              {sale.sale_date ? new Date(sale.sale_date).toLocaleDateString() : "-"}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {/* Material Usage Dialog */}
      <Dialog open={materialUsageDialog} onOpenChange={setMaterialUsageDialog}>
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{t.enterMaterials}</DialogTitle>
            <DialogDescription>{t.enterActualMaterials}</DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
              <div className="font-medium">
                {t.customerOrder}: {selectedOrder?.order?.customer_name}
              </div>
              <div className="text-sm text-muted-foreground">
                {selectedOrder?.items
                  ?.map((item) => `${item.orderItem?.product_name} × ${item.orderItem?.quantity}`)
                  .join(", ")}
              </div>
            </div>

            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <h4 className="font-medium">{t.materialsUsed}</h4>
                <Button size="sm" onClick={handleAddMaterialUsage}>
                  <Plus className="h-4 w-4 mr-1" />
                  {t.addMaterial}
                </Button>
              </div>

              {productionForm.materials.map((mat, index) => (
                <div key={index} className="border rounded-lg p-3 space-y-3">
                  <div className="flex items-center gap-3">
                    <div className="flex-1">
                      <Label>{t.material}</Label>
                      <Select
                        value={mat.material_id}
                        onValueChange={(value) => handleMaterialUsageChange(index, "material_id", value)}
                      >
                        <SelectTrigger>
                          <SelectValue placeholder={t.selectMaterial} />
                        </SelectTrigger>
                        <SelectContent>
                          {availableMaterials.map((material) => (
                            <SelectItem key={material.id} value={material.id.toString()}>
                              {material.name} ({num(material.current_stock)} {material.base_unit})
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="w-32">
                      <Label>{t.quantity}</Label>
                      <Input
                        type="text"
                        inputMode="decimal"
                        value={mat.actual_qty}
                        onChange={(e) => handleMaterialUsageChange(index, "actual_qty", e.target.value)}
                        min="0"
                        step="0.01"
                      />
                    </div>
                    <Button size="sm" variant="destructive" onClick={() => handleRemoveMaterialUsage(index)}>
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                  <div className="flex gap-2">
                    <label className="flex items-center gap-2 text-sm">
                      <input
                        type="checkbox"
                        checked={mat.is_packaging}
                        onChange={(e) => handleMaterialUsageChange(index, "is_packaging", e.target.checked)}
                      />
                      {t.packaging}
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                      <input
                        type="checkbox"
                        checked={mat.is_optional}
                        onChange={(e) => handleMaterialUsageChange(index, "is_optional", e.target.checked)}
                      />
                      {t.optional}
                    </label>
                  </div>
                </div>
              ))}
            </div>

            <div className="border-t pt-4 space-y-3">
              <h4 className="font-medium">{t.additionalCosts}</h4>
              <div className="grid grid-cols-3 gap-3">
                <div>
                  <Label>{t.laborCost} (SYP)</Label>
                  <Input
                    type="text"
                    inputMode="decimal"
                    value={productionForm.labor_cost}
                    onChange={(e) => setProductionForm({ ...productionForm, labor_cost: e.target.value })}
                    min="0"
                  />
                </div>
                <div>
                  <Label>{t.electricityCost} (SYP)</Label>
                  <Input
                    type="text"
                    inputMode="decimal"
                    value={productionForm.electricity_cost}
                    onChange={(e) => setProductionForm({ ...productionForm, electricity_cost: e.target.value })}
                    min="0"
                  />
                </div>
                <div>
                  <Label>{t.overheadCost} (SYP)</Label>
                  <Input
                    type="text"
                    inputMode="decimal"
                    value={productionForm.overhead_cost}
                    onChange={(e) => setProductionForm({ ...productionForm, overhead_cost: e.target.value })}
                    min="0"
                  />
                </div>
              </div>
            </div>

            <div>
              <Label>{t.notes}</Label>
              <Textarea
                value={productionForm.notes}
                onChange={(e) => setProductionForm({ ...productionForm, notes: e.target.value })}
                placeholder={t.productionNotes}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setMaterialUsageDialog(false)}>
              {t.cancel}
            </Button>
            <Button onClick={() => selectedOrder && void handleStartProduction(selectedOrder.id)}>
              <Play className="h-4 w-4 mr-2" />
              {t.startProduction}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Material Dialog */}
      <Dialog
        open={materialDialog}
        onOpenChange={(open) => {
          setMaterialDialog(open);
          if (!open) setEditingMaterial(null);
        }}
      >
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{editingMaterial ? t.editMaterial : t.addMaterialTitle}</DialogTitle>
            <DialogDescription>{editingMaterial ? t.editMaterialDesc : t.addMaterialTitle}</DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label>{t.materialCode}</Label>
                <Input
                  value={materialForm.code}
                  onChange={(e) => setMaterialForm({ ...materialForm, code: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label>{t.name} *</Label>
                <Input
                  value={materialForm.name}
                  onChange={(e) => setMaterialForm({ ...materialForm, name: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label>{t.nameAr}</Label>
                <Input
                  value={materialForm.name_ar}
                  onChange={(e) => setMaterialForm({ ...materialForm, name_ar: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label>{t.category} *</Label>
                <Select
                  value={materialForm.material_category}
                  onValueChange={(value) => setMaterialForm({ ...materialForm, material_category: value })}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="perfume_oil">{t.perfumeOil}</SelectItem>
                    <SelectItem value="alcohol">{t.alcohol}</SelectItem>
                    <SelectItem value="packaging">{t.packagingCat}</SelectItem>
                    <SelectItem value="other">{t.other}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t.subcategory}</Label>
                <Input
                  value={materialForm.subcategory}
                  onChange={(e) => setMaterialForm({ ...materialForm, subcategory: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label>{t.baseUnit} *</Label>
                <Select
                  value={materialForm.base_unit}
                  onValueChange={(value) => setMaterialForm({ ...materialForm, base_unit: value })}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="g">g (gram)</SelectItem>
                    <SelectItem value="ml">ml (milliliter)</SelectItem>
                    <SelectItem value="pcs">pcs (pieces)</SelectItem>
                    <SelectItem value="kg">kg (kilogram)</SelectItem>
                    <SelectItem value="l">l (liter)</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t.trackFractional}</Label>
                <Checkbox
                  checked={materialForm.track_fractional}
                  onCheckedChange={(checked) =>
                    setMaterialForm({ ...materialForm, track_fractional: checked as boolean })
                  }
                />
              </div>
              <div className="space-y-2">
                <Label>{t.currentStock} *</Label>
                <Input
                  type="text"
                  inputMode="decimal"
                  value={materialForm.current_stock}
                  onChange={(e) => setMaterialForm({ ...materialForm, current_stock: e.target.value })}
                  min="0"
                  step="0.01"
                />
              </div>
              <div className="space-y-2">
                <Label>{t.minStock}</Label>
                <Input
                  type="text"
                  inputMode="decimal"
                  value={materialForm.min_stock}
                  onChange={(e) => setMaterialForm({ ...materialForm, min_stock: e.target.value })}
                  min="0"
                  step="0.01"
                />
              </div>
              <div className="space-y-2">
                <Label>{t.avgUnitCost} *</Label>
                <Input
                  type="text"
                  inputMode="decimal"
                  value={materialForm.avg_unit_cost}
                  onChange={(e) => setMaterialForm({ ...materialForm, avg_unit_cost: e.target.value })}
                  min="0"
                  step="0.01"
                />
              </div>
              <div className="space-y-2">
                <Label>{t.currency}</Label>
                <Input
                  value={materialForm.currency}
                  onChange={(e) => setMaterialForm({ ...materialForm, currency: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label>{t.exchangeRate}</Label>
                <Input
                  type="text"
                  inputMode="decimal"
                  value={materialForm.exchange_rate}
                  onChange={(e) => setMaterialForm({ ...materialForm, exchange_rate: e.target.value })}
                  min="0"
                  step="0.01"
                />
              </div>
              <div className="space-y-2">
                <Label>{t.supplier}</Label>
                <Input
                  value={materialForm.supplier_name}
                  onChange={(e) => setMaterialForm({ ...materialForm, supplier_name: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label>{t.isActive}</Label>
                <Checkbox
                  checked={materialForm.is_active}
                  onCheckedChange={(checked) => setMaterialForm({ ...materialForm, is_active: checked as boolean })}
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label>{t.notes}</Label>
              <Textarea
                value={materialForm.notes}
                onChange={(e) => setMaterialForm({ ...materialForm, notes: e.target.value })}
                placeholder={t.notes}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setMaterialDialog(false)}>
              {t.cancel}
            </Button>
            <Button onClick={() => void handleMaterialSubmit()}>{t.save}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Mapping Dialog */}
      <Dialog
        open={mappingDialog}
        onOpenChange={(open) => {
          setMappingDialog(open);
          if (!open) setEditingMapping(null);
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editingMapping ? t.editMapping : t.addMappingTitle}</DialogTitle>
            <DialogDescription>{t.mappingDesc}</DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>{t.product}</Label>
              <Select
                value={mappingForm.product_id}
                onValueChange={(v) => setMappingForm({ ...mappingForm, product_id: v })}
              >
                <SelectTrigger>
                  <SelectValue placeholder={t.selectProduct} />
                </SelectTrigger>
                <SelectContent>
                  {products.map((p) => (
                    <SelectItem key={p.id} value={String(p.id)}>
                      {p.name_ar || p.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{t.material}</Label>
              <Select
                value={mappingForm.material_id}
                onValueChange={(v) => setMappingForm({ ...mappingForm, material_id: v })}
              >
                <SelectTrigger>
                  <SelectValue placeholder={t.selectMaterial} />
                </SelectTrigger>
                <SelectContent>
                  {availableMaterials.map((m) => (
                    <SelectItem key={m.id} value={String(m.id)}>
                      {m.name_ar || m.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex items-center gap-3">
              <Checkbox
                checked={mappingForm.is_default}
                onCheckedChange={(checked) => setMappingForm({ ...mappingForm, is_default: checked === true })}
              />
              <Label>{t.setDefault}</Label>
            </div>
            <div className="space-y-2">
              <Label>{t.notes}</Label>
              <Textarea
                value={mappingForm.notes}
                onChange={(e) => setMappingForm({ ...mappingForm, notes: e.target.value })}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setMappingDialog(false)}>
              {t.cancel}
            </Button>
            <Button onClick={() => void handleMappingSave()}>{t.save}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default ManufacturingManagement;
