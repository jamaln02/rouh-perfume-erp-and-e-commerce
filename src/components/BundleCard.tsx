import { Link } from "react-router-dom";
import { useLanguage } from "@/hooks/useLanguage";
import { Package2, Percent } from "lucide-react";
import { motion } from "framer-motion";

interface BundleCardProps { bundle: any; }

const BundleCard = ({ bundle }: BundleCardProps) => {
  const { lang } = useLanguage();
  const config = bundle.offer_config;
  const slots = Array.isArray(config?.slots) ? config.slots : [];
  const freeCount = slots.filter((slot: any) => slot.price_mode === "free").length;
  const label = bundle.offer_type === "product_discount" || bundle.offer_type === "specific_product"
    ? (lang === "ar" ? "خصم على عطر محدد" : "Product discount")
    : freeCount > 0
      ? (lang === "ar" ? `${slots.length} قطع — ${freeCount} مجانية` : `${slots.length} items — ${freeCount} free`)
      : (lang === "ar" ? `${slots.length || 1} قطع قابلة للتخصيص` : `${slots.length || 1} customizable items`);
  const fixedTotal = config?.pricing_mode === "fixed_total" ? Number(config.fixed_total || 0) : 0;

  return <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }}>
    <Link to={`/bundle/${encodeURIComponent(bundle.id)}`} className="group block">
      <div className="h-full rounded-2xl border border-border bg-card overflow-hidden hover:border-gold/30 transition-all duration-500 hover:shadow-xl">
        {Number(bundle.discount_percentage) > 0 && <div className="absolute top-3 start-3 z-10"><span className="bg-gradient-to-r from-gold to-gold-dark text-accent-foreground text-xs font-bold px-3 py-1.5 rounded-full shadow-gold flex items-center gap-1"><Percent size={12} />{Number(bundle.discount_percentage)}%</span></div>}
        <div className="relative overflow-hidden"><img src={bundle.image_url || "/placeholder.svg"} alt={lang === "ar" ? bundle.name_ar : bundle.name} className="w-full aspect-[4/3] object-cover transition-transform duration-700 group-hover:scale-110" loading="lazy" width={400} height={300} /></div>
        <div className="p-4">
          <div className="flex items-start justify-between gap-2 mb-2"><h3 className="font-semibold text-foreground group-hover:text-gold transition-colors flex-1">{lang === "ar" ? bundle.name_ar : bundle.name}</h3><Package2 size={16} className="text-muted-foreground shrink-0" /></div>
          <p className="text-xs text-gold mb-3">{label}</p>
          <p className="text-xs text-muted-foreground mb-3 line-clamp-2">{lang === "ar" ? bundle.description_ar : bundle.description}</p>
          <div className="flex items-center justify-between mt-3"><div>{fixedTotal > 0 ? <p className="text-gold font-bold text-lg">{fixedTotal.toLocaleString()} <span className="text-xs font-normal text-muted-foreground">SYP</span></p> : <p className="text-gold font-bold text-lg">{lang === "ar" ? "حسب اختياراتك" : "Based on your choices"}</p>}</div><span className="text-xs text-muted-foreground">{lang === "ar" ? "تخصيص العرض" : "Customize"}</span></div>
        </div>
      </div>
    </Link>
  </motion.div>;
};

export default BundleCard;
