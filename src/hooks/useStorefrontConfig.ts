import { useEffect, useState } from "react";

export type StoreCity = { key: string; ar: string; en: string; shipping: number };
export type StorefrontConfig = {
  shipping: { free_threshold: number; cities: StoreCity[] };
  loyalty: { enabled: boolean; earn_amount: number; earn_points: number; redeem_points: number; redeem_discount: number };
  quiz: { discount_percent: number };
  social: { instagram: string };
  offers: { title_ar: string; title_en: string; subtitle_ar: string; subtitle_en: string };
};

export const defaultStorefrontConfig: StorefrontConfig = {
  shipping: {
    free_threshold: 5000,
    cities: [
      ["damascus", "دمشق", "Damascus", 250], ["aleppo", "حلب", "Aleppo", 80], ["homs", "حمص", "Homs", 50],
      ["latakia", "اللاذقية", "Latakia", 70], ["tartus", "طرطوس", "Tartus", 70], ["hama", "حماة", "Hama", 60],
      ["as-suwayda", "السويداء", "As-Suwayda", 50], ["daraa", "درعا", "Daraa", 50], ["deir ez-zor", "دير الزور", "Deir ez-Zor", 100],
      ["raqqa", "الرقة", "Raqqa", 100], ["al-hasakah", "الحسكة", "Al-Hasakah", 120], ["idlib", "إدلب", "Idlib", 90],
      ["quneitra", "القنيطرة", "Quneitra", 60],
    ].map(([key, ar, en, shipping]) => ({ key: key as string, ar: ar as string, en: en as string, shipping: Number(shipping) })),
  },
  loyalty: { enabled: true, earn_amount: 1000, earn_points: 1, redeem_points: 100, redeem_discount: 500 },
  quiz: { discount_percent: 10 },
  social: { instagram: "rouh_.parfum" },
  offers: {
    title_ar: "العروض والمجموعات", title_en: "Bundles & Offers",
    subtitle_ar: "وفر أكثر مع مجموعاتنا الحصرية", subtitle_en: "Save more with our exclusive bundles",
  },
};

let cachedConfig: StorefrontConfig | null = null;
let pendingRequest: Promise<StorefrontConfig> | null = null;

const loadConfig = async (): Promise<StorefrontConfig> => {
  if (cachedConfig) return cachedConfig;
  if (pendingRequest) return pendingRequest;
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "");
  pendingRequest = fetch(`${apiBaseUrl}/api/storefront-config`, { headers: { Accept: "application/json" } })
    .then(async (res) => {
      if (!res.ok) throw new Error("config request failed");
      const data = await res.json();
      const next: StorefrontConfig = {
        ...defaultStorefrontConfig,
        ...data,
        shipping: { ...defaultStorefrontConfig.shipping, ...data?.shipping, cities: Array.isArray(data?.shipping?.cities) ? data.shipping.cities : defaultStorefrontConfig.shipping.cities },
        loyalty: { ...defaultStorefrontConfig.loyalty, ...data?.loyalty },
        quiz: { ...defaultStorefrontConfig.quiz, ...data?.quiz },
        social: { ...defaultStorefrontConfig.social, ...data?.social },
        offers: { ...defaultStorefrontConfig.offers, ...data?.offers },
      };
      cachedConfig = next;
      return next;
    })
    .catch(() => defaultStorefrontConfig)
    .finally(() => { pendingRequest = null; });
  return pendingRequest;
};

export const useStorefrontConfig = () => {
  const [config, setConfig] = useState<StorefrontConfig>(cachedConfig || defaultStorefrontConfig);
  const [loading, setLoading] = useState(!cachedConfig);

  useEffect(() => {
    let alive = true;
    void loadConfig().then((next) => {
      if (!alive) return;
      setConfig(next);
      setLoading(false);
    });
    return () => { alive = false; };
  }, []);

  return { config, loading };
};

export const invalidateStorefrontConfig = () => { cachedConfig = null; };
