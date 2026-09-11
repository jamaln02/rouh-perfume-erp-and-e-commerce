import { useCallback, useEffect, useState } from "react";
import { useLanguage } from "@/hooks/useLanguage";
import { withAuthHeaders } from "@/lib/auth";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Plus, Trash2, Users, User as UserIcon, History } from "lucide-react";
import { toast } from "sonner";

interface Coupon {
  id: string;
  code: string;
  discount_percent: number;
  max_uses: number | null;
  used_count: number;
  expires_at: string | null;
  active: boolean;
  assigned_to_user_id: string | null;
  assigned_to_phone: string | null;
  per_user_limit: number;
}

interface QuizDiscount {
  id: string;
  phone: string;
  customer_name: string | null;
  user_id: string | null;
  discount_code: string;
  discount_percent: number;
  recommended_product: string;
  recommended_product_ar: string;
  used: boolean;
  created_at: string;
}

interface Redemption {
  id: string;
  source: string;
  code: string;
  user_id: string | null;
  phone: string | null;
  order_id: string | null;
  discount_amount: number;
  discount_percent: number;
  redeemed_at: string;
}

const empty = {
  code: "",
  discount_percent: 10,
  max_uses: "",
  expires_at: "",
  active: true,
  assignment: "global" as "global" | "user" | "phone",
  assigned_to_user_id: "",
  assigned_to_phone: "",
  per_user_limit: 1,
};

