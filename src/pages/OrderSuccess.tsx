import { useEffect, useState } from "react";
import { Link, useLocation, useParams } from "react-router-dom";
import { useLanguage } from "@/hooks/useLanguage";
import { motion } from "framer-motion";
import { CheckCircle2, Package, Phone, MapPin, Home, MessageCircle } from "lucide-react";
import SEO from "@/components/SEO";

interface OrderData {
  id: string;
  customer_name: string;
  customer_phone: string;
  customer_address: string;
  city: string;
  total: number;
  shipping_cost: number;
  status: string;
  created_at: string;
}

interface OrderItem {
  id: string;
  product_name: string;
  size: string | null;
  quantity: number;
  price: number;
}

const OrderSuccess = () => {
  const { id } = useParams();
  const location = useLocation();
  const { lang } = useLanguage();
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "");
  const [order, setOrder] = useState<OrderData | null>(null);
  const [items, setItems] = useState<OrderItem[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!id) return;
    (async () => {
      const token = new URLSearchParams(location.search).get("token");
      if (!token) { setLoading(false); return; }
      const response = await fetch(`${apiBaseUrl}/api/orders/${id}?token=${encodeURIComponent(token)}`, {
        headers: {
          Accept: "application/json",
        },
      });
      const data = await response.json();
      setOrder((data?.order as OrderData) ?? null);
      setItems((data?.items as OrderItem[]) || []);
      setLoading(false);
    })();
  }, [apiBaseUrl, id, location.search]);

  const fmt = (n: number) => new Intl.NumberFormat(lang === "ar" ? "ar-SY" : "en-SY").format(n);

  if (loading) {
    return (
      <div className="min-h-screen pt-24 flex items-center justify-center">
        <div className="animate-spin h-10 w-10 border-2 border-primary border-t-transparent rounded-full" />
      </div>
    );
  }

  if (!order) {
    return (
      <div className="min-h-screen pt-24 flex flex-col items-center justify-center gap-4">
        <p className="text-foreground">{lang === "ar" ? "الطلب غير موجود" : "Order not found"}</p>
        <Link to="/" className="text-primary underline">{lang === "ar" ? "العودة للرئيسية" : "Back to home"}</Link>
      </div>
    );
  }

  return (
    <div className="min-h-screen pt-20 lg:pt-24 pb-12 bg-background">
      <SEO
        title={lang === "ar" ? "تم تأكيد طلبك | روح" : "Order Confirmed | Rouh"}
        description={lang === "ar" ? "تم استلام طلبك بنجاح وسيتم التواصل معك قريباً." : "Your order has been received successfully."}
        path={`/order-success/${id}`}
      />
      <div className="container mx-auto px-4 max-w-3xl">
        <motion.div
          initial={{ opacity: 0, scale: 0.9 }}
          animate={{ opacity: 1, scale: 1 }}
          className="text-center mb-8"
        >
          <motion.div
            initial={{ scale: 0 }}
            animate={{ scale: 1 }}
            transition={{ delay: 0.2, type: "spring", stiffness: 200 }}
            className="inline-flex items-center justify-center w-24 h-24 rounded-full bg-green-500/10 mb-4"
          >
            <CheckCircle2 className="w-14 h-14 text-green-500" />
          </motion.div>
          <h1 className="text-3xl lg:text-4xl font-display font-bold text-foreground mb-3">
            {lang === "ar" ? "تم استلام طلبك بنجاح! 🎉" : "Order received successfully! 🎉"}
          </h1>
          <p className="text-muted-foreground">
            {lang === "ar"
              ? "شكراً لثقتك بـ روح. سنتواصل معك خلال 24 ساعة لتأكيد طلبك."
              : "Thank you for trusting Rouh. We'll contact you within 24 hours to confirm."}
          </p>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
          className="bg-card border border-border rounded-2xl p-6 lg:p-8 space-y-6"
        >
          <div className="flex items-center justify-between pb-4 border-b border-border">
            <div>
              <p className="text-xs text-muted-foreground mb-1">{lang === "ar" ? "رقم الطلب" : "Order Number"}</p>
              <p className="font-mono text-sm font-bold text-foreground">#{order.id.slice(0, 8).toUpperCase()}</p>
            </div>
            <span className="px-3 py-1.5 rounded-full bg-yellow-500/15 text-yellow-600 text-xs font-medium">
              {lang === "ar" ? "قيد المراجعة" : "Pending"}
            </span>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div className="flex items-start gap-3">
              <Phone className="w-4 h-4 text-primary mt-0.5 shrink-0" />
              <div>
                <p className="text-muted-foreground text-xs">{lang === "ar" ? "الهاتف" : "Phone"}</p>
                <p className="text-foreground font-medium" dir="ltr">{order.customer_phone}</p>
              </div>
            </div>
            <div className="flex items-start gap-3">
              <MapPin className="w-4 h-4 text-primary mt-0.5 shrink-0" />
              <div>
                <p className="text-muted-foreground text-xs">{lang === "ar" ? "العنوان" : "Address"}</p>
                <p className="text-foreground font-medium">{order.city} — {order.customer_address}</p>
              </div>
            </div>
          </div>

          <div>
            <h2 className="text-sm font-bold text-foreground mb-3 flex items-center gap-2">
              <Package className="w-4 h-4 text-primary" />
              {lang === "ar" ? "المنتجات" : "Items"}
            </h2>
            <div className="space-y-2">
              {items.map((it) => (
                <div key={it.id} className="flex justify-between text-sm py-2 border-b border-border/50 last:border-0">
                  <span className="text-foreground">{it.product_name} {it.size && <span className="text-muted-foreground">({it.size})</span>} × {it.quantity}</span>
                  <span className="font-medium text-foreground">{fmt(it.price * it.quantity)} SYP</span>
                </div>
              ))}
            </div>
          </div>

          <div className="pt-4 border-t border-border space-y-2 text-sm">
            <div className="flex justify-between text-muted-foreground">
              <span>{lang === "ar" ? "الشحن" : "Shipping"}</span>
              <span>{order.shipping_cost === 0 ? (lang === "ar" ? "مجاني" : "Free") : `${fmt(order.shipping_cost)} SYP`}</span>
            </div>
            <div className="flex justify-between text-lg font-bold pt-2 border-t border-border">
              <span className="text-foreground">{lang === "ar" ? "الإجمالي" : "Total"}</span>
              <span className="text-primary">{fmt(order.total)} SYP</span>
            </div>
          </div>

          <div className="bg-primary/5 border border-primary/20 rounded-xl p-4 text-sm text-muted-foreground">
            {lang === "ar"
              ? "💡 الدفع عند الاستلام — احتفظ برقم طلبك للمتابعة."
              : "💡 Cash on delivery — keep your order number for tracking."}
          </div>
        </motion.div>

        <div className="flex flex-col sm:flex-row gap-3 mt-6">
          <Link
            to="/"
            className="flex-1 inline-flex items-center justify-center gap-2 bg-gradient-gold text-accent-foreground py-3 rounded-xl font-bold shadow-gold hover:opacity-95 transition"
          >
            <Home className="w-4 h-4" />
            {lang === "ar" ? "العودة للرئيسية" : "Back to home"}
          </Link>
          <a
            href="https://wa.me/963933898625"
            target="_blank"
            rel="noopener noreferrer"
            className="flex-1 inline-flex items-center justify-center gap-2 border border-border text-foreground py-3 rounded-xl font-medium hover:bg-muted transition"
          >
            <MessageCircle className="w-4 h-4 text-[#25D366]" />
            {lang === "ar" ? "تواصل معنا" : "Contact us"}
          </a>
        </div>
      </div>
    </div>
  );
};

export default OrderSuccess;
