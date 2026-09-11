import { useLanguage } from "@/hooks/useLanguage";
import { Shield, Truck, Clock, HeadphonesIcon, CreditCard, Award } from "lucide-react";

interface TrustBadge {
  icon: React.ReactNode;
  title: { ar: string; en: string };
  description: { ar: string; en: string };
}

const TrustBadges = () => {
  const { lang } = useLanguage();

  const badges: TrustBadge[] = [
    {
      icon: <Shield className="w-6 h-6" />,
      title: { ar: "منتج أصلي 100%", en: "100% Authentic" },
      description: { ar: "ضمان الجودة والأصالة", en: "Quality & authenticity guaranteed" }
    },
    {
      icon: <Truck className="w-6 h-6" />,
      title: { ar: "شحن سريع", en: "Fast Shipping" },
      description: { ar: "توصيل خلال 2-4 أيام", en: "Delivery within 2-4 days" }
    },
    {
      icon: <Clock className="w-6 h-6" />,
      title: { ar: "دفع عند الاستلام", en: "Cash on Delivery" },
      description: { ar: "لا تدفع إلا عند الاستلام", en: "Pay only when you receive" }
    },
    {
      icon: <HeadphonesIcon className="w-6 h-6" />,
      title: { ar: "دعم 24/7", en: "24/7 Support" },
      description: { ar: "خدمة عملاء متاحة دائماً", en: "Customer service always available" }
    },
    {
      icon: <CreditCard className="w-6 h-6" />,
      title: { ar: "دفع آمن", en: "Secure Payment" },
      description: { ar: "حماية كاملة لبياناتك", en: "Complete data protection" }
    },
    {
      icon: <Award className="w-6 h-6" />,
      title: { ar: "جودة مضمونة", en: "Quality Guaranteed" },
      description: { ar: "تجربة عطور فاخرة", en: "Luxury fragrance experience" }
    }
  ];

  return (
    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 md:gap-6">
      {badges.map((badge, index) => (
        <div
          key={index}
          className="flex flex-col items-center text-center p-4 rounded-xl bg-card border border-border/50 hover:border-gold/30 transition-all duration-300 hover:shadow-lg"
        >
          <div className="text-gold mb-3">
            {badge.icon}
          </div>
          <h3 className="font-semibold text-sm md:text-base mb-1">
            {lang === "ar" ? badge.title.ar : badge.title.en}
          </h3>
          <p className="text-xs text-muted-foreground">
            {lang === "ar" ? badge.description.ar : badge.description.en}
          </p>
        </div>
      ))}
    </div>
  );
};

export default TrustBadges;