const AdminCoupons = () => {
  const { lang } = useLanguage();
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "");
  const [rows, setRows] = useState<Coupon[]>([]);
  const [quizDiscounts, setQuizDiscounts] = useState<QuizDiscount[]>([]);
  const [loading, setLoading] = useState(true);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState<typeof empty>(empty);
  const [redemptions, setRedemptions] = useState<Redemption[]>([]);
  const [viewCoupon, setViewCoupon] = useState<Coupon | null>(null);

  const load = useCallback(async () => {
    const response = await fetch(`${apiBaseUrl}/api/admin/coupons`, { headers: withAuthHeaders({ Accept: "application/json" }), credentials: "include" });
    const data = await response.json();
    setRows((data?.coupons as Coupon[]) || []);
    setQuizDiscounts((data?.quiz_discounts as QuizDiscount[]) || []);
    setLoading(false);
  }, [apiBaseUrl]);
  useEffect(() => { void load(); }, [load]);

  const save = async () => {
    if (!form.code) return toast.error(lang === "ar" ? "أدخل الكود" : "Enter code");
    if (form.assignment === "user" && !form.assigned_to_user_id) return toast.error(lang === "ar" ? "اختر المستخدم" : "Select a user");
    if (form.assignment === "phone" && (!form.assigned_to_phone || form.assigned_to_phone.length < 6)) return toast.error(lang === "ar" ? "أدخل رقم هاتف صحيح" : "Enter a valid phone");
    const payload = {
      code: form.code.toUpperCase(),
      discount_percent: Number(form.discount_percent),
      max_uses: form.max_uses ? Number(form.max_uses) : null,
      expires_at: form.expires_at || null,
      active: form.active,
      assigned_to_user_id: form.assignment === "user" ? form.assigned_to_user_id : null,
      assigned_to_phone: form.assignment === "phone" ? form.assigned_to_phone.trim() : null,
      per_user_limit: Math.max(1, Number(form.per_user_limit) || 1),
    };
    const response = await fetch(`${apiBaseUrl}/api/admin/coupons`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        ...withAuthHeaders(),
      },
      credentials: "include",
      body: JSON.stringify(payload),
    });
    const data = await response.json();
    if (!response.ok || !data?.ok) return toast.error(data?.message || "Failed");
    toast.success(lang === "ar" ? "تم الإنشاء" : "Created");
    setOpen(false); setForm(empty); load();
  };

  const toggle = async (c: Coupon) => {
    await fetch(`${apiBaseUrl}/api/admin/coupons/${c.id}/toggle`, {
      method: "PATCH",
      headers: withAuthHeaders({ Accept: "application/json" }),
      credentials: "include",
    });
    load();
  };
  const del = async (id: string) => {
    if (!confirm(lang === "ar" ? "حذف الكوبون؟" : "Delete coupon?")) return;
    await fetch(`${apiBaseUrl}/api/admin/coupons/${id}`, {
      method: "DELETE",
      headers: withAuthHeaders({ Accept: "application/json" }),
      credentials: "include",
    });
    load();
  };
  const openRedemptions = async (c: Coupon) => {
    setViewCoupon(c);
    const response = await fetch(`${apiBaseUrl}/api/admin/coupons/${c.id}/redemptions`, {
      headers: withAuthHeaders({ Accept: "application/json" }),
      credentials: "include",
    });
    const data = await response.json();
    setRedemptions((data?.redemptions as Redemption[]) || []);
  };
  if (loading) return <div className="flex justify-center p-8"><div className="animate-spin h-8 w-8 border-2 border-primary border-t-transparent rounded-full" /></div>;

  const inp = "w-full bg-background border border-border rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-primary/40";

  return (
    <div className="p-6 space-y-6 space-x-2">
      <div className="flex justify-between items-center mb-6 p-4">
        <h1 className="text-2xl font-display font-bold">{lang === "ar" ? "كوبونات الخصم" : "Coupons"}</h1>
        <Button onClick={() => setOpen(true)}><Plus className="h-4 w-4 mr-1" /> {lang === "ar" ? "جديد" : "New"}</Button>
      </div>
      <div className="border rounded-lg overflow-x-auto">
        <Table className="min-w-[820px]">
          <TableHeader>
            <TableRow>
              <TableHead>{lang === "ar" ? "الكود" : "Code"}</TableHead>
              <TableHead>{lang === "ar" ? "الخصم" : "Discount"}</TableHead>
              <TableHead>{lang === "ar" ? "النوع" : "Type"}</TableHead>
              <TableHead>{lang === "ar" ? "لكل مستخدم" : "Per user"}</TableHead>
              <TableHead>{lang === "ar" ? "الاستخدام" : "Uses"}</TableHead>
              <TableHead>{lang === "ar" ? "ينتهي" : "Expires"}</TableHead>
              <TableHead>{lang === "ar" ? "الحالة" : "Status"}</TableHead>
              <TableHead></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((c) => (
              <TableRow key={c.id}>
                <TableCell className="font-mono font-bold">{c.code}</TableCell>
                <TableCell>{c.discount_percent}%</TableCell>
                <TableCell>
                  {c.assigned_to_user_id ? (
                    <Badge variant="outline" className="gap-1"><UserIcon className="h-3 w-3" />{lang === "ar" ? "مستخدم" : "User"}</Badge>
                  ) : c.assigned_to_phone ? (
                    <Badge variant="outline" className="gap-1"><UserIcon className="h-3 w-3" />{c.assigned_to_phone}</Badge>
                  ) : (
                    <Badge variant="secondary" className="gap-1"><Users className="h-3 w-3" />{lang === "ar" ? "عام" : "Global"}</Badge>
                  )}
                </TableCell>
                <TableCell className="text-center">{c.per_user_limit}</TableCell>
                <TableCell>{c.used_count}{c.max_uses ? ` / ${c.max_uses}` : ""}</TableCell>
                <TableCell className="text-xs text-muted-foreground">{c.expires_at ? new Date(c.expires_at).toLocaleDateString() : "-"}</TableCell>
                <TableCell>
                  <Badge className={c.active ? "bg-green-500/20 text-green-600 cursor-pointer" : "bg-muted text-muted-foreground cursor-pointer"} onClick={() => toggle(c)}>
                    {c.active ? (lang === "ar" ? "نشط" : "Active") : (lang === "ar" ? "معطل" : "Disabled")}
                  </Badge>
                </TableCell>
                <TableCell>
                  <div className="flex gap-2 justify-end">
                    <Button size="sm" variant="outline" onClick={() => openRedemptions(c)} title={lang === "ar" ? "سجل الاستخدام" : "Redemptions"}><History className="h-3 w-3" /></Button>
                    <Button size="sm" variant="destructive" onClick={() => del(c.id)}><Trash2 className="h-3 w-3" /></Button>
                  </div>
                </TableCell>
              </TableRow>
            ))}
            {rows.length === 0 && <TableRow><TableCell colSpan={8} className="text-center py-8 text-muted-foreground">{lang === "ar" ? "لا توجد كوبونات" : "No coupons"}</TableCell></TableRow>}
          </TableBody>
        </Table>
      </div>

      <div className="space-y-3">
        <div className="flex items-center justify-between p-2">
          <div>
            <h2 className="text-xl font-display font-bold">{lang === "ar" ? "أكواد اختبار العطر" : "Perfume Quiz Codes"}</h2>
            <p className="text-xs text-muted-foreground mt-1">{lang === "ar" ? "هذه الأكواد مرتبطة بالعميل ولمرة استخدام واحدة فقط." : "These codes are customer-bound and can be redeemed only once."}</p>
          </div>
          <Badge variant="outline">{quizDiscounts.length}</Badge>
        </div>
        <div className="border rounded-lg overflow-x-auto">
          <Table className="min-w-[980px]">
            <TableHeader>
              <TableRow>
                <TableHead>{lang === "ar" ? "العميل" : "Customer"}</TableHead>
                <TableHead>{lang === "ar" ? "الهاتف" : "Phone"}</TableHead>
                <TableHead>{lang === "ar" ? "الكود" : "Code"}</TableHead>
                <TableHead>{lang === "ar" ? "الخصم" : "Discount"}</TableHead>
                <TableHead>{lang === "ar" ? "العطر الموصى" : "Recommended perfume"}</TableHead>
                <TableHead>{lang === "ar" ? "الحالة" : "Status"}</TableHead>
                <TableHead>{lang === "ar" ? "التاريخ" : "Created"}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {quizDiscounts.map((q) => (
                <TableRow key={q.id}>
                  <TableCell className="font-medium">{q.customer_name || "-"}</TableCell>
                  <TableCell className="font-mono text-xs">{q.phone}</TableCell>
                  <TableCell className="font-mono font-bold">{q.discount_code}</TableCell>
                  <TableCell>{q.discount_percent}%</TableCell>
                  <TableCell>{lang === "ar" ? (q.recommended_product_ar || q.recommended_product) : q.recommended_product}</TableCell>
                  <TableCell><Badge className={q.used ? "bg-muted text-muted-foreground" : "bg-green-500/20 text-green-600"}>{q.used ? (lang === "ar" ? "مستخدم" : "Used") : (lang === "ar" ? "جاهز - مرة واحدة" : "Unused - one use")}</Badge></TableCell>
                  <TableCell className="text-xs text-muted-foreground">{new Date(q.created_at).toLocaleDateString()}</TableCell>
                </TableRow>
              ))}
              {quizDiscounts.length === 0 && <TableRow><TableCell colSpan={7} className="text-center py-8 text-muted-foreground">{lang === "ar" ? "لا توجد أكواد اختبار عطر بعد" : "No perfume quiz codes yet"}</TableCell></TableRow>}
            </TableBody>
          </Table>
        </div>
      </div>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto">
          <DialogHeader><DialogTitle>{lang === "ar" ? "كوبون جديد" : "New Coupon"}</DialogTitle></DialogHeader>
          <div className="space-y-3">
            <input className={inp} placeholder={lang === "ar" ? "الكود (مثال: WELCOME10)" : "Code (e.g. WELCOME10)"} value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} />
