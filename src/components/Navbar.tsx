import { withAuthHeaders } from "@/lib/auth";
import { useState, useEffect } from "react";
import { Link, useLocation } from "react-router-dom";
import { ShoppingBag, Menu, X, Globe, Search, User, Shield, Heart, Sparkles, Scale } from "lucide-react";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger, DropdownMenuSeparator, DropdownMenuLabel } from "@/components/ui/dropdown-menu";
import { useLanguage } from "@/hooks/useLanguage";
import { useCart } from "@/hooks/useCart";
import { useWishlist } from "@/hooks/useWishlist";
import { useComparison } from "@/hooks/useComparison";
import { useAuth } from "@/hooks/useAuth";
import { motion, AnimatePresence } from "framer-motion";
import AdvancedSearch from "@/components/AdvancedSearch";
import rouhLogo from "@/assets/rouh-logo-trimmed.png";

const Navbar = () => {
  const { t, lang, setLang } = useLanguage();
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "");
  const { totalItems } = useCart();
  const { count: wishlistCount } = useWishlist();
  const { count: comparisonCount } = useComparison();
  const { user, isAdmin, signOut } = useAuth();
  const [mobileOpen, setMobileOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const [loyaltyPoints, setLoyaltyPoints] = useState<number | null>(null);
  const location = useLocation();

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 30);
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  // Close mobile menu on route change
  useEffect(() => {
    setMobileOpen(false);
    setSearchOpen(false);
  }, [location.pathname]);

  useEffect(() => {
    if (!user) { setLoyaltyPoints(null); return; }
    fetch(`${apiBaseUrl}/api/profiles/${user.id}`, { headers: withAuthHeaders({ Accept: "application/json" }) })
      .then((res) => res.json())
      .then((data) => setLoyaltyPoints(Number(data?.profile?.loyalty_points || 0)))
      .catch(() => setLoyaltyPoints(0));
  }, [apiBaseUrl, user]);

  const links = [
    { to: "/", label: t("home") },
    { to: "/shop", label: t("shop") },
    { to: "/bundles", label: lang === "ar" ? "العروض" : "Bundles" },
    { to: "/quiz", label: lang === "ar" ? "اختبار العطر" : "Perfume Quiz" },
    { to: "/track", label: t("trackOrder") },
    { to: "/faq", label: lang === "ar" ? "الأسئلة الشائعة" : "FAQ" },
    { to: "/about", label: t("about") },
    { to: "/contact", label: t("contact") },
  ];

    const navBg = `bg-gradient-burgundy backdrop-blur-2xl border-b border-gold/10 shadow-[0_2px_20px_-4px_rgba(0,0,0,0.18)]`;
  const textColor = "text-cream";

  return (
    <nav className={`fixed top-0 left-0 right-0 z-50 transition-all duration-700 ${navBg}`}>
<div className="container mx-auto px-3 sm:px-4 xl:px-8">
        <div className="relative flex items-center justify-between gap-3 h-16 xl:h-20">
          {/* Mobile logo (left) */}
          <Link
            to="/"
            className="xl:hidden flex items-center justify-center shrink-0"
            aria-label={t("brand")}
          >
            <img
              src={rouhLogo}
              alt={t("brand")}
              className="h-9 sm:h-11 w-auto object-contain saturate-110 contrast-110 drop-shadow-[0_4px_12px_rgba(201,168,76,0.4)]"
            />
          </Link>

          {/* Desktop logo (left) */}
          <Link
            to="/"
            className="hidden xl:flex items-center justify-center shrink-0 mr-2"
            aria-label={t("brand")}
          >
            <img
              src={rouhLogo}
              alt={t("brand")}
              className="h-[54px] lg:h-14 w-auto object-contain saturate-110 contrast-110 drop-shadow-[0_4px_12px_rgba(201,168,76,0.4)]"
            />
          </Link>

{/* Desktop links (center) */}
          <div className="hidden xl:flex absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 items-center justify-center gap-1">
            {links.map((link) => (
              <Link
                key={link.to}
                to={link.to}
                className={`relative px-3 xl:px-4 py-2 text-sm font-medium rounded-lg transition-all duration-300 whitespace-nowrap ${
                  location.pathname === link.to
                    ? "text-primary"
                    : `${textColor} hover:text-primary hover:bg-primary/5`
                }`}
              >
                {link.label}
                {location.pathname === link.to && (
                  <motion.div
                    layoutId="activeNav"
                    className="absolute bottom-0 left-2 right-2 h-0.5 bg-primary rounded-full"
                    transition={{ type: "spring", stiffness: 400, damping: 30 }}
                  />
                )}
              </Link>
            ))}
          </div>

{/* Actions (desktop only) */}
          <div className="relative z-20 hidden xl:flex items-center justify-end gap-0.5 sm:gap-1 lg:gap-2">
            <button
              onClick={() => setSearchOpen(!searchOpen)}
              className={`p-2 rounded-lg ${textColor} hover:text-primary hover:bg-primary/5 transition-all duration-200 min-h-[44px] min-w-[44px] flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-gold focus:ring-offset-2`}
              aria-label="Search"
              aria-expanded={searchOpen}
            >
              <Search size={18} />
            </button>

            <button
              onClick={() => setLang(lang === "ar" ? "en" : "ar")}
              className={`p-2 rounded-lg ${textColor} hover:text-primary hover:bg-primary/5 transition-all duration-200 flex items-center gap-1 min-h-[44px] min-w-[44px] focus:outline-none focus:ring-2 focus:ring-gold focus:ring-offset-2`}
              aria-label="Switch language"
            >
              <Globe size={18} />
              <span className="hidden sm:inline text-xs font-semibold">{lang === "ar" ? "EN" : "عربي"}</span>
            </button>

            {user ? (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <button
                    className={`p-2 rounded-lg ${textColor} hover:text-primary hover:bg-primary/5 transition-all duration-200`}
                    aria-label="Account"
                  >
                    <User size={18} />
                  </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-56 bg-background">
                  <DropdownMenuLabel className="truncate">{user.email}</DropdownMenuLabel>
                  {loyaltyPoints !== null && (
                    <div className="mx-2 my-1 rounded-md bg-gradient-to-r from-primary/15 to-primary/5 border border-primary/20 px-3 py-2 flex items-center gap-2">
                      <Sparkles className="h-4 w-4 text-primary" />
                      <div className="flex-1">
                        <p className="text-[10px] text-muted-foreground leading-none">{lang === "ar" ? "نقاط الولاء" : "Loyalty Points"}</p>
                        <p className="text-sm font-bold text-primary leading-tight">{loyaltyPoints.toLocaleString()}</p>
                      </div>
                    </div>
                  )}
                  <DropdownMenuSeparator />
                  {isAdmin && (
                    <DropdownMenuItem asChild>
                      <Link to="/admin" className="cursor-pointer">
                        <Shield className="h-4 w-4 me-2" />
                        {lang === "ar" ? "لوحة التحكم" : "Admin Dashboard"}
                      </Link>
                    </DropdownMenuItem>
                  )}
                  <DropdownMenuItem asChild>
                    <Link to="/track" className="cursor-pointer">{lang === "ar" ? "تتبع طلباتي" : "Track My Orders"}</Link>
                  </DropdownMenuItem>
                  <DropdownMenuItem asChild>
                    <Link to="/wishlist" className="cursor-pointer">{lang === "ar" ? "المفضلة" : "Wishlist"}</Link>
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem onClick={signOut} className="text-destructive cursor-pointer">
                    {lang === "ar" ? "تسجيل الخروج" : "Sign Out"}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            ) : (
              <Link
                to="/auth"
                className={`p-2 rounded-lg ${textColor} hover:text-primary hover:bg-primary/5 transition-all duration-200`}
                aria-label="Sign In"
              >
                <User size={18} />
              </Link>
            )}

            <Link to="/cart" className={`relative p-2 rounded-lg ${textColor} hover:text-primary hover:bg-primary/5 transition-all duration-200 min-h-[44px] min-w-[44px] flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-gold focus:ring-offset-2`} aria-label={`${t("cart")} (${totalItems} items)`}>
              <ShoppingBag size={18} />
              {totalItems > 0 && (
                <motion.span
                  initial={{ scale: 0 }}
                  animate={{ scale: 1 }}
                  className="absolute -top-0.5 -right-0.5 bg-primary text-primary-foreground text-[10px] w-4.5 h-4.5 min-w-[18px] min-h-[18px] rounded-full flex items-center justify-center font-bold leading-none"
                >
                  {totalItems}
                </motion.span>
              )}
            </Link>

            <Link to="/wishlist" className={`relative p-2 rounded-lg ${textColor} hover:text-primary hover:bg-primary/5 transition-all duration-200 min-h-[44px] min-w-[44px] flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-gold focus:ring-offset-2`} aria-label={`${t("wishlist")} (${wishlistCount} items)`}>
              <Heart size={18} />
              {wishlistCount > 0 && (
                <motion.span
                  initial={{ scale: 0 }}
                  animate={{ scale: 1 }}
                  className="absolute -top-0.5 -right-0.5 bg-gold text-accent-foreground text-[10px] min-w-[18px] min-h-[18px] rounded-full flex items-center justify-center font-bold leading-none"
                >
                  {wishlistCount}
                </motion.span>
              )}
            </Link>

            <Link to="/compare" className={`relative p-2 rounded-lg ${textColor} hover:text-primary hover:bg-primary/5 transition-all duration-200 min-h-[44px] min-w-[44px] flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-gold focus:ring-offset-2`} aria-label={`${t("compare")} (${comparisonCount} items)`}>
              <Scale size={18} />
              {comparisonCount > 0 && (
                <motion.span
                  initial={{ scale: 0 }}
                  animate={{ scale: 1 }}
                  className="absolute -top-0.5 -right-0.5 bg-primary text-primary-foreground text-[10px] min-w-[18px] min-h-[18px] rounded-full flex items-center justify-center font-bold leading-none"
                >
                  {comparisonCount}
                </motion.span>
              )}
            </Link>
          </div>

          {/* Mobile drawer toggle button (right) */}
          <button
            onClick={() => setMobileOpen(!mobileOpen)}
            className={`xl:hidden z-20 ${textColor} hover:text-primary transition-colors rounded-lg p-1.5 h-10 w-10 shrink-0 flex items-center justify-center`}
            aria-label={mobileOpen ? "Close menu" : "Open menu"}
            aria-expanded={mobileOpen}
          >
            <AnimatePresence mode="wait">
              {mobileOpen ? (
                <motion.div key="close" initial={{ rotate: -90, opacity: 0 }} animate={{ rotate: 0, opacity: 1 }} exit={{ rotate: 90, opacity: 0 }} transition={{ duration: 0.2 }}>
                  <X size={22} />
                </motion.div>
              ) : (
                <motion.div key="menu" initial={{ rotate: 90, opacity: 0 }} animate={{ rotate: 0, opacity: 1 }} exit={{ rotate: -90, opacity: 0 }} transition={{ duration: 0.2 }}>
                  <Menu size={22} />
                </motion.div>
              )}
            </AnimatePresence>
          </button>

        </div>
      </div>

      {/* Search bar */}
      <AnimatePresence>
        {searchOpen && (
          <motion.div
            initial={{ opacity: 0, y: -20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
            transition={{ duration: 0.3, ease: "easeInOut" }}
            className="bg-secondary/98 backdrop-blur-2xl border-t border-primary/5 relative z-[60]"
          >
            <div className="container mx-auto px-4 py-4 relative z-[60]">
              <AdvancedSearch onClose={() => setSearchOpen(false)} />
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Mobile menu */}
      <AnimatePresence>
        {mobileOpen && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: "auto", opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
transition={{ duration: 0.3, ease: "easeInOut" }}
            className="xl:hidden overflow-y-auto max-h-[calc(100dvh-4rem)] bg-secondary/98 backdrop-blur-2xl border-t border-primary/5"
          >
            <div className="container mx-auto px-4 py-6 flex flex-col gap-2">
              <div className="grid grid-cols-2 gap-2">
                {links.map((link, i) => (
                  <motion.div
                    key={link.to}
                    initial={{ opacity: 0, x: -10 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ delay: i * 0.05 }}
                  >
                    <Link
                      to={link.to}
                      onClick={() => setMobileOpen(false)}
                      className={`block text-base font-medium py-3 px-3 rounded-xl text-center transition-all duration-200 min-h-[48px] flex items-center justify-center ${
                        location.pathname === link.to
                          ? "text-primary bg-primary/10 border border-primary/20"
                          : "text-cream hover:text-primary hover:bg-primary/5"
                      }`}
                    >
                      {link.label}
                    </Link>
                  </motion.div>
                ))}
              </div>

              <div className="my-2 border-t border-border/60" />

              <motion.button
                initial={{ opacity: 0, x: -10 }}
                animate={{ opacity: 1, x: 0 }}
                onClick={() => {
                  setSearchOpen((prev) => !prev);
                  setMobileOpen(false);
                }}
                className="flex items-center gap-3 text-base font-medium py-4 px-4 rounded-xl text-cream hover:text-primary hover:bg-primary/5 transition-all duration-200 min-h-[52px]"
              >
                <Search size={20} />
                <span>{lang === "ar" ? "بحث" : "Search"}</span>
              </motion.button>

              <motion.button
                initial={{ opacity: 0, x: -10 }}
                animate={{ opacity: 1, x: 0 }}
                onClick={() => setLang(lang === "ar" ? "en" : "ar")}
                className="flex items-center gap-3 text-base font-medium py-4 px-4 rounded-xl text-cream hover:text-primary hover:bg-primary/5 transition-all duration-200 min-h-[52px]"
              >
                <Globe size={20} />
                <span>{lang === "ar" ? "English" : "العربية"}</span>
              </motion.button>

              <motion.div initial={{ opacity: 0, x: -10 }} animate={{ opacity: 1, x: 0 }}>
                <Link
                  to="/cart"
                  onClick={() => setMobileOpen(false)}
                  className="flex items-center justify-between text-base font-medium py-4 px-4 rounded-xl text-cream hover:text-primary hover:bg-primary/5 transition-all duration-200 min-h-[52px]"
                >
                  <span className="flex items-center gap-3">
                    <ShoppingBag size={20} />
                    {t("cart")}
                  </span>
                  {totalItems > 0 && <span className="text-xs font-bold px-2 py-0.5 rounded-full bg-primary text-primary-foreground">{totalItems}</span>}
                </Link>
              </motion.div>

              <motion.div initial={{ opacity: 0, x: -10 }} animate={{ opacity: 1, x: 0 }}>
                <Link
                  to="/wishlist"
                  onClick={() => setMobileOpen(false)}
                  className="flex items-center justify-between text-base font-medium py-4 px-4 rounded-xl text-cream hover:text-primary hover:bg-primary/5 transition-all duration-200 min-h-[52px]"
                >
                  <span className="flex items-center gap-3">
                    <Heart size={20} />
                    {t("wishlist")}
                  </span>
                  {wishlistCount > 0 && <span className="text-xs font-bold px-2 py-0.5 rounded-full bg-gold text-accent-foreground">{wishlistCount}</span>}
                </Link>
              </motion.div>

              <motion.div initial={{ opacity: 0, x: -10 }} animate={{ opacity: 1, x: 0 }}>
                <Link
                  to="/compare"
                  onClick={() => setMobileOpen(false)}
                  className="flex items-center justify-between text-base font-medium py-4 px-4 rounded-xl text-cream hover:text-primary hover:bg-primary/5 transition-all duration-200 min-h-[52px]"
                >
                  <span className="flex items-center gap-3">
                    <Scale size={20} />
                    {t("compare")}
                  </span>
                  {comparisonCount > 0 && <span className="text-xs font-bold px-2 py-0.5 rounded-full bg-primary text-primary-foreground">{comparisonCount}</span>}
                </Link>
              </motion.div>

              {user ? (
                <>
                  {isAdmin && (
                    <motion.div initial={{ opacity: 0, x: -10 }} animate={{ opacity: 1, x: 0 }}>
                      <Link
                        to="/admin"
                        onClick={() => setMobileOpen(false)}
                        className="flex items-center gap-2 text-base font-semibold py-3 px-4 rounded-xl bg-primary text-primary-foreground mt-2 min-h-[48px]"
                      >
                        <Shield size={18} />
                        {lang === "ar" ? "لوحة التحكم" : "Admin Dashboard"}
                      </Link>
                    </motion.div>
                  )}

                  <motion.button
                    initial={{ opacity: 0, x: -10 }}
                    animate={{ opacity: 1, x: 0 }}
                    onClick={signOut}
                    className="flex items-center gap-3 text-base font-medium py-3 px-4 rounded-xl text-destructive hover:bg-destructive/10 transition-all duration-200 min-h-[48px]"
                  >
                    <User size={18} />
                    {lang === "ar" ? "تسجيل الخروج" : "Sign Out"}
                  </motion.button>
                </>
              ) : (
                <motion.div initial={{ opacity: 0, x: -10 }} animate={{ opacity: 1, x: 0 }}>
                  <Link
                    to="/auth"
                    onClick={() => setMobileOpen(false)}
                    className="flex items-center gap-3 text-base font-medium py-3 px-4 rounded-xl text-cream hover:text-primary hover:bg-primary/5 transition-all duration-200 min-h-[48px]"
                  >
                    <User size={18} />
                    {lang === "ar" ? "تسجيل الدخول" : "Sign In"}
                  </Link>
                </motion.div>
              )}

            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </nav>
  );
};

export default Navbar;
