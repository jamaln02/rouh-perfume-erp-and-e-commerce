import { useState, useEffect } from "react";

export interface DBProductVariant {
  id: string;
  size_label: string;
  volume_ml: number | null;
  bottle_shape: string | null;
  selling_price_default: number | null;
  image_url: string | null;
  image_is_reference?: boolean | null;
}

export interface DBProduct {
  id: string;
  name: string;
  name_ar: string;
  description: string | null;
  description_ar: string | null;
  price: number;
  image_url: string | null;
  category_id: string | null;
  fragrance: string | null;
  top_notes: string[] | string | null;
  heart_notes: string[] | string | null;
  base_notes: string[] | string | null;
  fragrance_family: string | null;
  sizes: string[] | null;
  available_sizes?: string[] | null;
  size_prices?: Record<string, number> | string | null;
  size_stock?: Record<string, number> | string | null;
  featured: boolean | null;
  is_new: boolean | null;
  best_seller: boolean | null;
  stock: number | null;
  variants?: DBProductVariant[] | null;
}

interface DBCategory {
  id: string;
  name: string;
  name_ar: string;
  slug: string;
}

const apiBaseUrl = String(import.meta.env.VITE_API_URL || "");

const resolveImageUrl = (imageUrl: string | null, productId?: string): string => {
  if (!imageUrl || imageUrl.trim() === "" || imageUrl.trim() === "/placeholder.svg") {
    return "/placeholder.svg";
  }
  if (/^https?:\/\//i.test(imageUrl)) return imageUrl;
  if (imageUrl.startsWith("/")) return `${apiBaseUrl}${imageUrl}`;
  return imageUrl;
};

/**
 * Parse a fragrance notes field that may arrive as:
 *  - a native JS array (Laravel JSON cast already decoded it)
 *  - a JSON-encoded string (e.g. `["Bergamot","Lemon"]`)
 *  - a comma-separated string (legacy fallback)
 *  - null / undefined
 * Always returns a clean string[].
 */
const parseNotesArray = (notes: unknown): string[] => {
  if (Array.isArray(notes)) {
    return notes.map(String).map((n) => n.trim()).filter((n) => n.length > 0);
  }
  if (typeof notes === "string") {
    const trimmed = notes.trim();
    if (trimmed === "") return [];
    // Try JSON first
    try {
      const parsed = JSON.parse(trimmed);
      if (Array.isArray(parsed)) {
        return parsed.map(String).map((n) => n.trim()).filter((n) => n.length > 0);
      }
    } catch {
      // Not JSON — treat as comma-separated
    }
    return trimmed
      .split(",")
      .map((n) => n.trim())
      .filter((n) => n.length > 0);
  }
  return [];
};

const parseSizes = (sizes: unknown): string[] => {
  if (Array.isArray(sizes)) {
    const cleaned = sizes.map(String).map((s) => s.trim()).filter((s) => s.length > 0);
    if (cleaned.length > 0) return cleaned;
  }
  if (typeof sizes === "string") {
    try {
      const parsed = JSON.parse(sizes);
      if (Array.isArray(parsed)) {
        const cleaned = parsed.map(String).map((s) => s.trim()).filter((s) => s.length > 0);
        if (cleaned.length > 0) return cleaned;
      }
    } catch {
      // not JSON — fall through
    }
  }
  return ["50ml", "100ml"];
};

const parseSizePrices = (sizePrices: unknown, sizes: string[], basePrice: number): Record<string, number> => {
  const parsed: Record<string, number> = {};

  if (sizePrices && typeof sizePrices === "object" && !Array.isArray(sizePrices)) {
    for (const [key, value] of Object.entries(sizePrices as Record<string, unknown>)) {
      if (typeof value === "number") parsed[key] = value;
      else if (typeof value === "string" && value.trim() !== "" && !Number.isNaN(Number(value))) parsed[key] = Number(value);
    }
  } else if (typeof sizePrices === "string") {
    try {
      const decoded = JSON.parse(sizePrices) as Record<string, unknown>;
      for (const [key, value] of Object.entries(decoded || {})) {
        if (typeof value === "number") parsed[key] = value;
        else if (typeof value === "string" && value.trim() !== "" && !Number.isNaN(Number(value))) parsed[key] = Number(value);
      }
    } catch {
      // ignore invalid JSON
    }
  }

  if (Object.keys(parsed).length === 0) {
    for (const size of sizes) {
      parsed[size] = basePrice;
    }
  }

  return parsed;
};

const parseSizeStock = (sizeStock: unknown): Record<string, number> => {
  const parsed: Record<string, number> = {};

  if (sizeStock && typeof sizeStock === "object" && !Array.isArray(sizeStock)) {
    for (const [key, value] of Object.entries(sizeStock as Record<string, unknown>)) {
      if (typeof value === "number") parsed[key] = value;
      else if (typeof value === "string" && value.trim() !== "" && !Number.isNaN(Number(value))) parsed[key] = Number(value);
    }
  } else if (typeof sizeStock === "string") {
    try {
      const decoded = JSON.parse(sizeStock) as Record<string, unknown>;
      for (const [key, value] of Object.entries(decoded || {})) {
        if (typeof value === "number") parsed[key] = value;
        else if (typeof value === "string" && value.trim() !== "" && !Number.isNaN(Number(value))) parsed[key] = Number(value);
      }
    } catch {
      // ignore invalid JSON
    }
  }

  return parsed;
};

// Adapter to match the old Product interface used by ProductCard
export interface ProductVariantView {
  id: string;
  size: string;
  volumeMl: number | null;
  bottleShape: string | null;
  price: number | null;
  image: string;
  imageIsReference: boolean;
}

export interface ProductView {
  id: string;
  name: string;
  nameAr: string;
  description: string;
  descriptionAr: string;
  price: number;
  image: string;
  category: string;
  fragrance: string;
  topNotes: string[];
  heartNotes: string[];
  baseNotes: string[];
  fragranceFamily: string;
  sizes: string[];
  sizePrices: Record<string, number>;
  sizeStock: Record<string, number>;
  featured: boolean;
  isNew: boolean;
  bestSeller: boolean;
  stock: number;
  categoryName?: string;
  categoryNameAr?: string;
  variants: ProductVariantView[];
}

/**
 * Resolve the displayed price for a given product size.
 *
 * Lookup order (first hit wins):
 *   1. An exact key match in `sizePrices` (e.g. "100ml" -> 12000).
 *   2. A case/whitespace-insensitive key match (handles "100 ML" vs "100ml").
 *   3. The product `basePrice` as a final fallback — this is intentional for
 *      legacy products that have no per-size price map; for such products the
 *      base price IS the price for every size. Returning `basePrice` here is
 *      correct, not a bug.
 */
export const getVariantForSize = (product: ProductView, size: string): ProductVariantView | undefined => {
  const normalized = size.toLowerCase().replace(/\s+/g, '');
  return product.variants.find((variant) => variant.size.toLowerCase().replace(/\s+/g, '') === normalized);
};

export const getPriceForSize = (basePrice: number, size: string, sizePrices?: Record<string, number>): number => {
  if (sizePrices && Object.keys(sizePrices).length > 0) {
    if (sizePrices[size] !== undefined) return sizePrices[size];
    const normalized = size.toLowerCase().replace(/\s+/g, "");
    for (const [key, value] of Object.entries(sizePrices)) {
      if (key.toLowerCase().replace(/\s+/g, "") === normalized) return value;
    }
  }

  return basePrice;
};

export const dbToView = (p: DBProduct, categoryName?: string, categoryNameAr?: string): ProductView => {
  // Prefer the explicit `available_sizes` array from the API; fall back to the
  // raw `sizes` column which may be a JSON string or array.
  const sizes = parseSizes(p.available_sizes ?? p.sizes);

  return {
    id: p.id,
    name: p.name,
    nameAr: p.name_ar,
    description: p.description || "",
    descriptionAr: p.description_ar || "",
    price: p.price,
    image: resolveImageUrl(p.image_url, p.id),
    category: categoryName || "unisex",
    fragrance: p.fragrance || "oriental",
    // Fragrance note pyramid — parsed from JSON/array/string/null
    topNotes: parseNotesArray(p.top_notes),
    heartNotes: parseNotesArray(p.heart_notes),
    baseNotes: parseNotesArray(p.base_notes),
    fragranceFamily: p.fragrance_family || "",
    sizes,
    sizePrices: parseSizePrices(p.size_prices, sizes, p.price),
    sizeStock: parseSizeStock(p.size_stock),
    featured: p.featured || false,
    isNew: p.is_new || false,
    bestSeller: p.best_seller || false,
    stock: p.stock || 0,
    variants: Array.isArray(p.variants) ? p.variants.map((v) => ({
      id: String(v.id),
      size: String(v.size_label || '').trim(),
      volumeMl: v.volume_ml == null ? null : Number(v.volume_ml),
      bottleShape: v.bottle_shape || null,
      price: v.selling_price_default == null ? null : Number(v.selling_price_default),
      image: resolveImageUrl(v.image_url, p.id),
      imageIsReference: Boolean(v.image_is_reference),
    })).filter((v) => v.size !== '') : [],
    categoryName,
    categoryNameAr,
  };
};

export const useProducts = () => {
  const [products, setProducts] = useState<ProductView[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    const loadData = async () => {
      try {
        setLoading(true);
        setError(null);
        const [prodRes, catRes] = await Promise.all([
          window.fetch(`${apiBaseUrl}/api/products`, { headers: { Accept: "application/json" } }),
          window.fetch(`${apiBaseUrl}/api/categories`, { headers: { Accept: "application/json" } }),
        ]);

        if (!prodRes.ok) throw new Error(`Products request failed: ${prodRes.status}`);

        const prodData = await prodRes.json();
        // Categories are best-effort; a failure here should not block products.
        let categories: DBCategory[] = [];
        if (catRes.ok) {
          const catData = await catRes.json();
          categories = (catData?.categories || []) as DBCategory[];
        }
        const catMap = new Map(categories.map((c) => [c.id, c]));

        const items = ((prodData?.products || []) as DBProduct[]).map((p) => {
          const cat = p.category_id ? catMap.get(p.category_id) : null;
          return dbToView(p, cat?.slug || cat?.name || "unisex", cat?.name_ar);
        });

        if (!cancelled) setProducts(items);
      } catch (err) {
        if (!cancelled) {
          const message = err instanceof Error ? err.message : "Failed to load products";
          setError(message);
          setProducts([]);
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    loadData();
    return () => {
      cancelled = true;
    };
  }, []);

  return { products, loading, error };
};

export const useProduct = (id: string) => {
  const [product, setProduct] = useState<ProductView | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    const loadProduct = async () => {
      try {
        setLoading(true);
        setError(null);
        const response = await window.fetch(`${apiBaseUrl}/api/products/${id}`, {
          headers: { Accept: "application/json" },
        });
        if (!response.ok) throw new Error(`Product request failed: ${response.status}`);
        const payload = await response.json();
        const data = payload?.product as DBProduct | null;

        if (data) {
          let cat = null;
          if (data.category_id) {
            const catResponse = await window.fetch(`${apiBaseUrl}/api/categories`, {
              headers: { Accept: "application/json" },
            });
            if (catResponse.ok) {
              const catPayload = await catResponse.json();
              const categories = (catPayload?.categories || []) as DBCategory[];
              cat = categories.find((c) => c.id === data.category_id) || null;
            }
          }
          if (!cancelled) setProduct(dbToView(data, cat?.slug || cat?.name || "unisex", cat?.name_ar));
        } else if (!cancelled) {
          setProduct(null);
        }
      } catch (err) {
        if (!cancelled) {
          const message = err instanceof Error ? err.message : "Failed to load product";
          setError(message);
          setProduct(null);
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    loadProduct();
    return () => {
      cancelled = true;
    };
  }, [id]);

  return { product, loading, error };
};
