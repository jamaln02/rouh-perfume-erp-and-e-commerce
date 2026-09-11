import { useEffect, useState } from "react";
import { Navigate, Outlet, useLocation, Link, useNavigate } from "react-router-dom";
import { useAuth } from "@/hooks/useAuth";
import { useLanguage } from "@/hooks/useLanguage";
import { withAuthHeaders } from "@/lib/auth";
import { toast } from "sonner";
import ProtectedRoute from "@/components/ProtectedRoute";
import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarProvider,
  SidebarTrigger,
} from "@/components/ui/sidebar";
import { Receipt, Boxes, LayoutDashboard, Package, FolderTree, ShoppingCart, Users, LogOut, Home, Star, Tag, Bell, Upload, User, PackageCheck, Droplets, Wallet, ShieldCheck, Settings } from "lucide-react";
import { Button } from "@/components/ui/button";

const AdminLayout = () => {
  const { user, loading, isAdmin, permissions, signOut } = useAuth();
  const { lang } = useLanguage();
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "");
  const location = useLocation();
  const navigate = useNavigate();
  const [unreadOrders, setUnreadOrders] = useState<number>(0);
  const [unreadRecipeAlerts, setUnreadRecipeAlerts] = useState<number>(0);
  const [latestOrderId, setLatestOrderId] = useState<string | null>(null);
  const [latestRecipeAlertId, setLatestRecipeAlertId] = useState<number | null>(null);
  const canReceiveManagementAlerts = (user?.role === "admin" || user?.role === "manager") && permissions.includes("audit.view");

  // Request browser notification permission once
  useEffect(() => {
    if (isAdmin && typeof Notification !== "undefined" && Notification.permission === "default") {
      Notification.requestPermission().catch(() => {});
    }
  }, [isAdmin]);

  useEffect(() => {
    if (!isAdmin || !permissions.includes("orders.view")) return;

    let isMounted = true;

    const notifyForNewOrder = (o: { id: string; customer_name: string; total: number }) => {
      setUnreadOrders((n) => n + 1);
      try {
        const audioContextClass = (window as unknown as { AudioContext?: typeof AudioContext; webkitAudioContext?: typeof AudioContext }).AudioContext
          || (window as unknown as { AudioContext?: typeof AudioContext; webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
        if (!audioContextClass) throw new Error("AudioContext not available");
        const ctx = new audioContextClass();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = 880;
        gain.gain.setValueAtTime(0.0001, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.2, ctx.currentTime + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.4);
        osc.start();
        osc.stop(ctx.currentTime + 0.4);
      } catch (_) { /* ignore */ }

      try {
        if (typeof Notification !== "undefined" && Notification.permission === "granted") {
          const n = new Notification(
            lang === "ar" ? "🛒 طلب جديد" : "🛒 New Order",
            {
              body: `${o.customer_name} — ${o.total.toLocaleString()} SYP`,
              icon: "/favicon.ico",
              tag: `order-${o.id}`,
            }
          );
          n.onclick = () => {
            window.focus();
            navigate("/admin/orders");
            n.close();
          };
        }
      } catch (_) { /* ignore */ }

      toast.success(
        lang === "ar"
          ? `🛒 طلب جديد من ${o.customer_name}`
          : `🛒 New order from ${o.customer_name}`,
        {
          description: `${o.total.toLocaleString()} SYP`,
          duration: 8000,
          action: {
            label: lang === "ar" ? "عرض" : "View",
            onClick: () => { setUnreadOrders(0); navigate("/admin/orders"); },
          },
        }
      );
    };

    const pollOrders = async () => {
      try {
        const response = await window.fetch(`${apiBaseUrl}/api/admin/orders`, {
          headers: withAuthHeaders({ Accept: "application/json" }),
        });
        const data = await response.json();
        const firstOrder = (data?.orders || [])[0] as { id: string; customer_name: string; total: number } | undefined;

        if (!isMounted || !firstOrder) return;
        if (!latestOrderId) {
          setLatestOrderId(firstOrder.id);
          return;
        }

        if (firstOrder.id !== latestOrderId) {
          setLatestOrderId(firstOrder.id);
          notifyForNewOrder(firstOrder);
        }
      } catch {
        // Ignore polling failures silently.
      }
    };

    void pollOrders();
    const timer = window.setInterval(() => { void pollOrders(); }, 15000);

    return () => {
      isMounted = false;
      window.clearInterval(timer);
    };
  }, [apiBaseUrl, isAdmin, lang, latestOrderId, navigate]);

  useEffect(() => {
    if (!canReceiveManagementAlerts) return;
    let mounted = true;
    const pollRecipeAlerts = async () => {
      try {
        const response = await fetch(`${apiBaseUrl}/api/admin/audit-logs?action=recipe_quantity_changed&status=success&per_page=10`, {
          headers: withAuthHeaders({ Accept: "application/json" }),
        });
        if (!response.ok) return;
        const data = await response.json();
        const rows = Array.isArray(data?.data) ? data.data : [];
        const latest = rows[0];
        if (!mounted || !latest) return;
        const id = Number(latest.id);
        if (!latestRecipeAlertId) {
          setLatestRecipeAlertId(id);
          return;
        }
        if (id !== latestRecipeAlertId && id > latestRecipeAlertId) {
          setLatestRecipeAlertId(id);
          setUnreadRecipeAlerts((n) => n + 1);
          const message = lang === "ar" ? `تنبيه: ${latest.user?.name || "موظف"} عدّل كميات وصفة. السبب: ${latest.reason || "غير مذكور"}` : `Alert: ${latest.user?.name || "Employee"} changed recipe quantities. Reason: ${latest.reason || "Not provided"}`;
          toast.warning(message, { duration: 10000, action: { label: lang === "ar" ? "السجل" : "Audit log", onClick: () => navigate("/admin/audit-logs") } });
          try {
            if (typeof Notification !== "undefined" && Notification.permission === "granted") new Notification(lang === "ar" ? "تعديل على وصفة" : "Recipe quantity changed", { body: message, icon: "/favicon.ico", tag: `recipe-alert-${id}` });
          } catch (_) {}
        }
      } catch (_) {}
    };
    void pollRecipeAlerts();
    const timer = window.setInterval(() => { void pollRecipeAlerts(); }, 12000);
    return () => { mounted = false; window.clearInterval(timer); };
  }, [apiBaseUrl, canReceiveManagementAlerts, lang, latestRecipeAlertId, navigate]);

  // Clear the badge when the admin opens the orders page
  useEffect(() => {
    if (location.pathname === "/admin/orders") setUnreadOrders(0);
    if (location.pathname === "/admin/audit-logs") setUnreadRecipeAlerts(0);
  }, [location.pathname]);


  // ── Access control ──────────────────────────────────────────────
  // Authentication, loading state, and the admin/staff gate are handled by
  // <ProtectedRoute> which wraps the returned tree (see end of component).
  // The route-level *permission* checks below run first because they need a
  // smarter, path-aware fallback than a single permission gate allows.

  const t = {
    dashboard: lang === "ar" ? "لوحة التحكم" : "Dashboard",
    products: lang === "ar" ? "المنتجات" : "Products",
    categories: lang === "ar" ? "التصنيفات" : "Categories",
    orders: lang === "ar" ? "الطلبات" : "Orders",
    orderPreparation: lang === "ar" ? "تجهيز الطلبات" : "Order Preparation",
    users: lang === "ar" ? "المستخدمون" : "Users",
    reviews: lang === "ar" ? "التقييمات" : "Reviews",
    coupons: lang === "ar" ? "الكوبونات" : "Coupons",
    storeSettings: lang === "ar" ? "إعدادات المتجر" : "Store Settings",
    bulkImport: lang === "ar" ? "استيراد بالجملة" : "Bulk Import",
    customers: lang === "ar" ? "العملاء" : "Customers",
    admin: lang === "ar" ? "إدارة روح" : "Rouh Admin",
    backToSite: lang === "ar" ? "العودة للموقع" : "Back to Site",
    logout: lang === "ar" ? "تسجيل الخروج" : "Sign Out",
  };

  const allMenuItems = [
    ...(user?.role !== "employee" ? [{ title: t.dashboard, url: "/admin", icon: LayoutDashboard, permission: "dashboard.view" }] : []),
    { title: t.products, url: "/admin/products", icon: Package, permission: "products.view" },
    { title: t.categories, url: "/admin/categories", icon: FolderTree, permission: "products.manage" },
    { title: t.orders, url: "/admin/orders", icon: ShoppingCart, permission: "orders.view" },
    { title: lang === "ar" ? "تجهيز الطلبات" : "Order Preparation", url: "/admin/order-preparation", icon: PackageCheck, permission: "orders.prepare" },
    { title: lang === "ar" ? "إدارة التصنيع" : "Manufacturing", url: "/admin/manufacturing", icon: Droplets, permission: "manufacturing.view" },
    { title: t.customers, url: "/admin/customers", icon: User, permission: "customers.view" },
    { title: lang === "ar" ? "المخزون" : "Inventory", url: "/admin/inventory", icon: Boxes, permission: "inventory.view" },
    { title: lang === "ar" ? "تسجيل مصروف" : "Record Expense", url: "/admin/expense-entry", icon: Receipt, permission: "expenses.create" },
    { title: t.users, url: "/admin/users", icon: Users, permission: "users.manage" },
    { title: lang === "ar" ? "سجل العمليات" : "Audit Log", url: "/admin/audit-logs", icon: ShieldCheck, permission: "audit.view" },
    { title: t.reviews, url: "/admin/reviews", icon: Star, permission: "products.manage" },
    { title: t.coupons, url: "/admin/coupons", icon: Tag, permission: "settings.manage" },
    { title: t.storeSettings, url: "/admin/store-settings", icon: Settings, permission: "settings.manage" },
    { title: t.bulkImport, url: "/admin/bulk-import", icon: Upload, permission: "products.manage" },
    { title: lang === "ar" ? "المالية" : "Financial", url: "/admin/financial", icon: Wallet, permission: "finance.view" },
      ];
  const menuItems = allMenuItems.filter((item) => permissions.includes(item.permission));

  const isActive = (url: string) => location.pathname === url;
  const routePermission: Record<string, string> = {
    ...(user?.role !== 'employee' ? {'/admin': 'dashboard.view'} : {}), '/admin/products':'products.view', '/admin/categories':'products.manage',
    '/admin/orders':'orders.view', '/admin/order-preparation':'orders.prepare', '/admin/manufacturing':'manufacturing.view',
    '/admin/customers':'customers.view', '/admin/users':'users.manage', '/admin/audit-logs':'audit.view',
    '/admin/reviews':'products.manage', '/admin/coupons':'settings.manage', '/admin/store-settings':'settings.manage', '/admin/bulk-import':'products.manage',
    '/admin/financial':'finance.view', '/admin/inventory':'inventory.view', '/admin/expense-entry':'expenses.create', '/admin/system-debug':'settings.manage'
  };
  const requiredPermission = Object.entries(routePermission).find(([route]) => location.pathname === route)?.[1];

  // Wait for auth bootstrap before evaluating permission-based redirects.
  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin h-8 w-8 border-2 border-primary border-t-transparent rounded-full" />
      </div>
    );
  }

  if (location.pathname === '/admin' && !permissions.includes('dashboard.view')) {
    return <Navigate to={permissions.includes('orders.view') ? '/admin/orders' : '/'} replace />;
  }
  if (requiredPermission && !permissions.includes(requiredPermission)) {
    return <Navigate to={permissions.includes('orders.view') ? '/admin/orders' : '/'} replace />;
  }

