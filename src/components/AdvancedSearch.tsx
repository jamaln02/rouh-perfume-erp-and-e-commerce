import { useState, useEffect, useRef } from "react";
import { useLanguage } from "@/hooks/useLanguage";
import { useProducts } from "@/hooks/useProducts";
import { Search, X, Clock, TrendingUp } from "lucide-react";
import { motion, AnimatePresence } from "framer-motion";
import { Link, useNavigate } from "react-router-dom";

const AdvancedSearch = ({ onClose }: { onClose?: () => void }) => {
  const { t, lang } = useLanguage();
  const { products } = useProducts();
  const navigate = useNavigate();
  const [query, setQuery] = useState("");
  const [suggestions, setSuggestions] = useState<any[]>([]);
  const [recentSearches, setRecentSearches] = useState<string[]>([]);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const searchRef = useRef<HTMLDivElement>(null);

  // Load recent searches from localStorage
  useEffect(() => {
    try {
      const saved = localStorage.getItem("rouh_recent_searches");
      if (saved) setRecentSearches(JSON.parse(saved));
    } catch (error) {
    }
  }, []);

  // Filter products based on query
  useEffect(() => {
    const trimmedQuery = query.trim();
    if (trimmedQuery.length === 0) {
      setSuggestions([]);
      setShowSuggestions(true);
      return;
    }

    const filtered = products.filter((p) => {
      const searchLower = trimmedQuery.toLocaleLowerCase();
      const arabicQuery = trimmedQuery;
      const haystack = [
        p.name, p.nameAr, p.description, p.descriptionAr, p.category, p.fragrance,
        ...(p.sizes || []),
        ...(p.variants || []).map((variant: any) => variant.size),
      ].filter(Boolean).join(" ").toLocaleLowerCase();
      return haystack.includes(searchLower) || p.nameAr.includes(arabicQuery) || p.descriptionAr?.includes(arabicQuery);
    }).slice(0, 8); // Limit to 8 suggestions

    setSuggestions(filtered);
    setShowSuggestions(true);
  }, [query, products]);

  // Show suggestions when component mounts
  useEffect(() => {
    setShowSuggestions(true);
  }, []);

  // Close suggestions when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (searchRef.current && !searchRef.current.contains(event.target as Node)) {
        setShowSuggestions(false);
      }
    };

    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  const handleSearch = (searchTerm: string) => {
    if (!searchTerm.trim()) return;

    // Save to recent searches
    const newRecent = [searchTerm, ...recentSearches.filter((s) => s !== searchTerm)].slice(0, 5);
    setRecentSearches(newRecent);
    try {
      localStorage.setItem("rouh_recent_searches", JSON.stringify(newRecent));
    } catch (error) {
    }

    setQuery(searchTerm);
    setShowSuggestions(false);
    onClose?.();

    // Navigate to shop with search query using React Router
    navigate(`/shop?q=${encodeURIComponent(searchTerm)}`);
  };

  const clearRecentSearches = () => {
    setRecentSearches([]);
    try {
      localStorage.removeItem("rouh_recent_searches");
    } catch (error) {
    }
  };

  const popularSearches = lang === "ar"
    ? ["عود", "زهري", "رجالي", "نسائي", "خشبي"]
    : ["oud", "floral", "men", "women", "woody"];

  return (
    <div ref={searchRef} className="relative w-full max-w-2xl mx-auto z-[70]">
      <div className="relative">
        <Search size={20} className="absolute start-4 top-1/2 -translate-y-1/2 text-muted-foreground" />
        <input
          type="text"
          value={query}
          onChange={(e) => { setQuery(e.target.value); setShowSuggestions(true); }}
          onKeyDown={(e) => {
            if (e.key === "Enter") handleSearch(query);
          }}
          placeholder={t("search")}
          className="w-full bg-card text-foreground ps-12 pe-12 py-4 rounded-2xl border border-border outline-none focus:ring-2 focus:ring-gold placeholder:text-muted-foreground transition-shadow text-lg"
          autoFocus
        />
        {query && (
          <button
            onClick={() => setQuery("")}
            className="absolute end-4 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
          >
            <X size={20} />
          </button>
        )}
      </div>

      <AnimatePresence>
        {showSuggestions && (
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            className="absolute top-full left-0 right-0 mt-2 bg-card border border-border rounded-2xl shadow-xl z-[80]"
          >
            {/* Product suggestions */}
            {suggestions.length > 0 && (
              <div className="p-2">
                <div className="px-3 py-2 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                  {lang === "ar" ? "المنتجات" : "Products"}
                </div>
                {suggestions.map((product) => (
                  <Link
                    key={product.id}
                    to={`/product/${product.id}`}
                    onClick={() => {
                      setShowSuggestions(false);
                      onClose?.();
                    }}
                    className="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-muted transition-colors"
                  >
                    <img
                      src={product.image}
                      alt={lang === "ar" ? product.nameAr : product.name}
                      className="w-12 h-12 object-cover rounded-lg"
                    />
                    <div className="flex-1 min-w-0">
                      <p className="font-medium text-foreground truncate">
                        {lang === "ar" ? product.nameAr : product.name}
                      </p>
                      <p className="text-xs text-muted-foreground">
                        {t(product.category)} · {t(product.fragrance)}
                      </p>
                    </div>
                  </Link>
                ))}
              </div>
            )}

            {query.trim().length > 0 && suggestions.length === 0 && (
              <div className="px-4 py-5 text-sm text-muted-foreground text-center">
                {lang === "ar" ? "لا توجد منتجات مطابقة للبحث." : "No matching products found."}
              </div>
            )}

            {/* Recent searches */}
            {query.trim().length === 0 && recentSearches.length > 0 && (
              <div className="p-2 border-t border-border">
                <div className="flex items-center justify-between px-3 py-2">
                  <div className="flex items-center gap-2 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                    <Clock size={14} />
                    {lang === "ar" ? "عمليات البحث الأخيرة" : "Recent Searches"}
                  </div>
                  <button
                    onClick={clearRecentSearches}
                    className="text-xs text-destructive hover:underline"
                  >
                    {lang === "ar" ? "مسح" : "Clear"}
                  </button>
                </div>
                {recentSearches.map((search, i) => (
                  <button
                    key={i}
                    onClick={() => handleSearch(search)}
                    className="w-full flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-muted transition-colors text-start"
                  >
                    <Clock size={14} className="text-muted-foreground" />
                    <span className="text-foreground">{search}</span>
                  </button>
                ))}
              </div>
            )}

            {/* Popular searches */}
            {query.trim().length === 0 && (
              <div className="p-2 border-t border-border">
                <div className="flex items-center gap-2 px-3 py-2 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                  <TrendingUp size={14} />
                  {lang === "ar" ? "عمليات البحث الشائعة" : "Popular Searches"}
                </div>
                <div className="flex flex-wrap gap-2 px-3 pb-2">
                  {popularSearches.map((search) => (
                    <button
                      key={search}
                      onClick={() => handleSearch(search)}
                      className="px-3 py-1.5 bg-muted hover:bg-gold hover:text-accent-foreground rounded-lg text-sm transition-colors"
                    >
                      {search}
                    </button>
                  ))}
                </div>
              </div>
            )}
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
};

export default AdvancedSearch;
