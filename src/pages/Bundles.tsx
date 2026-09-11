import { useState, useEffect } from "react";
import { useLanguage } from "@/hooks/useLanguage";
import BundleCard from "@/components/BundleCard";
import SEO from "@/components/SEO";
import { Package2 } from "lucide-react";
import { motion } from "framer-motion";

const Bundles = () => {
  const { lang } = useLanguage();
  const [bundles, setBundles] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "");

  useEffect(() => {
    fetch(`${apiBaseUrl}/api/bundles`)
      .then((res) => res.json())
      .then((data) => setBundles(Array.isArray(data) ? data : []))
      .catch(() => setBundles([]))
      .finally(() => setLoading(false));
  }, [apiBaseUrl]);

  return <div className="min-h-screen pt-20 lg:pt-24">
    <SEO title={lang === "ar" ? "العروض | روح" : "Offers | Rouh"} description={lang === "ar" ? "اختَر عرضك المفضل من عطور روح." : "Choose your favourite Rouh perfume offer."} path="/bundles" />
    <div className="container mx-auto px-4 lg:px-8 py-8">
      <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="mb-10">
        <div className="flex items-center gap-3 mb-3"><Package2 className="text-gold" size={32} /><h1 className="font-display text-4xl md:text-5xl font-bold text-gradient-gold">{lang === "ar" ? "العروض" : "Offers"}</h1></div>
        <p className="text-muted-foreground max-w-2xl">{lang === "ar" ? "اختَر العرض ثم حدّد العطور والأحجام التي تريدها." : "Choose an offer, then select the perfumes and sizes you want."}</p>
      </motion.div>
      {loading ? <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">{[1,2,3].map((x) => <div key={x} className="h-96 rounded-2xl bg-muted animate-pulse" />)}</div>
        : bundles.length > 0 ? <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">{bundles.map((bundle) => <BundleCard key={bundle.id} bundle={bundle} />)}</div>
        : <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="text-center py-20 text-muted-foreground"><Package2 className="mx-auto h-16 w-16 mb-4 opacity-50" /><p className="text-lg">{lang === "ar" ? "لا توجد عروض متاحة حالياً" : "No offers are available right now"}</p></motion.div>}
    </div>
  </div>;
};

export default Bundles;
