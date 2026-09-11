import { useLanguage } from "@/hooks/useLanguage";
import { useProducts } from "@/hooks/useProducts";
import { useRecentlyViewed } from "@/hooks/useRecentlyViewed";
import ProductCard from "./ProductCard";

const RecentlyViewed = ({ excludeId }: { excludeId?: string }) => {
  const { lang } = useLanguage();
  const { products } = useProducts();
  const { ids } = useRecentlyViewed();

  const items = ids
    .filter((id) => id !== excludeId)
    .map((id) => products.find((p) => p.id === id))
    .filter(Boolean)
    .slice(0, 4);

  if (items.length === 0) return null;

  return (
    <section className="mt-20">
      <h2 className="font-display text-3xl font-bold text-gradient-gold mb-10">
        {lang === "ar" ? "شاهدتها مؤخراً" : "Recently Viewed"}
      </h2>
      <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
        {items.map((p) => p && <ProductCard key={p.id} product={p} />)}
      </div>
    </section>
  );
};

export default RecentlyViewed;