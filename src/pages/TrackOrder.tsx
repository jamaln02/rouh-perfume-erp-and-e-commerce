import { useCallback, useEffect, useState, type ElementType, type FormEvent } from "react";
import { useNavigate } from "react-router-dom";
import { useLanguage } from "@/hooks/useLanguage";
import { useAuth } from "@/hooks/useAuth";
import { withAuthHeaders } from "@/lib/auth";
import { toast } from "sonner";
import { motion } from "framer-motion";
import { Package, CheckCircle2, Truck, Clock, XCircle, Loader2, Pencil, Ban, ChevronDown, ChevronUp, Search, Plus, Trash2, UserRound } from "lucide-react";
import SEO from "@/components/SEO";

interface OrderListItem { id: string; customer_name?: string | null; city?: string | null; status?: string | null; order_status?: string | null; total?: number | string | null; created_at?: string | null; updated_at?: string | null; }
interface OrderItem { id?: string | number; product_id?: string | null; product_variant_id?: string | null; product_name?: string | null; size?: string | null; quantity?: number | string | null; price?: number | string | null; line_total?: number | string | null; }
interface OrderDetail extends OrderListItem { customer_phone?: string | null; customer_address?: string | null; notes?: string | null; shipping_cost?: number | string | null; discount_amount?: number | string | null; payment_method?: string | null; }
interface CatalogProduct { id: string; name: string; name_ar?: string | null; price: number | string; sizes?: string[] | string | null; size_prices?: Record<string, number> | string | null; }

const statusMeta: Record<string, { icon: ElementType; color: string; ar: string; en: string }> = {
  pending: { icon: Clock, color: "text-yellow-500", ar: "قيد المراجعة", en: "Pending" },
  confirmed: { icon: CheckCircle2, color: "text-blue-500", ar: "تم القبول", en: "Accepted" },
  processing: { icon: Loader2, color: "text-indigo-500", ar: "قيد التجهيز", en: "Processing" },
  shipped: { icon: Truck, color: "text-purple-500", ar: "تم الشحن", en: "Shipped" },
  delivered: { icon: CheckCircle2, color: "text-green-500", ar: "تم التسليم", en: "Delivered" },
  cancelled: { icon: XCircle, color: "text-red-500", ar: "ملغى", en: "Cancelled" },
};
const toNum = (v: unknown) => Number.isFinite(Number(v)) ? Number(v) : 0;
const effectiveStatus = (o: { status?: string | null; order_status?: string | null }) => (o.status || o.order_status || "pending").toLowerCase();

