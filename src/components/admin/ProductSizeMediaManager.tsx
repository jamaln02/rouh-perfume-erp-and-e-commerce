import { useCallback, useEffect, useState } from "react";
import { ImageIcon, Trash2, Upload } from "lucide-react";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { toast } from "sonner";
import { withAuthHeaders } from "@/lib/auth";

interface SizeMedia {
  size_key: string;
  size_label: string;
  image_url?: string | null;
  image_is_reference?: boolean;
}

interface Props {
  open: boolean;
  apiBaseUrl: string;
  lang: "ar" | "en";
  onOpenChange: (open: boolean) => void;
}

export default function ProductSizeMediaManager({ open, apiBaseUrl, lang, onOpenChange }: Props) {
  const ar = lang === "ar";
  const [items, setItems] = useState<SizeMedia[]>([]);
  const [loading, setLoading] = useState(false);
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [newSize, setNewSize] = useState("");
  const [referenceByKey, setReferenceByKey] = useState<Record<string, boolean>>({});

  const load = useCallback(async () => {
    if (!open) return;
    setLoading(true);
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/size-media`, { headers: withAuthHeaders({ Accept: "application/json" }) });
      const data = await response.json().catch(() => null);
      if (!response.ok) throw new Error(data?.message || (ar ? "تعذر تحميل صور الأحجام" : "Failed to load size images"));
      const sizes = Array.isArray(data?.sizes) ? data.sizes as SizeMedia[] : [];
      setItems(sizes);
      setReferenceByKey(Object.fromEntries(sizes.map((item) => [item.size_key, Boolean(item.image_is_reference)])));
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
    } finally {
      setLoading(false);
    }
  }, [apiBaseUrl, ar, open]);

  useEffect(() => { void load(); }, [load]);

  const ensureSize = async (label: string) => {
    const clean = label.trim();
    if (!clean) return;
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/size-media`, {
        method: "POST",
        headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json" }),
        body: JSON.stringify({ size_label: clean }),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) throw new Error(data?.message || (ar ? "فشل إضافة الحجم" : "Failed to add size"));
      setNewSize("");
      await load();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
    }
  };

  const persistReference = async (item: SizeMedia, checked: boolean) => {
    setReferenceByKey((prev) => ({ ...prev, [item.size_key]: checked }));
    if (!item.image_url) return;
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/size-media`, {
        method: "POST",
        headers: withAuthHeaders({ "Content-Type": "application/json", Accept: "application/json" }),
        body: JSON.stringify({ size_label: item.size_label, image_is_reference: checked }),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) throw new Error(data?.message || (ar ? "فشل حفظ إعداد الصورة" : "Failed to save image setting"));
      await load();
    } catch (error) {
      setReferenceByKey((prev) => ({ ...prev, [item.size_key]: !checked }));
      toast.error(error instanceof Error ? error.message : String(error));
    }
  };

  const upload = async (item: SizeMedia, file: File | null) => {
    if (!file) return;
    if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) {
      toast.error(ar ? "اختر صورة JPG أو PNG أو WebP." : "Choose a JPG, PNG or WebP image.");
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      toast.error(ar ? "حجم الصورة يجب ألا يتجاوز 5 ميغابايت." : "Image size must not exceed 5 MB.");
      return;
    }
    setBusyKey(item.size_key);
    try {
      const form = new FormData();
      form.append("size_label", item.size_label);
      form.append("image", file);
      form.append("image_is_reference", referenceByKey[item.size_key] ? "1" : "0");
      const response = await fetch(`${apiBaseUrl}/api/admin/size-media/${encodeURIComponent(item.size_key)}/image`, {
        method: "POST",
        headers: withAuthHeaders({ Accept: "application/json" }),
        body: form,
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) throw new Error(data?.message || data?.errors?.image?.[0] || (ar ? "فشل رفع الصورة" : "Failed to upload image"));
      toast.success(ar ? `تم حفظ صورة ${item.size_label} وتطبيقها على جميع المنتجات.` : `${item.size_label} image saved for all products.`);
      await load();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
    } finally {
      setBusyKey(null);
    }
  };

  const remove = async (item: SizeMedia) => {
    if (!item.image_url) return;
    if (!window.confirm(ar ? `حذف الصورة الموحدة للحجم ${item.size_label}؟` : `Remove the shared image for ${item.size_label}?`)) return;
    setBusyKey(item.size_key);
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/size-media/${encodeURIComponent(item.size_key)}/image`, {
        method: "DELETE",
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) throw new Error(data?.message || (ar ? "فشل حذف الصورة" : "Failed to delete image"));
      toast.success(ar ? "تم حذف الصورة" : "Image removed");
      await load();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
    } finally {
      setBusyKey(null);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="w-[96vw] max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
        <DialogHeader className="shrink-0">
          <DialogTitle>{ar ? "صور الأحجام الموحدة" : "Global size images"}</DialogTitle>
          <p className="text-sm text-muted-foreground">
            {ar ? "ارفع صورة الحجم مرة واحدة وستظهر تلقائياً لكل المنتجات التي تستخدم نفس الحجم." : "Upload a size image once and it will automatically appear on every product using that size."}
          </p>
        </DialogHeader>

        <div className="min-h-0 flex-1 overflow-y-auto pe-1 space-y-3">
          <div className="flex gap-2">
            <Input value={newSize} onChange={(e) => setNewSize(e.target.value)} placeholder={ar ? "مثال: 50ml" : "Example: 50ml"} />
            <Button variant="outline" onClick={() => void ensureSize(newSize)} disabled={!newSize.trim()}>{ar ? "إضافة حجم" : "Add size"}</Button>
          </div>

          {loading ? (
            <div className="py-10 text-center text-muted-foreground">{ar ? "جاري التحميل…" : "Loading…"}</div>
          ) : items.length === 0 ? (
            <div className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">{ar ? "لا توجد أحجام بعد." : "No sizes yet."}</div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              {items.map((item) => (
                <div key={item.size_key} className="rounded-xl border bg-card p-3 space-y-3">
                  <div className="flex items-center justify-between gap-2">
                    <div className="font-semibold">{item.size_label}</div>
                    {item.image_url ? <span className="text-xs text-emerald-600">{ar ? "صورة محفوظة" : "Image saved"}</span> : <span className="text-xs text-muted-foreground">{ar ? "الصورة الأساسية" : "Product image fallback"}</span>}
                  </div>
                  <div className="rounded-lg border bg-muted/20 overflow-hidden aspect-[4/3] flex items-center justify-center">
                    {item.image_url ? <img src={item.image_url} alt={item.size_label} className="w-full h-full object-contain" /> : <ImageIcon className="h-8 w-8 text-muted-foreground" />}
                  </div>
                  <div className="flex items-center justify-between gap-3">
                    <label className="flex items-center gap-2 text-sm">
                      <input type="checkbox" checked={Boolean(referenceByKey[item.size_key])} onChange={(e) => void persistReference(item, e.target.checked)} />
                      {ar ? "صورة توضيحية" : "Illustrative image"}
                    </label>
                    <div className="flex items-center gap-2">
                      {item.image_url && <Button size="icon" variant="destructive" disabled={busyKey === item.size_key} onClick={() => void remove(item)} title={ar ? "حذف الصورة" : "Delete image"}><Trash2 className="h-4 w-4" /></Button>}
                      <label className="inline-flex cursor-pointer">
                        <Button asChild variant="outline" disabled={busyKey === item.size_key}>
                          <span><Upload className="h-4 w-4 me-2" />{busyKey === item.size_key ? (ar ? "جاري الرفع…" : "Uploading…") : (ar ? "رفع صورة" : "Upload image")}</span>
                        </Button>
                        <input className="sr-only" type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => { void upload(item, e.target.files?.[0] || null); e.currentTarget.value = ""; }} />
                      </label>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        <DialogFooter className="shrink-0 border-t bg-background pt-3">
          <Button variant="outline" onClick={() => onOpenChange(false)}>{ar ? "إغلاق" : "Close"}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
