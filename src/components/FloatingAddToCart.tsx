import { useCart } from "@/hooks/useCart";
import { useLanguage } from "@/hooks/useLanguage";
import { ShoppingBag } from "lucide-react";
import { motion, AnimatePresence } from "framer-motion";
import { Link, useLocation } from "react-router-dom";

const FloatingAddToCart = () => {
  const { totalItems } = useCart();
  const { lang } = useLanguage();
  const location = useLocation();

  // Only show on product detail pages
  const isProductPage = location.pathname.startsWith('/product/');
  
  if (!isProductPage || totalItems === 0) return null;

  return (
    <AnimatePresence>
      {isProductPage && (
        <motion.div
          initial={{ opacity: 0, y: 100 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0, y: 100 }}
          className="fixed bottom-6 left-6 right-6 md:left-auto md:right-6 md:w-auto z-50"
        >
          <Link
            to="/cart"
            className="flex items-center justify-center gap-3 bg-gold text-accent-foreground px-6 py-4 rounded-full shadow-xl hover:bg-gold-dark transition-colors font-semibold"
          >
            <ShoppingBag size={20} />
            <span>{lang === "ar" ? "عرض السلة" : "View Cart"}</span>
            <span className="bg-accent-foreground text-gold px-2 py-0.5 rounded-full text-sm font-bold">
              {totalItems}
            </span>
          </Link>
        </motion.div>
      )}
    </AnimatePresence>
  );
};

export default FloatingAddToCart;