return (
    <ProtectedRoute requireAdmin>
    <SidebarProvider>
      <div className="min-h-screen flex w-full bg-background gap-1">
<Sidebar collapsible="icon" className="border-e border-border z-40">
          <SidebarContent>
            <SidebarGroup>
              <SidebarGroupLabel className="text-primary font-display text-base px-3 py-2 truncate">
                {t.admin}
              </SidebarGroupLabel>
              <SidebarGroupContent>
                <SidebarMenu>
                  {menuItems.map((item) => (
                    <SidebarMenuItem key={item.url}>
                      <SidebarMenuButton asChild tooltip={item.title}>
                        <Link
                          to={item.url}
                          className={`flex items-center gap-3 px-3 py-2 rounded-lg transition-colors text-sm ${
                            isActive(item.url)
                              ? "bg-primary/10 text-primary font-medium"
                              : "text-muted-foreground hover:text-foreground hover:bg-muted"
                          }`}
                        >
                          <item.icon className="h-4 w-4 shrink-0" />
                          <span className="truncate">{item.title}</span>
                        </Link>
                      </SidebarMenuButton>
                    </SidebarMenuItem>
                  ))}
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>

            <div className="mt-auto p-2 space-y-1">
              <Button variant="ghost" size="sm" className="w-full justify-start gap-2 text-muted-foreground" asChild>
                <Link to="/"><Home className="h-4 w-4" /> {t.backToSite}</Link>
              </Button>
              <Button variant="ghost" size="sm" className="w-full justify-start gap-2 text-destructive" onClick={signOut}>
                <LogOut className="h-4 w-4" /> {t.logout}
              </Button>
            </div>
          </SidebarContent>
        </Sidebar>

        <div className="flex-1 flex flex-col min-w-0">
          <header className="h-14 flex items-center border-b border-border px-3 sm:px-4 sticky top-0 bg-background/95 backdrop-blur z-30">
            <SidebarTrigger />
            <div className="ms-auto flex items-center gap-2">
              <Link
                to="/admin/orders"
                className="relative p-2 rounded-lg hover:bg-muted transition-colors"
                aria-label={lang === "ar" ? "الإشعارات" : "Notifications"}
                title={lang === "ar" ? "الطلبات الجديدة" : "New orders"}
              >
                <Bell className="h-5 w-5 text-muted-foreground" />
                {unreadOrders + unreadRecipeAlerts > 0 && (
                  <span className="absolute -top-0.5 -right-0.5 bg-primary text-primary-foreground text-[10px] min-w-[18px] h-[18px] px-1 rounded-full flex items-center justify-center font-bold leading-none">
                    {unreadOrders + unreadRecipeAlerts}
                  </span>
                )}
              </Link>
            </div>
          </header>
          <main className="flex-1 p-3 sm:p-4 lg:p-6 overflow-x-hidden overflow-y-auto">
            <div className="max-w-full">
              <Outlet />
            </div>
          </main>
        </div>
      </div>
    </SidebarProvider>
    </ProtectedRoute>
  );
};

export default AdminLayout;
