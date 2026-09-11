import { useLanguage } from "@/hooks/useLanguage";
import { ChevronDown, ChevronUp } from "lucide-react";
import { useState } from "react";
import SEO from "@/components/SEO";

const FAQ = () => {
  const { lang } = useLanguage();
  const [openIndex, setOpenIndex] = useState<number | null>(null);

  const faqs = [
    {
      question: { ar: "كيف يمكنني التأكد من أن العطور أصلية؟", en: "How can I be sure the perfumes are authentic?" },
      answer: { ar: "جميع عطورنا من تصنيع روح، بتركيبات مختارة بعناية وجودة نحرص عليها في كل تفاصيل المنتج. نحن نضمن جودة عطورنا ونقدّمها لكم بثقة، لأن كل منتج يحمل اسم روح ويعبّر عن معاييرنا في الجودة والإتقان.", en: "All our perfumes are crafted by ROUH, using carefully selected formulations and maintaining the quality we strive for in every detail of our products. We stand behind the quality of our perfumes and offer them to you with confidence, because every product carries the ROUH name and reflects our standards of quality and craftsmanship." }
    },
    {
      question: { ar: "ما هي طرق الدفع المتاحة؟", en: "What payment methods are available?" },
      answer: { ar: "نقبل الدفع عند الاستلام في جميع المناطق السورية. كما يمكنكم الدفع عبر تحويل بنكي أو محافظ إلكترونية.", en: "We accept cash on delivery across all Syrian regions. You can also pay via bank transfer or e-wallets." }
    },
    {
      question: { ar: "كم تستغرق عملية الشحن؟", en: "How long does shipping take?" },
      answer: { ar: "التوصيل عادة يستغرق 1-2 أيام عمل داخل دمشق، و3-5 أيام للمحافظات الأخرى.", en: "Delivery usually takes 1-2 business days within Damascus, and 3-5 days for other governorates." }
    },
    {
      question: { ar: "هل يمكنني إرجاع المنتج إذا لم يعجبني؟", en: "Can I return the product if I don't like it?" },
      answer: { ar: "نعم، يمكنك إرجاع المنتج خلال 7 أيام من الاستلام بشرط أن يكون في حالته الأصلية. يرجى التواصل معنا للتعرف على تفاصيل عملية الإرجاع.", en: "Yes, you can return the product within 7 days of delivery, provided it's in its original condition. Please contact us for return details." }
    },
    {
      question: { ar: "كيف يمكنني تتبع طلبي؟", en: "How can I track my order?" },
      answer: { ar: "سوف تتلقى رسالة عبر واتساب عند شحن طلبك مع رقم التتبع. يمكنك أيضاً استخدام صفحة تتبع الطلبات في موقعنا.", en: "You will receive a WhatsApp message when your order is shipped with tracking number. You can also use our order tracking page on the website." }
    },
    {
      question: { ar: "هل هناك حد أدنى للطلب؟", en: "Is there a minimum order amount?" },
      answer: { ar: "لا يوجد حد أدنى للطلب. يمكنك شراء أي منتج بكميات فردية.", en: "There is no minimum order amount. You can purchase any product in individual quantities." }
    },
    {
      question: { ar: "هل العطور مناسبة للبشرة الحساسة؟", en: "Are the perfumes suitable for sensitive skin?" },
      answer: { ar: "عطورنا عالية الجودة ومناسبة لمعظم أنواع البشرة. ومع ذلك، إذا كانت بشرتك حساسة جداً، ننصحك باختبار المنتج على منطقة صغيرة قبل الاستخدام.", en: "Our perfumes are high quality and suitable for most skin types. However, if you have very sensitive skin, we recommend testing the product on a small area before use." }
    }
  ];

  const toggleFAQ = (index: number) => {
    setOpenIndex(openIndex === index ? null : index);
  };

  return (
    <div className="min-h-screen pt-20 lg:pt-24">
<SEO 
        title={lang === "ar" ? "الأسئلة الشائعة" : "FAQ"}
        description={lang === "ar" ? "إجابات على الأسئلة الشائعة حول عطور روح" : "Frequently asked questions about Rouh perfumes"}
        path="/faq"
      />
      
      <div className="container mx-auto px-4 lg:px-8 py-12">
        <div className="max-w-3xl mx-auto">
          <h1 className="font-display text-4xl md:text-5xl font-bold text-gradient-gold mb-4 text-center">
            {lang === "ar" ? "الأسئلة الشائعة" : "Frequently Asked Questions"}
          </h1>
          <p className="text-muted-foreground text-center mb-12">
            {lang === "ar" ? "إجابات على أكثر الأسئلة شيوعاً حول منتجاتنا وخدماتنا" : "Answers to the most common questions about our products and services"}
          </p>

          <div className="space-y-4">
            {faqs.filter((faq) => faq && faq.question && faq.answer).map((faq, index) => (
              <div
                key={index}
                className="border border-border/50 rounded-xl overflow-hidden hover:border-gold/30 transition-colors"
              >
                <button
                  onClick={() => toggleFAQ(index)}
                  className="w-full flex items-center justify-between p-6 text-left bg-card hover:bg-card/80 transition-colors"
                  aria-expanded={openIndex === index}
                >
                  <span className="font-semibold text-lg pr-4">
                    {lang === "ar" ? faq.question.ar : faq.question.en}
                  </span>
                  {openIndex === index ? (
                    <ChevronUp className="text-gold shrink-0" />
                  ) : (
                    <ChevronDown className="text-muted-foreground shrink-0" />
                  )}
                </button>
                {openIndex === index && (
                  <div className="px-6 pb-6 pt-0">
                    <p className="text-muted-foreground leading-relaxed">
                      {lang === "ar" ? faq.answer.ar : faq.answer.en}
                    </p>
                  </div>
                )}
              </div>
            ))}
          </div>

          <div className="mt-12 text-center">
            <p className="text-muted-foreground mb-4">
              {lang === "ar" ? "لم تجد إجابة على سؤالك؟" : "Didn't find the answer to your question?"}
            </p>
            <a
              href="https://wa.me/963933898625"
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-2 bg-gold text-accent-foreground px-6 py-3 rounded-full font-semibold hover:bg-gold-dark transition-colors"
            >
              {lang === "ar" ? "تواصل معنا عبر واتساب" : "Contact us on WhatsApp"}
            </a>
          </div>
        </div>
      </div>
    </div>
  );
};

export default FAQ;
