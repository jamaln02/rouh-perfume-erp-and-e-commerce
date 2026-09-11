import { useEffect, useState } from "react";
import { useLanguage } from "@/hooks/useLanguage";
import { useAuth } from "@/hooks/useAuth";
import { withAuthHeaders } from "@/lib/auth";
import { Package, FolderTree, ShoppingCart, Users, DollarSign, TrendingUp, AlertTriangle, BarChart3, PieChart } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ResponsiveContainer, XAxis, YAxis, Tooltip, CartesianGrid, BarChart, Bar, PieChart as RechartsPieChart, Pie, Cell, Legend } from "recharts";
import { Link } from "react-router-dom";

interface LowStock { id: string; name: string; name_ar: string; stock: number; }
interface ChartSlice { name: string; value: number; }

const COLORS = ['#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#ec4899'];

const Dashboard = () => {
  const { lang } = useLanguage();
  const { user } = useAuth();
  const isEmployee = user?.role === "employee";
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "");
  const [counts, setCounts] = useState({ products: 0, categories: 0, orders: 0, pendingOrders: 0 });
  const [revenue, setRevenue] = useState({ total: 0, avg: 0, count: 0 });
  const [chartData, setChartData] = useState<{ date: string; sales: number }[]>([]);
  const [topProducts, setTopProducts] = useState<{ name: string; qty: number; revenue: number }[]>([]);
  const [lowStock, setLowStock] = useState<LowStock[]>([]);
  const [categoryData, setCategoryData] = useState<ChartSlice[]>([]);
  const [orderStatusData, setOrderStatusData] = useState<ChartSlice[]>([]);

  const localizedStatus = (status: string) => {
    const mapAr: Record<string, string> = {
      completed: "مكتمل",
      processing: "قيد المعالجة",
      pending: "معلق",
      cancelled: "ملغي",
    };

    if (lang !== "ar") return status;
    return mapAr[status.toLowerCase()] ?? status;
  };

  useEffect(() => {
    (async () => {
      const response = await window.fetch(`${apiBaseUrl}/api/admin/dashboard`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();

      setCounts(data?.counts || { products: 0, categories: 0, orders: 0, pendingOrders: 0 });
      setRevenue(data?.revenue || { total: 0, avg: 0, count: 0 });
      setChartData(data?.chartData || []);
      setTopProducts(data?.topProducts || []);
      setLowStock((data?.lowStock || []) as LowStock[]);

      const rawCategoryData = (data?.categoryData || []) as ChartSlice[];
      setCategoryData(rawCategoryData);

      const rawOrderStatusData = (data?.orderStatusData || []) as ChartSlice[];
      setOrderStatusData(
        rawOrderStatusData.map((item) => ({
          ...item,
          name: localizedStatus(item.name),
        }))
      );
    })();
  }, [apiBaseUrl, lang]);

  const fmt = (n: number) => n.toLocaleString();

  const cards = [
    ...(!isEmployee ? [
      { title: lang === "ar" ? "إجمالي المبيعات (٣٠ يوم)" : "Revenue (30d)", value: `${fmt(Math.round(revenue.total))} SYP`, icon: DollarSign, color: "text-primary" },
      { title: lang === "ar" ? "متوسط الطلب" : "Avg Order", value: `${fmt(Math.round(revenue.avg))} SYP`, icon: TrendingUp, color: "text-green-500" },
    ] : []),
    { title: lang === "ar" ? "الطلبات" : "Orders", value: counts.orders, icon: ShoppingCart, color: "text-blue-500" },
    { title: lang === "ar" ? "طلبات معلقة" : "Pending", value: counts.pendingOrders, icon: Users, color: "text-orange-500" },
    { title: lang === "ar" ? "المنتجات" : "Products", value: counts.products, icon: Package, color: "text-purple-500" },
    { title: lang === "ar" ? "التصنيفات" : "Categories", value: counts.categories, icon: FolderTree, color: "text-pink-500" },
  ];

  return (
    <div className="space-y-6 space-x-7">
      <h1 className="text-2xl font-display font-bold">{lang === "ar" ? "لوحة التحكم" : "Dashboard"}</h1>

      <div className="grid grid-cols-2 lg:grid-cols-6 gap-4">
        {cards.map((c) => (
          <Card key={c.title} className="border-border">
            <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
              <CardTitle className="text-xs font-medium text-muted-foreground">{c.title}</CardTitle>
              <c.icon className={`h-4 w-4 ${c.color}`} />
            </CardHeader>
            <CardContent>
              <div className="text-xl font-bold">{c.value}</div>
            </CardContent>
          </Card>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {!isEmployee && <Card className="border-border">
          <CardHeader>
            <CardTitle className="text-base flex items-center gap-2">
              <BarChart3 className="h-5 w-5" />
              {lang === "ar" ? "المبيعات اليومية (آخر ٣٠ يوم)" : "Daily Sales (Last 30 days)"}
            </CardTitle>
          </CardHeader>
          <CardContent>
            {chartData.some(d => d.sales > 0) ? (
              <ResponsiveContainer width="100%" height={260}>
                <BarChart data={chartData}>
                  <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                  <XAxis dataKey="date" tick={{ fontSize: 11 }} />
                  <YAxis tick={{ fontSize: 11 }} />
                  <Tooltip contentStyle={{ background: "hsl(var(--background))", border: "1px solid hsl(var(--border))", borderRadius: 8 }} />
                  <Bar dataKey="sales" fill="hsl(var(--primary))" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-[260px] flex items-center justify-center text-muted-foreground text-sm">
                {lang === "ar" ? "لا مبيعات بعد" : "No sales yet"}
              </div>
            )}
          </CardContent>
        </Card>}

        <Card className="border-border">
          <CardHeader>
            <CardTitle className="text-base flex items-center gap-2">
              <PieChart className="h-5 w-5" />
              {lang === "ar" ? "توزيع التصنيفات" : "Category Distribution"}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={260}>
              <RechartsPieChart>
                <Pie
                  data={categoryData}
                  cx="50%"
                  cy="50%"
                  labelLine={false}
                  label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                  outerRadius={80}
                  fill="#8884d8"
                  dataKey="value"
                >
                  {categoryData.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip />
                <Legend />
              </RechartsPieChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <Card className="border-border">
          <CardHeader>
            <CardTitle className="text-base flex items-center gap-2">
              <TrendingUp className="h-5 w-5" />
              {lang === "ar" ? "حالة الطلبات" : "Order Status"}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={200}>
              <RechartsPieChart>
                <Pie
                  data={orderStatusData}
                  cx="50%"
                  cy="50%"
                  labelLine={false}
                  label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                  outerRadius={70}
                  fill="#8884d8"
                  dataKey="value"
                >
                  {orderStatusData.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip />
                <Legend />
              </RechartsPieChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        <Card className="border-border">
          <CardHeader>
            <CardTitle className="text-base">{lang === "ar" ? "أكثر المنتجات مبيعاً" : "Top Products"}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {topProducts.length === 0 && <div className="text-sm text-muted-foreground">—</div>}
            {topProducts.map((p, i) => (
              <div key={p.name} className="flex items-center justify-between gap-2 text-sm">
                <div className="flex items-center gap-2 min-w-0">
                  <span className="text-primary font-bold w-5">{i + 1}.</span>
                  <span className="truncate">{p.name}</span>
                </div>
                <span className="text-muted-foreground text-xs whitespace-nowrap">{p.qty} {lang === "ar" ? "وحدة" : "units"}</span>
              </div>
            ))}
          </CardContent>
        </Card>
      </div>

      {lowStock.length > 0 && (
        <Card className="border-orange-500/40 bg-orange-500/5">
          <CardHeader>
            <CardTitle className="text-base flex items-center gap-2 text-orange-600 dark:text-orange-400">
              <AlertTriangle className="h-5 w-5" />
              {lang === "ar" ? "تنبيه: مخزون منخفض" : "Low Stock Alert"}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
              {lowStock.map((p) => (
                <Link
                  key={p.id}
                  to="/admin/products"
                  className="flex items-center justify-between rounded-md border border-border bg-background px-3 py-2 text-sm hover:border-primary transition-colors"
                >
                  <span className="truncate">{lang === "ar" ? p.name_ar : p.name}</span>
                  <span className={`font-bold ${p.stock === 0 ? "text-destructive" : "text-orange-500"}`}>{p.stock}</span>
                </Link>
              ))}
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
};

export default Dashboard;
