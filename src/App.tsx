import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Outlet, Route, Routes } from "react-router-dom";
import { lazy, Suspense } from "react";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { ErrorProvider } from "@/contexts/ErrorContext";
import { AuthProvider } from "@/hooks/useAuth";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import WhatsAppButton from "@/components/WhatsAppButton";
import { ScrollToTop } from "@/components/ScrollToTop";
import FloatingAddToCart from "@/components/FloatingAddToCart";
import AdminLayout from "@/components/admin/AdminLayout";

// ---------------------------------------------------------------------------
// Route-level code splitting (lazy loading).
//
// Each page is loaded on-demand via React.lazy + dynamic import(). This means
// Vite/Rolldown produces a SEPARATE chunk per page, so visitors only download
// the code for the route they actually visit — not the entire admin panel +
// shop + checkout bundle upfront.
//
// The layout shells (Navbar, Footer, AdminLayout) stay eagerly imported so
// the chrome renders instantly; only page bodies are lazy.
// ---------------------------------------------------------------------------

// Public pages
const Index = lazy(() => import("./pages/Index"));
const Shop = lazy(() => import("./pages/Shop"));
const ProductDetails = lazy(() => import("./pages/ProductDetails"));
const About = lazy(() => import("./pages/About"));
const Contact = lazy(() => import("./pages/Contact"));
const Cart = lazy(() => import("./pages/Cart"));
const Checkout = lazy(() => import("./pages/Checkout"));
const Auth = lazy(() => import("./pages/Auth"));
const Quiz = lazy(() => import("./pages/Quiz"));
const OrderSuccess = lazy(() => import("./pages/OrderSuccess"));
const Wishlist = lazy(() => import("./pages/Wishlist"));
const TrackOrder = lazy(() => import("./pages/TrackOrder"));
const NotFound = lazy(() => import("./pages/NotFound"));
const Compare = lazy(() => import("./pages/Compare"));
const Bundles = lazy(() => import("./pages/Bundles"));
const BundleDetails = lazy(() => import("./pages/BundleDetails"));
const FAQ = lazy(() => import("./pages/FAQ"));

// Admin pages
const Dashboard = lazy(() => import("./pages/admin/Dashboard"));
const AdminProducts = lazy(() => import("./pages/admin/AdminProducts"));
const AdminCategories = lazy(() => import("./pages/admin/AdminCategories"));
const AdminOrders = lazy(() => import("./pages/admin/AdminOrders"));
const OrderPreparation = lazy(() => import("./pages/admin/OrderPreparation"));
const ManufacturingManagement = lazy(() => import("./pages/admin/ManufacturingManagement"));
const AdminUsers = lazy(() => import("./pages/admin/AdminUsers"));
const AuditLogs = lazy(() => import("./pages/admin/AuditLogs"));
const AdminReviews = lazy(() => import("./pages/admin/AdminReviews"));
const AdminCoupons = lazy(() => import("./pages/admin/AdminCoupons"));
const BulkImport = lazy(() => import("./pages/admin/BulkImport"));
const CustomerManagement = lazy(() => import("./pages/admin/CustomerManagement"));
const FinancialManagement = lazy(() => import("./pages/admin/FinancialManagement"));
const InventoryOverview = lazy(() => import("./pages/admin/InventoryOverview"));
const EmployeeExpenseEntry = lazy(() => import("./pages/admin/EmployeeExpenseEntry"));
const AdminStoreSettings = lazy(() => import("./pages/admin/AdminStoreSettings"));

const queryClient = new QueryClient();

const PublicLayout = () => (
  <>
    <Navbar />
    <Outlet />
    <Footer />
    <WhatsAppButton />
    <ScrollToTop />
    <FloatingAddToCart />
  </>
);

// Minimal loading fallback shown while a lazy chunk downloads.
const PageLoader = () => (
  <div className="flex min-h-[50vh] items-center justify-center">
    <div className="h-8 w-8 animate-spin rounded-full border-2 border-gray-300 border-t-gray-700" />
  </div>
);

const App = () => (
  <QueryClientProvider client={queryClient}>
    <TooltipProvider>
      <ErrorProvider>
        <AuthProvider>
          <Sonner />
          <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
            <Suspense fallback={<PageLoader />}>
              <Routes>
                {/* Admin routes - no Navbar/Footer */}
                <Route path="/admin" element={<AdminLayout />}>
                  <Route index element={<Dashboard />} />
                  <Route path="products" element={<AdminProducts />} />
                  <Route path="categories" element={<AdminCategories />} />
                  <Route path="orders" element={<AdminOrders />} />
                  <Route path="order-preparation" element={<OrderPreparation />} />
                  <Route path="manufacturing" element={<ManufacturingManagement />} />
                  <Route path="customers" element={<CustomerManagement />} />
                  <Route path="users" element={<AdminUsers />} />
                  <Route path="audit-logs" element={<AuditLogs />} />
                  <Route path="reviews" element={<AdminReviews />} />
                  <Route path="coupons" element={<AdminCoupons />} />
                  <Route path="bulk-import" element={<BulkImport />} />
                  <Route path="financial" element={<FinancialManagement />} />
                  <Route path="inventory" element={<InventoryOverview />} />
                  <Route path="expense-entry" element={<EmployeeExpenseEntry />} />
                  <Route path="store-settings" element={<AdminStoreSettings />} />
                </Route>

                <Route path="/auth" element={<Auth />} />

                <Route element={<PublicLayout />}>
                  <Route index element={<Index />} />
                  <Route path="shop" element={<Shop />} />
                  <Route path="product/:id" element={<ProductDetails />} />
                  <Route path="about" element={<About />} />
                  <Route path="contact" element={<Contact />} />
                  <Route path="cart" element={<Cart />} />
                  <Route path="checkout" element={<Checkout />} />
                  <Route path="quiz" element={<Quiz />} />
                  <Route path="wishlist" element={<Wishlist />} />
                  <Route path="compare" element={<Compare />} />
                  <Route path="bundles" element={<Bundles />} />
                  <Route path="bundle/:id" element={<BundleDetails />} />
                  <Route path="track" element={<TrackOrder />} />
                  <Route path="faq" element={<FAQ />} />
                  <Route path="order-success/:id" element={<OrderSuccess />} />
                  <Route path="*" element={<NotFound />} />
                </Route>
              </Routes>
            </Suspense>
          </BrowserRouter>
        </AuthProvider>
      </ErrorProvider>
    </TooltipProvider>
  </QueryClientProvider>
);

export default App;
