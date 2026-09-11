import { Link } from "react-router-dom";
import { useLanguage } from "@/hooks/useLanguage";
import { Phone, Mail, MapPin, MessageCircle, Instagram, Facebook, Twitter } from "lucide-react";
import TrustBadges from "./TrustBadges";

const Footer = () => {
  const { t, lang } = useLanguage();

  return (
    <footer className="bg-gradient-burgundy border-t border-gold/10">
      <div className="container mx-auto px-4 lg:px-8 py-16">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-10">
          {/* Brand */}
          <div className="md:col-span-1">
            <h3 className="font-display text-3xl font-bold text-gradient-gold mb-4">{t("brand")}</h3>
            <p className="text-cream/50 text-sm leading-relaxed mb-6">
              {t("aboutText")}
            </p>
            <a
              href="https://wa.me/963933898625"
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-2 text-[#25D366] hover:underline text-sm"
            >
              <MessageCircle size={16} />
              {lang === "ar" ? "تواصل عبر واتساب" : "Chat on WhatsApp"}
            </a>

            <div className="mt-6">
              <p className="text-cream/60 text-xs uppercase tracking-wider mb-3">{t("followUs")}</p>
              <div className="flex items-center gap-3">
                <a
                  href="https://instagram.com/rouh_.parfum"
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label="Instagram"
                  className="w-9 h-9 rounded-full bg-cream/5 hover:bg-gold hover:text-accent-foreground text-cream/70 flex items-center justify-center transition-colors"
                >
                  <Instagram size={16} />
                </a>
                <a
                  href="https://facebook.com/rouh.perfumes"
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label="Facebook"
                  className="w-9 h-9 rounded-full bg-cream/5 hover:bg-gold hover:text-accent-foreground text-cream/70 flex items-center justify-center transition-colors"
                >
                  <Facebook size={16} />
                </a>
                <a
                  href="https://twitter.com/rouh_perfumes"
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label="Twitter / X"
                  className="w-9 h-9 rounded-full bg-cream/5 hover:bg-gold hover:text-accent-foreground text-cream/70 flex items-center justify-center transition-colors"
                >
                  <Twitter size={16} />
                </a>
                <a
                  href="https://wa.me/963933898625"
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label="WhatsApp"
                  className="w-9 h-9 rounded-full bg-cream/5 hover:bg-[#25D366] hover:text-white text-cream/70 flex items-center justify-center transition-colors"
                >
                  <MessageCircle size={16} />
                </a>
              </div>
            </div>
          </div>

          {/* Quick Links */}
          <div>
            <h4 className="text-gold font-semibold mb-5 text-sm tracking-wider uppercase">
              {lang === "ar" ? "روابط سريعة" : "Quick Links"}
            </h4>
            <div className="flex flex-col gap-3">
              {[
                { to: "/shop", label: t("shop") },
                { to: "/about", label: t("about") },
                { to: "/contact", label: t("contact") },
                { to: "/cart", label: t("cart") },
              ].map((link) => (
                <Link key={link.to} to={link.to} className="text-cream/50 hover:text-gold text-sm transition-colors">
                  {link.label}
                </Link>
              ))}
            </div>
          </div>

          {/* Categories */}
          <div>
            <h4 className="text-gold font-semibold mb-5 text-sm tracking-wider uppercase">
              {t("collections")}
            </h4>
            <div className="flex flex-col gap-3">
              {["men", "women", "unisex"].map((cat) => (
                <Link key={cat} to={`/shop?category=${cat}`} className="text-cream/50 hover:text-gold text-sm transition-colors">
                  {t(cat)}
                </Link>
              ))}
            </div>
          </div>

          {/* Contact */}
          <div>
            <h4 className="text-gold font-semibold mb-5 text-sm tracking-wider uppercase">
              {t("contact")}
            </h4>
            <div className="flex flex-col gap-4 text-sm text-cream/50">
              <a href="tel:+963933898625" className="flex items-center gap-3 hover:text-gold transition-colors">
                <Phone size={16} className="text-gold shrink-0" />
                <span dir="ltr">+963 933 898 625</span>
              </a>
              <a href="mailto:contact@rouh.shop" className="flex items-center gap-3 hover:text-gold transition-colors">
                <Mail size={16} className="text-gold shrink-0" />
                <span>contact@rouh.shop</span>
              </a>
              <a href="https://maps.google.com/?q=Damascus,Syria" target="_blank" rel="noopener noreferrer" className="flex items-center gap-3 hover:text-gold transition-colors">
                <MapPin size={16} className="text-gold shrink-0" />
                <span>{lang === "ar" ? "دمشق، سوريا" : "Damascus, Syria"}</span>
              </a>
            </div>
          </div>
        </div>

        {/* Trust Badges */}
        <div className="mt-12 pt-8 border-t border-cream/10">
          <TrustBadges />
        </div>

        <div className="mt-12 pt-8 border-t border-cream/10 text-center text-sm text-cream/30 space-y-2">
          <p>© 2026 {t("brand")}. {t("rights")}.</p>
          <p className="text-cream/40">
            {lang === "ar" ? "الموقع طُوّر بواسطة جمال نبعه" : "Website developed by Jamal Nabaa"}
          </p>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
