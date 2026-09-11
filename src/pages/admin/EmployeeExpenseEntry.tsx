import { useState } from "react";
import { useLanguage } from "@/hooks/useLanguage";
import { withAuthHeaders } from "@/lib/auth";
import { toast } from "sonner";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Receipt } from "lucide-react";

export default function EmployeeExpenseEntry() {
  const { lang } = useLanguage();
  const ar = lang === "ar";
  const api = String(import.meta.env.VITE_API_URL || "");
  const [form, setForm] = useState({
    category: "other", description: "", amount: "",
    expense_date: new Date().toISOString().slice(0, 10),
    payment_method: "cash", vendor: "", notes: ""
  });
  const [saving, setSaving] = useState(false);

  const submit = async () => {
    const amount = Number(form.amount.replace(/,/g, ""));
    if (!form.description.trim() || !Number.isFinite(amount) || amount <= 0) {
      toast.error(ar ? "أدخل الوصف والمبلغ بشكل صحيح" : "Enter a valid description and amount");
      return;
    }
    setSaving(true);
    try {
      const r = await fetch(`${api}/api/admin/inventory/expenses`, {
        method: "POST",
        headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json" }),
        body: JSON.stringify({ ...form, amount }),
      });
      const d = await r.json().catch(() => ({}));
      if (!r.ok) throw new Error(d?.message || "Failed");
      toast.success(ar ? "تم تسجيل المصروف" : "Expense recorded");
      setForm(f => ({ ...f, description: "", amount: "", vendor: "", notes: "" }));
    } catch (e) {
      toast.error(e instanceof Error ? e.message : (ar ? "تعذر تسجيل المصروف" : "Unable to record expense"));
    } finally { setSaving(false); }
  };

  return (
    <div className="w-full max-w-3xl mx-auto">
      <Card>
        <CardHeader><CardTitle className="flex items-center gap-2"><Receipt className="h-5 w-5" />{ar ? "تسجيل مصروف" : "Record Expense"}</CardTitle></CardHeader>
        <CardContent className="grid gap-4 sm:grid-cols-2">
          <div><label className="text-sm">{ar ? "نوع المصروف" : "Category"}</label><Select value={form.category} onValueChange={v => setForm(f => ({ ...f, category: v }))}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="rent">{ar ? "إيجار" : "Rent"}</SelectItem><SelectItem value="transport">{ar ? "مواصلات" : "Transport"}</SelectItem><SelectItem value="salaries">{ar ? "رواتب" : "Salaries"}</SelectItem><SelectItem value="marketing">{ar ? "تسويق" : "Marketing"}</SelectItem><SelectItem value="utilities">{ar ? "فواتير" : "Utilities"}</SelectItem><SelectItem value="other">{ar ? "أخرى" : "Other"}</SelectItem></SelectContent></Select></div>
          <div><label className="text-sm">{ar ? "المبلغ (SYP)" : "Amount (SYP)"}</label><Input type="text" inputMode="decimal" value={form.amount} onChange={e => setForm(f => ({ ...f, amount: e.target.value }))} /></div>
          <div className="sm:col-span-2"><label className="text-sm">{ar ? "الوصف" : "Description"}</label><Input value={form.description} onChange={e => setForm(f => ({ ...f, description: e.target.value }))} /></div>
          <div><label className="text-sm">{ar ? "التاريخ" : "Date"}</label><Input type="date" value={form.expense_date} onChange={e => setForm(f => ({ ...f, expense_date: e.target.value }))} /></div>
          <div><label className="text-sm">{ar ? "طريقة الدفع" : "Payment method"}</label><Select value={form.payment_method} onValueChange={v => setForm(f => ({ ...f, payment_method: v }))}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="cash">{ar ? "نقدي" : "Cash"}</SelectItem><SelectItem value="bank">{ar ? "بنك" : "Bank"}</SelectItem><SelectItem value="credit">{ar ? "آجل" : "Credit"}</SelectItem></SelectContent></Select></div>
          <div><label className="text-sm">{ar ? "الجهة" : "Vendor / Entity"}</label><Input value={form.vendor} onChange={e => setForm(f => ({ ...f, vendor: e.target.value }))} /></div>
          <div className="sm:col-span-2"><label className="text-sm">{ar ? "ملاحظات" : "Notes"}</label><Textarea value={form.notes} onChange={e => setForm(f => ({ ...f, notes: e.target.value }))} /></div>
          <div className="sm:col-span-2 flex justify-end"><Button onClick={() => void submit()} disabled={saving}>{saving ? (ar ? "جاري الحفظ..." : "Saving...") : (ar ? "حفظ المصروف" : "Save Expense")}</Button></div>
        </CardContent>
      </Card>
    </div>
  );
}
