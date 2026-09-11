import { useState, useEffect } from "react";
import { useLanguage } from "@/hooks/useLanguage";
import { useComparison } from "@/hooks/useComparison";
import { useProducts } from "@/hooks/useProducts";
import SEO from "@/components/SEO";
import { X, ArrowRight, Scale, Sparkles } from "lucide-react";
import { Link } from "react-router-dom";

const Compare = () => {
  const { t, lang } = useLanguage();
  const { ids, remove, clear } = useComparison();
  const { products } = useProducts();
  const [compareProducts, setCompareProducts] = useState<any[]>([]);

  useEffect(() => {
    const filtered = products.filter((p) => ids.includes(p.id));
    setCompareProducts(filtered);
  }, [products, ids]);

  /**
   * Compute shared fragrance notes across all compared products.
   * Returns an object with three arrays (top, heart, base) containing
   * note names that appear in EVERY compared product's respective tier.
   * Also returns the union of all notes for display.
   */
  const computeSharedNotes = () => {
    if (compareProducts.length < 2) {
      return { sharedTop: [], sharedHeart: [], sharedBase: [], allTop: [], allHeart: [], allBase: [] };
    }

    const normalize = (note: string): string => note.toLowerCase().trim();

    // For each tier, find notes that appear in ALL products
    const intersect = (arrays: string[][]): string[] => {
      if (arrays.length === 0) return [];
      const normalizedSets = arrays.map((arr) => new Set(arr.map(normalize)));
      // Use the first array as reference, keep original casing
      const reference = arrays[0];
      return reference.filter((note) => normalizedSets.every((set) => set.has(normalize(note))));
    };

    const union = (arrays: string[][]): string[] => {
      const seen = new Set<string>();
      const result: string[] = [];
      for (const arr of arrays) {
        for (const note of arr) {
          const key = normalize(note);
          if (!seen.has(key)) {
            seen.add(key);
            result.push(note);
          }
        }
      }
      return result;
    };

    const topArrays = compareProducts.map((p) => p.topNotes || []);
    const heartArrays = compareProducts.map((p) => p.heartNotes || []);
    const baseArrays = compareProducts.map((p) => p.baseNotes || []);

    return {
      sharedTop: intersect(topArrays),
      sharedHeart: intersect(heartArrays),
      sharedBase: intersect(baseArrays),
      allTop: union(topArrays),
      allHeart: union(heartArrays),
      allBase: union(baseArrays),
    };
  };

  const sharedNotes = computeSharedNotes();
  const hasAnyNotes =
    sharedNotes.allTop.length > 0 || sharedNotes.allHeart.length > 0 || sharedNotes.allBase.length > 0;

  const formatPrice = (price: number) =>
    new Intl.NumberFormat(lang === "ar" ? "ar-SY" : "en-SY").format(price);

  if (compareProducts.length === 0) {
    return (
      <div className="min-h-screen pt-20 lg:pt-24">
        <SEO
          title={lang === "ar" ? "مقارنة المنتجات | روح" : "Compare Products | Rouh"}
          description={lang === "ar" ? "قارن بين منتجات روح المختلفة" : "Compare different Rouh products"}
          path="/compare"
        />
        <div className="container mx-auto px-4 lg:px-8 py-20 text-center">
          <Scale className="mx-auto h-16 w-16 text-muted-foreground mb-4" />
          <h2 className="text-2xl font-bold mb-2">
            {lang === "ar" ? "لا توجد منتجات للمقارنة" : "No products to compare"}
          </h2>
          <p className="text-muted-foreground mb-6">
            {lang === "ar" ? "أضف منتجات إلى المقارنة من صفحة المتجر" : "Add products to compare from the shop page"}
          </p>
          <Link
            to="/shop"
            className="inline-flex items-center gap-2 bg-gold text-accent-foreground px-6 py-3 rounded-xl font-semibold hover:shadow-gold transition-all"
          >
            {lang === "ar" ? "الذهاب للمتجر" : "Go to Shop"}
            <ArrowRight size={18} className={lang === "ar" ? "rotate-180" : ""} />
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen pt-20 lg:pt-24">
      <SEO
        title={lang === "ar" ? "مقارنة المنتجات | روح" : "Compare Products | Rouh"}
        description={lang === "ar" ? "قارن بين منتجات روح المختلفة" : "Compare different Rouh products"}
        path="/compare"
      />
      <div className="container mx-auto px-4 lg:px-8 py-8">
        {/* Header */}
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="font-display text-4xl md:text-5xl font-bold text-gradient-gold mb-2">
              {lang === "ar" ? "مقارنة المنتجات" : "Compare Products"}
            </h1>
            <p className="text-muted-foreground">
              {lang === "ar" ? `${compareProducts.length} منتجات` : `${compareProducts.length} products`}
            </p>
          </div>
          <button
            onClick={clear}
            className="flex items-center gap-2 text-sm text-destructive hover:underline"
          >
            <X size={16} />
            {lang === "ar" ? "مسح الكل" : "Clear All"}
          </button>
        </div>

        {/* Comparison Table */}
        <div className="overflow-x-auto">
          <table className="w-full">
            <tbody>
              {/* Product Images & Names */}
              <tr className="border-b border-border">
                <td className="py-6 px-4 font-semibold text-muted-foreground text-sm uppercase tracking-wider sticky left-0 bg-background">
                  {lang === "ar" ? "المنتج" : "Product"}
                </td>
                {compareProducts.map((product) => (
                  <td key={product.id} className="py-6 px-4 text-center min-w-[200px]">
                    <div className="relative">
                      <button
                        onClick={() => remove(product.id)}
                        className="absolute -top-2 -right-2 bg-destructive text-white rounded-full p-1 hover:bg-destructive/80 transition-colors"
                      >
                        <X size={14} />
                      </button>
                      <img
                        src={product.image}
                        alt={lang === "ar" ? product.nameAr : product.name}
                        className="w-24 h-24 object-cover rounded-xl mx-auto mb-3"
                      />
                      <Link
                        to={`/product/${product.id}`}
                        className="font-semibold text-foreground hover:text-gold transition-colors"
                      >
                        {lang === "ar" ? product.nameAr : product.name}
                      </Link>
                    </div>
                  </td>
                ))}
                {/* Empty cells for consistent grid */}
                {Array.from({ length: 4 - compareProducts.length }).map((_, i) => (
                  <td key={`empty-${i}`} className="py-6 px-4 min-w-[200px]" />
                ))}
              </tr>

              {/* Price */}
              <tr className="border-b border-border">
                <td className="py-6 px-4 font-semibold text-muted-foreground text-sm uppercase tracking-wider sticky left-0 bg-background">
                  {lang === "ar" ? "السعر" : "Price"}
                </td>
                {compareProducts.map((product) => (
                  <td key={product.id} className="py-6 px-4 text-center">
                    <span className="text-xl font-bold text-gold">
                      {formatPrice(product.price)} <span className="text-sm font-normal text-muted-foreground">SYP</span>
                    </span>
                  </td>
                ))}
                {Array.from({ length: 4 - compareProducts.length }).map((_, i) => (
                  <td key={`empty-price-${i}`} className="py-6 px-4" />
                ))}
              </tr>

              {/* Category */}
              <tr className="border-b border-border">
                <td className="py-6 px-4 font-semibold text-muted-foreground text-sm uppercase tracking-wider sticky left-0 bg-background">
                  {lang === "ar" ? "التصنيف" : "Category"}
                </td>
                {compareProducts.map((product) => (
                  <td key={product.id} className="py-6 px-4 text-center">
                    <span className="bg-secondary/50 text-secondary-foreground text-sm font-medium px-3 py-1 rounded-full">
                      {t(product.category)}
                    </span>
                  </td>
                ))}
                {Array.from({ length: 4 - compareProducts.length }).map((_, i) => (
                  <td key={`empty-category-${i}`} className="py-6 px-4" />
                ))}
              </tr>

              {/* Fragrance */}
              <tr className="border-b border-border">
                <td className="py-6 px-4 font-semibold text-muted-foreground text-sm uppercase tracking-wider sticky left-0 bg-background">
                  {lang === "ar" ? "العائلة العطرية" : "Fragrance Family"}
                </td>
                {compareProducts.map((product) => (
                  <td key={product.id} className="py-6 px-4 text-center">
                    <span className="bg-gold/10 text-gold text-sm font-medium px-3 py-1 rounded-full">
                      {product.fragranceFamily || t(product.fragrance)}
                    </span>
                  </td>
                ))}
                {Array.from({ length: 4 - compareProducts.length }).map((_, i) => (
                  <td key={`empty-fragrance-${i}`} className="py-6 px-4" />
                ))}
              </tr>

              {/* Top Notes per product */}
              {hasAnyNotes && (
                <tr className="border-b border-border">
                  <td className="py-6 px-4 font-semibold text-muted-foreground text-sm uppercase tracking-wider sticky left-0 bg-background">
                    {lang === "ar" ? "النوتات العلوية" : "Top Notes"}
                  </td>
                  {compareProducts.map((product) => (
                    <td key={product.id} className="py-6 px-4 text-center">
                      <div className="flex flex-wrap gap-1.5 justify-center">
                        {(product.topNotes || []).length > 0 ? (
                          product.topNotes.map((note: string, i: number) => (
                            <span
                              key={`top-${product.id}-${i}`}
                              className={`text-xs font-medium px-2.5 py-1 rounded-full ${
                                sharedNotes.sharedTop.includes(note)
                                  ? "bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/30"
                                  : "bg-sky-500/10 text-sky-600 dark:text-sky-300"
                              }`}
                            >
                              {note}
                            </span>
                          ))
                        ) : (
                          <span className="text-xs text-muted-foreground">—</span>
                        )}
                      </div>
                    </td>
                  ))}
                  {Array.from({ length: 4 - compareProducts.length }).map((_, i) => (
                    <td key={`empty-top-${i}`} className="py-6 px-4" />
                  ))}
                </tr>
              )}

              {/* Heart Notes per product */}
              {hasAnyNotes && (
                <tr className="border-b border-border">
                  <td className="py-6 px-4 font-semibold text-muted-foreground text-sm uppercase tracking-wider sticky left-0 bg-background">
                    {lang === "ar" ? "النوتات الوسطى" : "Heart Notes"}
                  </td>
                  {compareProducts.map((product) => (
                    <td key={product.id} className="py-6 px-4 text-center">
                      <div className="flex flex-wrap gap-1.5 justify-center">
                        {(product.heartNotes || []).length > 0 ? (
                          product.heartNotes.map((note: string, i: number) => (
                            <span
                              key={`heart-${product.id}-${i}`}
                              className={`text-xs font-medium px-2.5 py-1 rounded-full ${
                                sharedNotes.sharedHeart.includes(note)
                                  ? "bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/30"
                                  : "bg-rose-500/10 text-rose-600 dark:text-rose-300"
                              }`}
                            >
                              {note}
                            </span>
                          ))
                        ) : (
                          <span className="text-xs text-muted-foreground">—</span>
                        )}
                      </div>
                    </td>
                  ))}
                  {Array.from({ length: 4 - compareProducts.length }).map((_, i) => (
                    <td key={`empty-heart-${i}`} className="py-6 px-4" />
                  ))}
                </tr>
              )}

              {/* Base Notes per product */}
              {hasAnyNotes && (
                <tr className="border-b border-border">
                  <td className="py-6 px-4 font-semibold text-muted-foreground text-sm uppercase tracking-wider sticky left-0 bg-background">
                    {lang === "ar" ? "النوتات القاعدية" : "Base Notes"}
                  </td>
                  {compareProducts.map((product) => (
                    <td key={product.id} className="py-6 px-4 text-center">
                      <div className="flex flex-wrap gap-1.5 justify-center">
                        {(product.baseNotes || []).length > 0 ? (
                          product.baseNotes.map((note: string, i: number) => (
                            <span
                              key={`base-${product.id}-${i}`}
                              className={`text-xs font-medium px-2.5 py-1 rounded-full ${
                                sharedNotes.sharedBase.includes(note)
                                  ? "bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/30"
                                  : "bg-amber-500/10 text-amber-700 dark:text-amber-300"
                              }`}
                            >
                              {note}
                            </span>
                          ))
                        ) : (
                          <span className="text-xs text-muted-foreground">—</span>
                        )}
                      </div>
                    </td>
                  ))}
                  {Array.from({ length: 4 - compareProducts.length }).map((_, i) => (
                    <td key={`empty-base-${i}`} className="py-6 px-4" />
                  ))}
                </tr>
              )}

              {/* Sizes row removed from customer-facing comparison page.
                  Sizes remain available on the product detail page only. */}

              {/* Description */}
              <tr className="border-b border-border">
                <td className="py-6 px-4 font-semibold text-muted-foreground text-sm uppercase tracking-wider sticky left-0 bg-background">
                  {lang === "ar" ? "الوصف" : "Description"}
                </td>
                {compareProducts.map((product) => (
                  <td key={product.id} className="py-6 px-4 text-center">
                    <p className="text-sm text-muted-foreground line-clamp-3">
                      {lang === "ar" ? product.descriptionAr : product.description}
                    </p>
                  </td>
                ))}
                {Array.from({ length: 4 - compareProducts.length }).map((_, i) => (
                  <td key={`empty-desc-${i}`} className="py-6 px-4" />
                ))}
              </tr>

              {/* Actions */}
              <tr>
                <td className="py-6 px-4 font-semibold text-muted-foreground text-sm uppercase tracking-wider sticky left-0 bg-background">
                  {lang === "ar" ? "إجراءات" : "Actions"}
                </td>
                {compareProducts.map((product) => (
                  <td key={product.id} className="py-6 px-4 text-center">
                    <Link
                      to={`/product/${product.id}`}
                      className="inline-flex items-center gap-2 bg-gold text-accent-foreground px-4 py-2 rounded-xl font-semibold hover:shadow-gold transition-all text-sm"
                    >
                      {lang === "ar" ? "عرض التفاصيل" : "View Details"}
                      <ArrowRight size={16} className={lang === "ar" ? "rotate-180" : ""} />
                    </Link>
                  </td>
                ))}
                {Array.from({ length: 4 - compareProducts.length }).map((_, i) => (
                  <td key={`empty-actions-${i}`} className="py-6 px-4" />
                ))}
              </tr>
            </tbody>
          </table>
        </div>

        {/* Shared Notes Summary — highlights common notes across all compared products */}
        {compareProducts.length >= 2 && hasAnyNotes && (
          <div className="mt-8 rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-6">
            <div className="flex items-center gap-2 mb-4">
              <Sparkles size={20} className="text-emerald-500" />
              <h3 className="font-display text-xl font-bold text-foreground">
                {lang === "ar" ? "النوتات المشتركة" : "Shared Notes"}
              </h3>
            </div>
            <p className="text-sm text-muted-foreground mb-4">
              {lang === "ar"
                ? "النوتات التي تظهر في جميع العطور المقارنة — النوتات المشتركة تظهر بإطار أخضر في الجدول أعلاه."
                : "Notes that appear in every compared fragrance — shared notes are highlighted with a green ring in the table above."}
            </p>

            <div className="space-y-3">
              {/* Shared Top */}
              <div className="flex items-start gap-3">
                <span className="text-sm font-semibold text-sky-500 min-w-[80px]">
                  {lang === "ar" ? "علوية" : "Top"}
                </span>
                <div className="flex flex-wrap gap-2">
                  {sharedNotes.sharedTop.length > 0 ? (
                    sharedNotes.sharedTop.map((note, i) => (
                      <span key={`st-${i}`} className="bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-xs font-medium px-3 py-1.5 rounded-full ring-1 ring-emerald-500/30">
                        {note}
                      </span>
                    ))
                  ) : (
                    <span className="text-xs text-muted-foreground">
                      {lang === "ar" ? "لا توجد نوتات علوية مشتركة" : "No shared top notes"}
                    </span>
                  )}
                </div>
              </div>

              {/* Shared Heart */}
              <div className="flex items-start gap-3">
                <span className="text-sm font-semibold text-rose-500 min-w-[80px]">
                  {lang === "ar" ? "وسطى" : "Heart"}
                </span>
                <div className="flex flex-wrap gap-2">
                  {sharedNotes.sharedHeart.length > 0 ? (
                    sharedNotes.sharedHeart.map((note, i) => (
                      <span key={`sh-${i}`} className="bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-xs font-medium px-3 py-1.5 rounded-full ring-1 ring-emerald-500/30">
                        {note}
                      </span>
                    ))
                  ) : (
                    <span className="text-xs text-muted-foreground">
                      {lang === "ar" ? "لا توجد نوتات وسطى مشتركة" : "No shared heart notes"}
                    </span>
                  )}
                </div>
              </div>

              {/* Shared Base */}
              <div className="flex items-start gap-3">
                <span className="text-sm font-semibold text-amber-500 min-w-[80px]">
                  {lang === "ar" ? "قاعدية" : "Base"}
                </span>
                <div className="flex flex-wrap gap-2">
                  {sharedNotes.sharedBase.length > 0 ? (
                    sharedNotes.sharedBase.map((note, i) => (
                      <span key={`sb-${i}`} className="bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-xs font-medium px-3 py-1.5 rounded-full ring-1 ring-emerald-500/30">
                        {note}
                      </span>
                    ))
                  ) : (
                    <span className="text-xs text-muted-foreground">
                      {lang === "ar" ? "لا توجد نوتات قاعدية مشتركة" : "No shared base notes"}
                    </span>
                  )}
                </div>
              </div>
            </div>

            {/* Similarity score */}
            {(() => {
              const totalShared = sharedNotes.sharedTop.length + sharedNotes.sharedHeart.length + sharedNotes.sharedBase.length;
              const totalAll = sharedNotes.allTop.length + sharedNotes.allHeart.length + sharedNotes.allBase.length;
              const similarity = totalAll > 0 ? Math.round((totalShared / totalAll) * 100) : 0;
              return (
                <div className="mt-4 pt-4 border-t border-emerald-500/20">
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-sm font-semibold text-foreground">
                      {lang === "ar" ? "نسبة التشابه" : "Similarity Score"}
                    </span>
                    <span className="text-lg font-bold text-emerald-600">{similarity}%</span>
                  </div>
                  <div className="h-2 rounded-full bg-muted overflow-hidden">
                    <div
                      className="h-full bg-gradient-to-r from-emerald-400 to-emerald-600 transition-all duration-500"
                      style={{ width: `${similarity}%` }}
                    />
                  </div>
                </div>
              );
            })()}
          </div>
        )}
      </div>
    </div>
  );
};

export default Compare;
