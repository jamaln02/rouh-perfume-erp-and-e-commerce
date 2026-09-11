import { Helmet } from "react-helmet-async";
import { useLanguage } from "@/hooks/useLanguage";

const SITE_URL = "https://rouh-perfume.com";

interface SEOProps {
  title: string;
  description: string;
  path: string;
  ogType?: "website" | "article" | "product";
  image?: string;
  jsonLd?: object;
  keywords?: string;
  noIndex?: boolean;
}

const SEO = ({ title, description, path, ogType = "website", image, jsonLd, keywords, noIndex = false }: SEOProps) => {
  const { lang } = useLanguage();
  const url = `${SITE_URL}${path}`;
  const defaultImage = image || `${SITE_URL}/og-image.jpg`;
  
  const defaultKeywords = lang === "ar" 
    ? "عطور سورية, عطور فاخرة, عطور شرقية, عطور رجالية, عطور نسائية, عطور دمشقية"
    : "Syrian perfumes, luxury fragrances, oriental perfumes, men's cologne, women's perfume, Damascus scents";

  return (
    <Helmet>
      <title>{title}</title>
      <meta name="description" content={description} />
      <meta name="keywords" content={keywords || defaultKeywords} />
      <link rel="canonical" href={url} />
      
      {/* Open Graph */}
      <meta property="og:title" content={title} />
      <meta property="og:description" content={description} />
      <meta property="og:url" content={url} />
      <meta property="og:type" content={ogType} />
      <meta property="og:image" content={defaultImage} />
      <meta property="og:site_name" content="Rouh Perfume" />
      <meta property="og:locale" content={lang === "ar" ? "ar_SY" : "en_US"} />
      
      {/* Twitter Card */}
      <meta name="twitter:card" content="summary_large_image" />
      <meta name="twitter:title" content={title} />
      <meta name="twitter:description" content={description} />
      <meta name="twitter:image" content={defaultImage} />
      
      {/* Additional SEO */}
      <meta name="robots" content={noIndex ? "noindex, nofollow" : "index, follow"} />
      <meta name="author" content="Rouh Perfume" />
      <meta name="theme-color" content="#b8860b" />
      
      {/* Structured Data */}
      {jsonLd && (
        <script type="application/ld+json">{JSON.stringify(jsonLd)}</script>
      )}
      
      {/* Organization Schema */}
      <script type="application/ld+json">
        {JSON.stringify({
          "@context": "https://schema.org",
          "@type": "Organization",
          "name": "Rouh Perfume",
          "url": SITE_URL,
          "logo": `${SITE_URL}/logo.png`,
          "description": lang === "ar" 
            ? "روح هي علامة تجارية سورية فاخرة للعطور، نقدم أرقى العطور الشرقية والغربية"
            : "Rouh is a premium Syrian perfume brand offering the finest oriental and western fragrances",
          "address": {
            "@type": "PostalAddress",
            "addressLocality": "Damascus",
            "addressCountry": "SY"
          },
          "contactPoint": {
            "@type": "ContactPoint",
            "telephone": "+963933898625",
            "contactType": "customer service"
          }
        })}
      </script>
    </Helmet>
  );
};

export default SEO;