import { MessageCircle, Sparkles } from "lucide-react";
import { useLanguage } from "@/hooks/useLanguage";

/**
 * CustomOrderNotice — a professional banner that invites the customer to
 * contact the store via WhatsApp if they want a **custom / personalized
 * order** (e.g. a bespoke fragrance blend, a special size, a gift set, or a
 * bulk order of 10+ perfumes).
 *
 * The WhatsApp number is centralised here so it can be updated in one place.
 * It mirrors the number used in WhatsAppButton / Footer (+963 933 898 625).
 */
const WHATSAPP_NUMBER = "963933898625";

const WHATSAPP_LINK = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(
  "مرحباً، أرغب بطلب عطر مخصص من روح / Hello, I'd like to place a custom perfume order with Rouh"
)}`;

interface CustomOrderNoticeProps {
  /** Visual variant — "banner" (full-width card) or "compact" (inline pill). */
  variant?: "banner" | "compact";
  className?: string;
}

const CustomOrderNotice = ({ variant = "banner", className = "" }: CustomOrderNoticeProps) => {
  const { lang } = useLanguage();
  const ar = lang === "ar";

  if (variant === "compact") {
    return (
      <a
        href={WHATSAPP_LINK}
        target="_blank"
        rel="noopener noreferrer"
        className={`inline-flex items-center gap-2 rounded-full border border-emerald-300/60 bg-emerald-50/60 px-4 py-2 text-sm font-medium text-emerald-800 transition-colors hover:bg-emerald-100/80 dark:border-emerald-700/40 dark:bg-emerald-950/30 dark:text-emerald-300 ${className}`}
      >
        <MessageCircle className="h-4 w-4" />
        {ar ? "طلب مخصص؟ راسلنا على واتساب" : "Custom order? Message us on WhatsApp"}
      </a>
    );
  }

  return (
    <div
      className={`flex flex-col sm:flex-row items-start sm:items-center gap-3 rounded-xl border border-emerald-300/50 bg-gradient-to-r from-emerald-50/80 to-gold/5 p-4 dark:border-emerald-700/40 dark:from-emerald-950/30 ${className}`}
    >
      <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-600/15 text-emerald-700 dark:text-emerald-400">
        <Sparkles className="h-5 w-5" />
      </div>
      <div className="flex-1">
        <p className="font-semibold text-foreground">
          {ar ? "هل ترغب بطلب عطر مخصص؟" : "Looking for a custom perfume order?"}
        </p>
        <p className="text-sm text-muted-foreground mt-0.5">
          {ar
            ? "إذا أردت خلطة عطرية حصرية، حجم خاص، أو طلبية كبيرة — تواصل معنا مباشرةً عبر واتساب وسنجهّز طلبك حسب رغبتك."
            : "For a bespoke blend, a special size, or a bulk order — reach out to us directly on WhatsApp and we'll craft your order to your liking."}
        </p>
      </div>
      <a
        href={WHATSAPP_LINK}
        target="_blank"
        rel="noopener noreferrer"
        className="inline-flex items-center gap-2 rounded-full bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
      >
        <MessageCircle className="h-4 w-4" />
        {ar ? "راسلنا الآن" : "Chat with us"}
      </a>
    </div>
  );
};

export default CustomOrderNotice;