<input className={inp} type="text" inputMode="decimal" min={1} max={100} placeholder={lang === "ar" ? "نسبة الخصم %" : "Discount %"} value={String(form.discount_percent)} onChange={(e) => setForm({ ...form, discount_percent: e.target.value as any })} />

            <div className="space-y-2 border border-border rounded-lg p-3">
              <label className="text-sm font-medium">{lang === "ar" ? "نوع الكوبون" : "Coupon type"}</label>
              <div className="grid grid-cols-3 gap-2">
{([
                  { v: "global", ar: "Global", en: "Global" },
                  { v: "user", ar: "مستخدم مسجل", en: "Registered user" },
                  { v: "phone", ar: "رقم هاتف", en: "Phone number" },
                ] as const).map((o) => (
                  <button key={o.v} type="button"
                    onClick={() => setForm({ ...form, assignment: o.v })}
                    className={`px-2 py-2 text-xs rounded-lg border transition ${form.assignment === o.v ? "border-primary bg-primary/10 text-primary" : "border-border hover:border-primary/40"}`}>
                    {lang === "ar" ? o.ar : o.en}
                  </button>
                ))}
              </div>
              {form.assignment === "user" && (
                <input
                  className={inp}
                  placeholder={lang === "ar" ? "معرّف المستخدم" : "User ID"}
                  value={form.assigned_to_user_id}
                  onChange={(e) => setForm({ ...form, assigned_to_user_id: e.target.value })}
                />
              )}
              {form.assignment === "phone" && (
                <input className={inp} placeholder={lang === "ar" ? "الهاتف (مثال: 09xxxxxxxx)" : "Phone (e.g. 09xxxxxxxx)"} value={form.assigned_to_phone} onChange={(e) => setForm({ ...form, assigned_to_phone: e.target.value })} />
              )}
            </div>

            <div className="grid grid-cols-2 gap-2">
              <div>
                <label className="text-xs text-muted-foreground">{lang === "ar" ? "الحد الأقصى للاستخدام الكلي" : "Max total uses"}</label>
                <input className={inp} type="text" inputMode="decimal" placeholder={lang === "ar" ? "اختياري" : "optional"} value={form.max_uses} onChange={(e) => setForm({ ...form, max_uses: e.target.value })} />
              </div>
              <div>
                <label className="text-xs text-muted-foreground">{lang === "ar" ? "مرات الاستخدام لكل مستخدم" : "Uses per user"}</label>
