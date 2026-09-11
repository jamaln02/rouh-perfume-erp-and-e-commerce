import { useMemo } from "react";
import { useProducts } from "./useProducts";

export const useRecommendations = (currentProductId?: string, limit: number = 4) => {
  const { products } = useProducts();

  // Get recently viewed from localStorage
  const getRecentlyViewed = () => {
    try {
      const stored = localStorage.getItem("rouh_recently_viewed");
      if (stored) {
        const items = JSON.parse(stored);
        return items.map((item: { id: string }) => item.id);
      }
    } catch (error) {
    }
    return [];
  };

  const recentlyViewedIds = getRecentlyViewed();

  const recommendations = useMemo(() => {
    if (!products.length) return [];

    // Get recently viewed product categories
    const recentCategories = recentlyViewedIds
      .filter(id => id !== currentProductId)
      .map(id => {
        const product = products.find(p => p.id === id);
        return product?.category;
      })
      .filter(Boolean);

    // Get current product category
    const currentProduct = products.find(p => p.id === currentProductId);
    const currentCategory = currentProduct?.category;

    // Filter out current product
    const availableProducts = products.filter(p => p.id !== currentProductId);

    // Score products based on recommendations
    const scored = availableProducts.map(product => {
      let score = 0;

      // Same category as current product
      if (product.category === currentCategory) {
        score += 3;
      }

      // Same category as recently viewed
      if (recentCategories.includes(product.category)) {
        score += 2;
      }

      // Best seller bonus
      if (product.bestSeller) {
        score += 1;
      }

      // New product bonus
      if (product.isNew) {
        score += 1;
      }

      return { product, score };
    });

    // Sort by score and take top N
    return scored
      .sort((a, b) => b.score - a.score)
      .slice(0, limit)
      .map(item => item.product);
  }, [products, recentlyViewedIds, currentProductId, limit]);

  return recommendations;
};
