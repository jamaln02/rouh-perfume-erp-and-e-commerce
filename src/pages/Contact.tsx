import { useState } from "react";
import { useLanguage } from "@/hooks/useLanguage";
import { Phone, Mail, MapPin, Send } from "lucide-react";
import { toast } from "sonner";
import { motion } from "framer-motion";
import SEO from "@/components/SEO";

const Contact = () => {
  const { t, lang } = useLanguage();
  const [form, setForm] = useState({ name: "", email: "", message: "" });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.name || !form.email || !form.message) {
      toast.error(lang === "ar" ? "يرجى ملء جميع الحقول" : "Please fill all fields");
      return;
    }
    toast.success(lang === "ar" ? "تم إرسال رسالتك بنجاح!" : "Message sent successfully!");
    setForm({ name: "", email: "", message: "" });
  };

  const inputClass = "w-full bg-card text-foreground px-5 py-3.5 rounded-xl border border-border outline-none focus:ring-2 focus:ring-gold placeholder:text-muted-foreground transition-shadow";

  return (
    <div className="min-h-screen pt-20 lg:pt-24">
      <SEO
        title={lang === "ar" ? "تواصل معنا | روح" : "Contact Us | Rouh"}
        description={lang === "ar" ? "تواصل مع فريق روح للدعم والاستفسارات حول العطور والطلبات." : "Get in touch with the Rouh team for support and inquiries about perfumes and orders."}
        path="/contact"
      />
      <div className="container mx-auto px-4 lg:px-8 py-16">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="text-center mb-14"
        >
          <h1 className="font-display text-4xl md:text-5xl font-bold text-gradient-gold mb-4">
            {t("contactTitle")}
          </h1>
          <p className="text-muted-foreground max-w-md mx-auto">
            {lang === "ar"
              ? "نحن هنا لمساعدتك. لا تتردد في التواصل معنا"
              : "We're here to help. Don't hesitate to reach out"}
          </p>
        </motion.div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 max-w-5xl mx-auto">
          {/* Contact info */}
          <motion.div
            initial={{ opacity: 0, x: -20 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: 0.2 }}
            className="space-y-8"
          >
            <div className="space-y-6">
              {[
                { icon: Phone, label: t("phone"), value: "+963 934 436 980" },
                { icon: Mail, label: t("email"), value: "contact@rouh.shop" },
                { icon: MapPin, label: t("address"), value: lang === "ar" ? "دمشق، سوريا" : "Damascus, Syria" },
              ].map((item, i) => (
                <motion.div
                  key={i}
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: 0.3 + i * 0.1 }}
                  className="flex items-start gap-4 p-5 bg-card rounded-2xl border border-border/50 hover:border-gold/30 transition-colors"
                >
                  <div className="w-12 h-12 rounded-xl bg-gold/10 flex items-center justify-center shrink-0">
                    <item.icon size={22} className="text-gold" />
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground mb-1">{item.label}</p>
                    <p className="font-semibold text-foreground" dir="ltr">{item.value}</p>
                  </div>
                </motion.div>
              ))}
            </div>
          </motion.div>

          {/* Form */}
          <motion.form
            initial={{ opacity: 0, x: 20 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: 0.3 }}
            onSubmit={handleSubmit}
            className="space-y-5 bg-card rounded-2xl border border-border/50 p-8"
          >
            <div>
              <label htmlFor="contact-name" className="sr-only">{t("name")}</label>
              <input
                id="contact-name"
                type="text"
                placeholder={t("name")}
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                className={inputClass}
              />
            </div>
            <div>
              <label htmlFor="contact-email" className="sr-only">{t("email")}</label>
              <input
                id="contact-email"
                type="email"
                placeholder={t("email")}
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
                className={inputClass}
              />
            </div>
            <div>
              <label htmlFor="contact-message" className="sr-only">{t("message")}</label>
              <textarea
                id="contact-message"
                placeholder={t("message")}
                value={form.message}
                onChange={(e) => setForm({ ...form, message: e.target.value })}
                className={`${inputClass} h-36 resize-none`}
              />
            </div>
            <button
              type="submit"
              className="w-full flex items-center justify-center gap-2 bg-gradient-gold text-accent-foreground py-4 rounded-xl font-semibold shadow-gold hover:shadow-gold-lg transition-all duration-300 text-lg"
            >
              <Send size={18} />
              {t("send")}
            </button>
          </motion.form>
        </div>
      </div>
    </div>
  );
};

export default Contact;
