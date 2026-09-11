import { useLanguage } from "@/hooks/useLanguage";
import { motion } from "framer-motion";
import heroImage from "@/assets/hero-rouh-clean.png";
import { Sparkles } from "lucide-react";
import SEO from "@/components/SEO";

const About = () => {
  const { t, lang } = useLanguage();

  const sections = [
    {
      title: t("ourStory"),
      textAr: "تأسست روح في قلب دمشق، مستوحاة من التراث العريق للعطور الشرقية. نحن نجمع بين الأصالة والحداثة لنقدم عطوراً تعكس الروح الحقيقية لكل شخص.",
      textEn: "Rouh was founded in the heart of Damascus, inspired by the rich heritage of oriental perfumery. We blend tradition with modernity to create fragrances that reflect the true spirit of every individual.",
    },
    {
      title: t("ourVision"),
      textAr: "أن نكون العلامة التجارية الرائدة في صناعة العطور السورية والعربية، ونقدم للعالم عطوراً تحكي قصة الشرق بأسلوب عصري.",
      textEn: "To be the leading brand in Syrian and Arab perfumery, bringing the story of the Orient to the world through contemporary fragrances.",
    },
    {
      title: t("ourMission"),
      textAr: "نلتزم باستخدام أجود المكونات الطبيعية وأحدث تقنيات التصنيع لإنتاج عطور فاخرة بأسعار عادلة، مع الحفاظ على التراث العطري السوري.",
      textEn: "We are committed to using the finest natural ingredients and latest manufacturing techniques to produce luxury fragrances at fair prices, while preserving Syrian perfumery heritage.",
    },
  ];

  return (
    <div className="min-h-screen pt-20 lg:pt-24">
      <SEO
        title={lang === "ar" ? "من نحن | روح" : "About Us | Rouh"}
        description={lang === "ar" ? "قصة روح ورؤيتنا ورسالتنا في صناعة العطور السورية الفاخرة من قلب دمشق." : "The Rouh story, vision, and mission in crafting Syrian luxury perfumes from the heart of Damascus."}
        path="/about"
      />
      {/* Hero */}
      <div className="relative h-72 md:h-96 overflow-hidden">
        <motion.img
          initial={{ scale: 1.1 }}
          animate={{ scale: 1 }}
          transition={{ duration: 1.2 }}
          src={heroImage}
          alt="About Rouh"
          className="w-full h-full object-cover"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-background via-foreground/40 to-foreground/60 flex items-center justify-center">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="text-center"
          >
            <Sparkles className="text-gold mx-auto mb-4" size={28} />
            <h1 className="font-display text-5xl md:text-6xl font-bold text-gradient-gold">{t("aboutTitle")}</h1>
          </motion.div>
        </div>
      </div>

      <div className="container mx-auto px-4 lg:px-8 py-20">
        <div className="max-w-3xl mx-auto space-y-16">
          {sections.map((section, i) => (
            <motion.div
              key={i}
              initial={{ opacity: 0, y: 30 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ delay: i * 0.15, duration: 0.6 }}
              className="relative"
            >
              <div className="absolute -start-6 top-0 w-1 h-full bg-gradient-to-b from-gold to-transparent rounded-full hidden md:block" />
              <h2 className="font-display text-3xl font-bold text-gold mb-5">{section.title}</h2>
              <p className="text-muted-foreground leading-relaxed text-lg">
                {lang === "ar" ? section.textAr : section.textEn}
              </p>
            </motion.div>
          ))}
        </div>
      </div>
    </div>
  );
};

export default About;
