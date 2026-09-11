import { useCallback, useEffect, useState } from "react";
import { useLanguage } from "@/hooks/useLanguage";
import { withAuthHeaders } from "@/lib/auth";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Star, Check, Trash2 } from "lucide-react";
import { toast } from "sonner";

interface Row {
  id: string;
  product_id: string;
  rating: number;
  comment: string | null;
  approved: boolean;
  created_at: string;
  product?: { name: string; name_ar: string } | null;
}

const AdminReviews = () => {
  const { lang } = useLanguage();
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "");
  const [rows, setRows] = useState<Row[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    const response = await fetch(`${apiBaseUrl}/api/admin/reviews`, { headers: withAuthHeaders({ Accept: "application/json" }) });
    const data = await response.json();
    setRows((data?.reviews as Row[]) || []);
    setLoading(false);
  }, [apiBaseUrl]);
  useEffect(() => { void load(); }, [load]);

  const toggle = async (id: string, approved: boolean) => {
    await fetch(`${apiBaseUrl}/api/admin/reviews/${id}`, {
      method: "PATCH",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        ...withAuthHeaders(),
      },
      body: JSON.stringify({ approved: !approved }),
    });
    toast.success(lang === "ar" ? "تم التحديث" : "Updated");
    load();
  };
  const del = async (id: string) => {
    if (!confirm(lang === "ar" ? "حذف هذا التقييم؟" : "Delete this review?")) return;
    await fetch(`${apiBaseUrl}/api/admin/reviews/${id}`, {
      method: "DELETE",
      headers: withAuthHeaders({ Accept: "application/json" }),
    });
    toast.success(lang === "ar" ? "تم الحذف" : "Deleted");
    load();
  };

  if (loading) return <div className="flex justify-center p-8"><div className="animate-spin h-8 w-8 border-2 border-primary border-t-transparent rounded-full" /></div>;

  return (
    <div  className="p-6 space-y-6 space-x-2">
      <h1 className="text-2xl font-display font-bold mb-6 space-x-2">{lang === "ar" ? "إدارة التقييمات" : "Manage Reviews"}</h1>
      <div className="border rounded-lg overflow-x-auto">
        <Table className="min-w-[640px]">
          <TableHeader>
            <TableRow>
              <TableHead>{lang === "ar" ? "المنتج" : "Product"}</TableHead>
              <TableHead>{lang === "ar" ? "التقييم" : "Rating"}</TableHead>
              <TableHead>{lang === "ar" ? "التعليق" : "Comment"}</TableHead>
              <TableHead>{lang === "ar" ? "الحالة" : "Status"}</TableHead>
              <TableHead>{lang === "ar" ? "التاريخ" : "Date"}</TableHead>
              <TableHead></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((r) => (
              <TableRow key={r.id}>
                <TableCell className="font-medium">{lang === "ar" ? r.product?.name_ar : r.product?.name}</TableCell>
                <TableCell><div className="flex">{[1,2,3,4,5].map(n=><Star key={n} size={14} className={n<=r.rating?"fill-gold text-gold":"text-muted-foreground"} />)}</div></TableCell>
                <TableCell className="max-w-xs truncate text-sm">{r.comment || "-"}</TableCell>
                <TableCell><Badge className={r.approved?"bg-green-500/20 text-green-600":"bg-yellow-500/20 text-yellow-600"}>{r.approved?(lang==="ar"?"معتمد":"Approved"):(lang==="ar"?"مخفي":"Hidden")}</Badge></TableCell>
                <TableCell className="text-xs text-muted-foreground">{new Date(r.created_at).toLocaleDateString()}</TableCell>
                <TableCell className="flex gap-2">
                  <Button size="sm" variant="outline" onClick={() => toggle(r.id, r.approved)}><Check className="h-3 w-3" /></Button>
                  <Button size="sm" variant="destructive" onClick={() => del(r.id)}><Trash2 className="h-3 w-3" /></Button>
                </TableCell>
              </TableRow>
            ))}
            {rows.length === 0 && <TableRow><TableCell colSpan={6} className="text-center py-8 text-muted-foreground">{lang === "ar" ? "لا توجد تقييمات" : "No reviews"}</TableCell></TableRow>}
          </TableBody>
        </Table>
      </div>
    </div>
  );
};
export default AdminReviews;