<input className={inp} type="text" inputMode="decimal" min={1} value={String(form.per_user_limit)} onChange={(e) => setForm({ ...form, per_user_limit: e.target.value as any })} />
              </div>
            </div>

            <input className={inp} type="date" value={form.expires_at} onChange={(e) => setForm({ ...form, expires_at: e.target.value })} />
            <Button onClick={save} className="w-full">{lang === "ar" ? "حفظ" : "Save"}</Button>
          </div>
        </DialogContent>
      </Dialog>

      <Dialog open={!!viewCoupon} onOpenChange={(o) => !o && setViewCoupon(null)}>
        <DialogContent className="max-h-[90vh] overflow-y-auto max-w-2xl">
          <DialogHeader>
            <DialogTitle className="font-mono">{viewCoupon?.code} - {lang === "ar" ? "سجل الاستخدام" : "Redemption log"}</DialogTitle>
          </DialogHeader>
          {redemptions.length === 0 ? (
            <p className="text-center text-muted-foreground py-6">{lang === "ar" ? "لم يستخدم بعد" : "Not used yet"}</p>
          ) : (
            <div className="border rounded-lg overflow-x-auto">
              <Table className="min-w-[520px]">
                <TableHeader>
                  <TableRow>
                    <TableHead>{lang === "ar" ? "مستخدم" : "User"}</TableHead>
                    <TableHead>{lang === "ar" ? "رقم الهاتف" : "Phone"}</TableHead>
                    <TableHead>{lang === "ar" ? "الطلب" : "Order"}</TableHead>
                    <TableHead>{lang === "ar" ? "الخصم" : "Discount"}</TableHead>
                    <TableHead>{lang === "ar" ? "التاريخ" : "Date"}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {redemptions.map((r) => (
                    <TableRow key={r.id}>
                      <TableCell>{r.user_id ? r.user_id.slice(0, 8) : "-"}</TableCell>
                      <TableCell className="font-mono text-xs">{r.phone || "-"}</TableCell>
                      <TableCell className="font-mono text-xs">{r.order_id ? r.order_id.slice(0, 8) : "-"}</TableCell>
                      <TableCell>{r.discount_amount.toLocaleString()} ({r.discount_percent}%)</TableCell>
                      <TableCell className="text-xs text-muted-foreground">{new Date(r.redeemed_at).toLocaleString()}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
};
export default AdminCoupons;