const TrackOrder = () => {
  const { lang } = useLanguage();
  const { user, loading: authLoading } = useAuth();
  const navigate = useNavigate();
  const api = String(import.meta.env.VITE_API_URL || "");
  const ar = lang === "ar";

  const [orders, setOrders] = useState<OrderListItem[]>([]);
  const [trackingOrderId, setTrackingOrderId] = useState("");
  const [trackingToken, setTrackingToken] = useState("");
  const [lookupLoading, setLookupLoading] = useState(false);
  const [expandedId, setExpandedId] = useState<string | null>(null);
  const [detail, setDetail] = useState<OrderDetail | null>(null);
  const [items, setItems] = useState<OrderItem[]>([]);
  const [loadingDetail, setLoadingDetail] = useState(false);
  const [editOpen, setEditOpen] = useState(false);
  const [catalog, setCatalog] = useState<CatalogProduct[]>([]);
  const [editForm, setEditForm] = useState({ customer_name: "", customer_phone: "", customer_address: "", city: "", notes: "" });
  const [editItems, setEditItems] = useState<OrderItem[]>([]);
  const [saving, setSaving] = useState(false);
  const [cancellingId, setCancellingId] = useState<string | null>(null);

  const loadOwnOrders = useCallback(async () => {
    if (!user) return;
    setLookupLoading(true);
    try {
      const response = await fetch(`${api}/api/customer/orders`, { headers: withAuthHeaders({ Accept: "application/json" }) });
      const data = await response.json();
      if (!response.ok || !data?.ok) throw new Error(data?.message || "Failed to load orders");
      setOrders(data.orders || []);
    } catch (e) { toast.error(e instanceof Error ? e.message : String(e)); }
    finally { setLookupLoading(false); }
  }, [api, user]);

  useEffect(() => { if (user) void loadOwnOrders(); }, [user, loadOwnOrders]);

  const searchOrders = async (e?: FormEvent) => {
    e?.preventDefault();
    if (!trackingOrderId.trim() || !trackingToken.trim()) {
      return toast.error(ar ? "أدخل رقم الطلب ورمز التتبع الموجود في رسالة تأكيد الطلب." : "Enter the order ID and the tracking token from your order confirmation.");
    }
    setLookupLoading(true);
    setExpandedId(null); setDetail(null); setItems([]);
    try {
      const response = await fetch(`${api}/api/track-orders`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        credentials: "include",
        body: JSON.stringify({ order_id: trackingOrderId.trim(), tracking_token: trackingToken.trim() }),
      });
      const data = await response.json();
      if (!response.ok || !data?.ok) throw new Error(data?.message || (ar ? "تعذر العثور على الطلب." : "Unable to find the order."));
      setOrders(data.orders || []);
    } catch (e) { toast.error(e instanceof Error ? e.message : String(e)); setOrders([]); }
    finally { setLookupLoading(false); }
  };

  const openOrder = async (id: string) => {
    if (!user) return;
    if (expandedId === id) { setExpandedId(null); setDetail(null); setItems([]); return; }
    setExpandedId(id); setLoadingDetail(true); setDetail(null); setItems([]);
    try {
      const response = await fetch(`${api}/api/customer/orders/${id}`, { headers: withAuthHeaders({ Accept: "application/json" }) });
      const data = await response.json();
      if (!response.ok || !data?.ok) throw new Error(data?.message || "Failed to load order details");
      setDetail(data.order); setItems(data.items || []);
    } catch (e) { toast.error(e instanceof Error ? e.message : String(e)); setExpandedId(null); }
    finally { setLoadingDetail(false); }
  };

  const loadCatalog = async () => {
    if (catalog.length) return;
    const response = await fetch(`${api}/api/products`, { headers: { Accept: "application/json" } });
    const data = await response.json();
    if (!response.ok) throw new Error("Failed to load products");
    setCatalog(data.products || []);
  };

  const startEdit = async () => {
    if (!detail || !user) return;
    try {
      await loadCatalog();
      setEditForm({ customer_name: detail.customer_name || "", customer_phone: detail.customer_phone || "", customer_address: detail.customer_address || "", city: detail.city || "", notes: detail.notes || "" });
      setEditItems(items.map(item => ({ ...item })));
      setEditOpen(true);
    } catch (e) { toast.error(e instanceof Error ? e.message : String(e)); }
  };

  const addItem = () => { const product = catalog[0]; const size = sizesFor(product)[0] || ""; setEditItems(prev => [...prev, { product_id: product?.id || "", size, quantity: 1 }]); };
  const updateItem = (index: number, patch: Partial<OrderItem>) => setEditItems(prev => prev.map((item, i) => i === index ? { ...item, ...patch } : item));
  const selectedProduct = (item: OrderItem) => catalog.find(p => p.id === item.product_id);
  const sizesFor = (product?: CatalogProduct) => {
    if (!product) return [];
    if (Array.isArray(product.sizes)) return product.sizes;
    if (typeof product.sizes === "string") { try { const parsed = JSON.parse(product.sizes); if (Array.isArray(parsed)) return parsed; } catch {} return product.sizes.split(",").map(s => s.trim()).filter(Boolean); }
    return [];
  };

  const submitEdit = async (e: FormEvent) => {
    e.preventDefault();
    if (!detail || !editItems.length) return toast.error(ar ? "يجب أن يحتوي الطلب على منتج واحد على الأقل." : "The order must contain at least one product.");
    if (editItems.some(i => !i.product_id || toNum(i.quantity) < 1)) return toast.error(ar ? "تحقق من المنتجات والكميات." : "Please check products and quantities.");
    setSaving(true);
    try {
      const response = await fetch(`${api}/api/customer/orders/${detail.id}`, { method: "PATCH", headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json" }), body: JSON.stringify({ ...editForm, customer_phone: editForm.customer_phone.replace(/[\s\-()]/g, "").trim(), items: editItems.map(i => ({ product_id: i.product_id, product_variant_id: i.product_variant_id || undefined, size: i.size || undefined, quantity: Number(i.quantity) })) }) });
      const data = await response.json();
      if (response.status === 409) throw new Error(ar ? "لا يمكن تعديل الطلب بعد قبول الإدارة له." : "This order can no longer be edited after admin acceptance.");
      if (!response.ok || !data?.ok) throw new Error(data?.message || "Failed to update order");
      setDetail(data.order); setItems(data.items || []); setEditOpen(false); toast.success(ar ? "تم تحديث الطلب بنجاح" : "Order updated successfully"); void loadOwnOrders();
    } catch (e) { toast.error(e instanceof Error ? e.message : String(e)); }
    finally { setSaving(false); }
  };

  const cancelOrder = async (id: string) => {
    if (!user) return navigate("/auth", { state: { redirectTo: "/track" } });
    if (!window.confirm(ar ? "هل أنت متأكد من إلغاء هذا الطلب؟ لا يمكن التراجع عن الإلغاء." : "Are you sure you want to cancel this order? This cannot be undone.")) return;
    setCancellingId(id);
    try {
      const response = await fetch(`${api}/api/customer/orders/${id}/cancel`, { method: "POST", headers: withAuthHeaders({ Accept: "application/json" }) });
      const data = await response.json();
      if (response.status === 409) throw new Error(ar ? "لا يمكن إلغاء الطلب بعد قبول الإدارة له." : "This order cannot be cancelled after admin acceptance.");
      if (!response.ok || !data?.ok) throw new Error(data?.message || "Failed to cancel order");
      toast.success(ar ? "تم إلغاء الطلب" : "Order cancelled");
      if (detail?.id === id) { setDetail(data.order); setEditOpen(false); }
      void loadOwnOrders();
    } catch (e) { toast.error(e instanceof Error ? e.message : String(e)); }
    finally { setCancellingId(null); }
  };

  const displayName = (p: CatalogProduct) => ar ? (p.name_ar || p.name) : p.name;
  const isOwnMode = Boolean(user);

  return <>
    <SEO title={ar ? "تتبع الطلب" : "Track Order"} description={ar ? "تتبع طلباتك وتعديلها قبل قبول الإدارة." : "Track your orders and edit them before admin acceptance."} path="/track" />
    <div className="min-h-screen pt-28 pb-16 px-4">
      <div className="max-w-4xl mx-auto space-y-6">
        <motion.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} className="text-center">
          <Package className="mx-auto h-10 w-10 text-primary mb-3" />
          <h1 className="font-display text-3xl font-bold">{ar ? "تتبع طلبك" : "Track your order"}</h1>
          <p className="text-muted-foreground mt-2">{ar ? "للزائر استخدم رقم الطلب ورمز التتبع السري من رسالة تأكيد الطلب. بعد تسجيل الدخول يمكنك رؤية طلباتك مباشرة." : "Guests use the order ID and private tracking token from the order confirmation. Signed-in customers can view their own orders directly."}</p>
        </motion.div>

        <form onSubmit={searchOrders} className="rounded-2xl border border-border bg-card p-4 shadow-sm">
          {!user && (
            <div className="grid grid-cols-1 sm:grid-cols-[1fr_1.4fr_auto] gap-3">
              <div><label className="sr-only" htmlFor="track-order-id">{ar ? "رقم الطلب" : "Order ID"}</label><input id="track-order-id" value={trackingOrderId} onChange={e => setTrackingOrderId(e.target.value)} dir="ltr" placeholder={ar ? "رقم الطلب" : "Order ID"} className="w-full h-11 rounded-xl border border-border bg-background px-4 outline-none focus:ring-2 focus:ring-primary/30" autoComplete="off" /></div>
              <div><label className="sr-only" htmlFor="track-token">{ar ? "رمز التتبع السري" : "Private tracking token"}</label><input id="track-token" value={trackingToken} onChange={e => setTrackingToken(e.target.value)} dir="ltr" placeholder={ar ? "رمز التتبع السري" : "Private tracking token"} className="w-full h-11 rounded-xl border border-border bg-background px-4 outline-none focus:ring-2 focus:ring-primary/30 font-mono" autoComplete="one-time-code" /></div>
              <button type="submit" disabled={lookupLoading} className="h-11 rounded-xl bg-primary text-primary-foreground px-6 font-medium inline-flex items-center justify-center gap-2">{lookupLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />}{ar ? "بحث" : "Search"}</button>
            </div>
          )}
          {user && <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"><UserRound className="h-4 w-4" />{ar ? `مسجل الدخول: ${user.email}` : `Signed in as ${user.email}`}<button type="button" onClick={() => void loadOwnOrders()} className="text-primary hover:underline">{ar ? "تحديث طلباتي" : "Refresh my orders"}</button></div>}
        </form>

        <div className="rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm text-foreground">
          <strong>{ar ? "تنبيه مهم:" : "Important:"}</strong> {ar ? "يمكن تعديل المنتجات أو الكميات أو إلغاء الطلب فقط قبل قبول الطلب من الإدارة. بعد القبول يصبح الطلب ثابتاً حتى لا يتأثر التجهيز والمخزون." : "Products, quantities and cancellation are available only before the order is accepted by the admin. After acceptance the order is locked so preparation and inventory remain consistent."}
        </div>

        {lookupLoading && !orders.length ? <div className="py-16 text-center text-muted-foreground"><Loader2 className="h-7 w-7 animate-spin mx-auto mb-3" />{ar ? "جاري البحث..." : "Searching..."}</div> : !orders.length ? <div className="rounded-2xl border border-dashed border-border p-12 text-center text-muted-foreground">{ar ? "أدخل بيانات التتبع أعلاه للعثور على طلبك." : "Enter your tracking details above to find your order."}</div> : <div className="space-y-4">
          {orders.map(order => {
            const status = effectiveStatus(order); const meta = statusMeta[status] || statusMeta.pending; const Icon = meta.icon; const own = Boolean(user) && (isOwnMode || order.id === detail?.id);
            const canEdit = own && status === "pending";
            return <motion.div key={order.id} initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} className="rounded-2xl border border-border bg-card overflow-hidden">
              <button type="button" onClick={() => void openOrder(order.id)} className="w-full text-start p-5 flex items-center gap-4 hover:bg-muted/30 transition-colors">
                <div className="h-11 w-11 rounded-xl bg-primary/10 flex items-center justify-center shrink-0"><Icon className={`h-5 w-5 ${meta.color} ${status === "processing" ? "animate-spin" : ""}`} /></div>
                <div className="min-w-0 flex-1"><div className="font-semibold truncate">{ar ? "طلب #" : "Order #"}{order.id.slice(0, 8)}</div><div className="text-sm text-muted-foreground">{order.created_at ? new Date(order.created_at).toLocaleString(ar ? "ar-SY" : "en-US") : "—"} {order.customer_name ? `· ${order.customer_name}` : ""}</div></div>
                <div className="text-end"><div className={`text-sm font-medium ${meta.color}`}>{ar ? meta.ar : meta.en}</div><div className="font-bold">{toNum(order.total).toLocaleString()} SYP</div></div>
                {user && (expandedId === order.id ? <ChevronUp /> : <ChevronDown />)}
              </button>

              {expandedId === order.id && user && <div className="border-t border-border p-5 space-y-5">
                {loadingDetail ? <div className="py-8 text-center"><Loader2 className="h-6 w-6 animate-spin mx-auto" /></div> : detail ? <>
                  <div className="space-y-3">{items.map((it, idx) => <div key={it.id ?? idx} className="flex items-center justify-between gap-3 border-b border-border/60 pb-3"><div><div className="font-medium">{it.product_name || "—"}</div><div className="text-xs text-muted-foreground">{it.size ? `${it.size} · ` : ""}x{toNum(it.quantity)}</div></div><div className="font-semibold whitespace-nowrap">{toNum(it.line_total).toLocaleString()} SYP</div></div>)}</div>
                  <div className="grid sm:grid-cols-2 gap-4 text-sm"><div><div className="text-xs text-muted-foreground">{ar ? "الهاتف" : "Phone"}</div><div dir="ltr">{detail.customer_phone || "—"}</div></div><div><div className="text-xs text-muted-foreground">{ar ? "المدينة" : "City"}</div><div>{detail.city || "—"}</div></div><div className="sm:col-span-2"><div className="text-xs text-muted-foreground">{ar ? "العنوان" : "Address"}</div><div>{detail.customer_address || "—"}</div></div></div>
                  {canEdit ? <div className="flex flex-wrap gap-2 pt-2"><button onClick={() => void startEdit()} className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-primary-foreground text-sm font-medium"><Pencil size={16} />{ar ? "تعديل الطلب" : "Edit order"}</button><button onClick={() => void cancelOrder(order.id)} disabled={cancellingId === order.id} className="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-red-500/40 text-red-600 text-sm font-medium">{cancellingId === order.id ? <Loader2 size={16} className="animate-spin" /> : <Ban size={16} />}{ar ? "إلغاء الطلب" : "Cancel order"}</button></div> : <p className="text-xs text-muted-foreground">{ar ? "هذا الطلب لم يعد قابلاً للتعديل أو الإلغاء بعد قبول الإدارة له." : "This order is locked because it has already been accepted by the admin."}</p>}
                </> : null}
              </div>}
            </motion.div>;
          })}
        </div>}
      </div>
    </div>

    {editOpen && detail && <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setEditOpen(false)}><div className="bg-card border border-border rounded-2xl p-6 w-full max-w-2xl max-h-[92vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
      <h2 className="font-display text-xl font-bold">{ar ? "تعديل الطلب" : "Edit order"}</h2>
      <p className="text-sm text-muted-foreground mt-1 mb-5">{ar ? "يمكنك تغيير المنتجات والكميات والعنوان قبل قبول الإدارة. الأسعار يعاد احتسابها تلقائياً." : "Change products, quantities and delivery details before admin acceptance. Prices are recalculated automatically."}</p>
      <form onSubmit={submitEdit} className="space-y-5">
        <div className="space-y-3">{editItems.map((item, index) => { const product = selectedProduct(item); const sizes = sizesFor(product); return <div key={index} className="rounded-xl border border-border p-3 grid gap-3 sm:grid-cols-[1fr_150px_90px_auto] items-end"><div><label className="text-xs text-muted-foreground">{ar ? "المنتج" : "Product"}</label><select value={item.product_id || ""} onChange={e => { const productId=e.target.value; const nextProduct=catalog.find(p=>p.id===productId); updateItem(index, { product_id: productId, product_variant_id: undefined, size: sizesFor(nextProduct)[0] || "" }); }} className="mt-1 w-full h-10 rounded-lg border border-border bg-background px-3">{catalog.map(p => <option key={p.id} value={p.id}>{displayName(p)}</option>)}</select></div><div><label className="text-xs text-muted-foreground">{ar ? "الحجم" : "Size"}</label><select value={item.size || ""} onChange={e => { const size=e.target.value; const variant=(catalog.find(p=>p.id===item.product_id) ? undefined : undefined); updateItem(index, { size, product_variant_id: variant }); }} className="mt-1 w-full h-10 rounded-lg border border-border bg-background px-3"><option value="">{ar ? "الافتراضي" : "Default"}</option>{sizes.map(size => <option key={size} value={size}>{size}</option>)}</select></div><div><label className="text-xs text-muted-foreground">{ar ? "الكمية" : "Qty"}</label><input type="number" min={1} max={100} value={item.quantity ?? 1} onChange={e => updateItem(index, { quantity: Number(e.target.value) })} className="mt-1 w-full h-10 rounded-lg border border-border bg-background px-3" /></div><button type="button" disabled={editItems.length === 1} onClick={() => setEditItems(prev => prev.filter((_, i) => i !== index))} className="h-10 px-3 rounded-lg border border-red-500/30 text-red-600 disabled:opacity-40"><Trash2 size={16} /></button></div>})}</div>
        <button type="button" onClick={addItem} className="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"><Plus size={16} />{ar ? "إضافة منتج" : "Add product"}</button>
        <div className="grid sm:grid-cols-2 gap-3"><input value={editForm.customer_name} onChange={e=>setEditForm({...editForm,customer_name:e.target.value})} placeholder={ar?"الاسم":"Name"} className="h-10 rounded-lg border border-border bg-background px-3" /><input value={editForm.customer_phone} onChange={e=>setEditForm({...editForm,customer_phone:e.target.value})} placeholder="+9639XXXXXXXX" dir="ltr" className="h-10 rounded-lg border border-border bg-background px-3" /><input value={editForm.customer_address} onChange={e=>setEditForm({...editForm,customer_address:e.target.value})} placeholder={ar?"العنوان":"Address"} className="h-10 rounded-lg border border-border bg-background px-3 sm:col-span-2" /><input value={editForm.city} onChange={e=>setEditForm({...editForm,city:e.target.value})} placeholder={ar?"المدينة":"City"} className="h-10 rounded-lg border border-border bg-background px-3" /><textarea value={editForm.notes} onChange={e=>setEditForm({...editForm,notes:e.target.value})} placeholder={ar?"ملاحظات":"Notes"} className="min-h-20 rounded-lg border border-border bg-background px-3 py-2" /></div>
        <div className="flex gap-2"><button type="submit" disabled={saving} className="flex-1 h-11 rounded-xl bg-primary text-primary-foreground font-medium">{saving ? (ar ? "جارٍ الحفظ..." : "Saving...") : (ar ? "حفظ التعديلات" : "Save changes")}</button><button type="button" onClick={()=>setEditOpen(false)} className="px-5 h-11 rounded-xl border border-border">{ar ? "إغلاق" : "Close"}</button></div>
      </form>
    </div></div>}
  </>;
};
export default TrackOrder;
