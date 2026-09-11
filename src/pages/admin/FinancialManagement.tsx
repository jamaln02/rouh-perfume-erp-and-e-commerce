import { useState, useEffect, useCallback } from "react";
import { useLanguage } from "@/hooks/useLanguage";
import { withAuthHeaders } from "@/lib/auth";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from "@/components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Plus,
  Trash2,
  Package,
  ShoppingCart,
  DollarSign,
  TrendingUp,
  BarChart3,
  Receipt,
  Wallet,
  CalendarDays,
  Pencil,
  Scale,
  ShieldCheck,
  LockKeyhole,
  UnlockKeyhole,
  Landmark,
  BookOpen,
  FileText,
  ListChecks,
} from "lucide-react";

interface MaterialRow {
  id: number;
  name: string;
  name_ar?: string;
  material_category: string;
  base_unit: string;
  current_stock: number;
  min_stock?: number;
  avg_unit_cost?: number;
}

interface Purchase {
  id: number;
  raw_material_id?: number | null;
  material_id?: number | null;
  item_name: string;
  quantity: number;
  unit: string;
  unit_cost: number;
  total_cost: number;
  purchase_date: string;
  supplier?: string;
  invoice_number?: string;
  notes?: string;
}

interface Expense {
  id: number;
  category: string;
  description: string;
  amount: number;
  expense_date: string;
  payment_method?: string;
  rent_duration_days?: number;
  vendor?: string;
  notes?: string;
}

interface PeriodRow {
  label: string;
  total: number;
}
interface AccountingPeriodRow {
  id: number;
  name: string;
  period_start: string;
  period_end: string;
  status: string;
  closed_at?: string | null;
}

interface ReconciliationRow {
  id: number;
  reconciliation_date: string;
  book_balance: number;
  statement_balance: number;
  difference: number;
  status: string;
  account?: { name?: string; name_ar?: string };
  reconciler?: { name?: string };
}

const FinancialManagement = () => {
  const { lang } = useLanguage();
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "");
  const [accountingStartDefault, setAccountingStartDefault] = useState("");
  const [openingInventoryRows, setOpeningInventoryRows] = useState<any[]>([]);
  const [openingFixedAssetRows, setOpeningFixedAssetRows] = useState<any[]>([]);
  const [openingImporting, setOpeningImporting] = useState(false);

  const [activeTab, setActiveTab] = useState("reports");
  const [loading, setLoading] = useState(false);

  // Inventory / raw materials for purchase dropdown
  const [materials, setMaterials] = useState<any[]>([]);
  const [purchases, setPurchases] = useState<Purchase[]>([]);
  const [expenses, setExpenses] = useState<Expense[]>([]);
  const [sales, setSales] = useState<any[]>([]);
  const [salesStatusFilter, setSalesStatusFilter] = useState("all");
  const [salesPaymentFilter, setSalesPaymentFilter] = useState("all");
  const [salesSourceFilter, setSalesSourceFilter] = useState("all");
  const [selectedSale, setSelectedSale] = useState<any>(null);
  const [reportData, setReportData] = useState<any>(null);
  const [assets, setAssets] = useState<any[]>([]);
  const [depreciationPreview, setDepreciationPreview] = useState<any>(null);
  const [productionUnits, setProductionUnits] = useState<Record<string, string>>({});
  const [depreciationDate, setDepreciationDate] = useState(new Date().toISOString().slice(0, 10));
  const [taxes, setTaxes] = useState<any[]>([]);
  const [accountingPeriods, setAccountingPeriods] = useState<AccountingPeriodRow[]>([]);
  const [reconciliations, setReconciliations] = useState<ReconciliationRow[]>([]);
  const [reconciliationOptions, setReconciliationOptions] = useState<any[]>([]);
  const [periodStart, setPeriodStart] = useState("");
  const [periodEnd, setPeriodEnd] = useState(new Date(new Date().getFullYear(), new Date().getMonth()+1, 0).toISOString().slice(0,10));
  const [periodName, setPeriodName] = useState('');
  const [reconciliationAccountId, setReconciliationAccountId] = useState('');
  const [statementBalance, setStatementBalance] = useState('');
  const [reconciliationDate, setReconciliationDate] = useState(new Date().toISOString().slice(0,10));
  const [reconciliationNotes, setReconciliationNotes] = useState('');
  const [journalRows, setJournalRows] = useState<any[]>([]);
  const [accounts, setAccounts] = useState<any[]>([]);
  const [selectedLedgerAccount, setSelectedLedgerAccount] = useState('');
  const [ledgerRows, setLedgerRows] = useState<any[]>([]);
  const [ledgerOpening, setLedgerOpening] = useState(0);
  const [ledgerClosing, setLedgerClosing] = useState(0);
  const [trialBalanceData, setTrialBalanceData] = useState<any>(null);
  const [statementsData, setStatementsData] = useState<any>(null);
  const [accountingStartDate, setAccountingStartDate] = useState("");
  const [accountingEndDate, setAccountingEndDate] = useState(new Date().toISOString().slice(0,10));
  const [manualJournalDialog, setManualJournalDialog] = useState(false);
  const [manualJournalDescription, setManualJournalDescription] = useState('');
  const [manualJournalDate, setManualJournalDate] = useState(new Date().toISOString().slice(0,10));
  const [manualJournalLines, setManualJournalLines] = useState([{ account_id: '', debit: '', credit: '', description: '' }]);
  const [openingBalances, setOpeningBalances] = useState<any[]>([]);
  const [activeOpeningId, setActiveOpeningId] = useState<number | null>(null);
  const [openingDate, setOpeningDate] = useState("");
  const [openingNote, setOpeningNote] = useState('');
  const [openingQty, setOpeningQty] = useState('');
  const [openingUnitCost, setOpeningUnitCost] = useState('');
  const [openingMaterialId, setOpeningMaterialId] = useState('');
  const [openingType, setOpeningType] = useState('perfume_oil');
  const [openingFinancialForm, setOpeningFinancialForm] = useState({ financial_account_id: '', balance: '', exchange_rate: '1', notes: '' });
  const [accountDialogOpen, setAccountDialogOpen] = useState(false);
  const [accountSaving, setAccountSaving] = useState(false);
  const [newAccountForm, setNewAccountForm] = useState({ name: '', name_ar: '', account_type: 'cash', currency: 'SYP', bank_name: '', account_number: '' });
  const [openingPartyForm, setOpeningPartyForm] = useState({ party_name: '', party_name_ar: '', type: 'payable', amount: '', currency: 'SYP', exchange_rate: '1', due_date: '', description: '', notes: '' });



  // Purchase form
  const [purchaseDialog, setPurchaseDialog] = useState(false);
  const [editingPurchase, setEditingPurchase] = useState<Purchase | null>(null);
  const [purchaseForm, setPurchaseForm] = useState({
    material_id: "",
    item_name: "",
    quantity: 0,
    cost_per_unit: 0,
    purchase_date: new Date().toISOString().slice(0, 10),
    supplier: "",
    invoice_number: "",
    notes: "",
    item_type: "raw_material",
    payment_method: "cash",
    paid_amount: 0,
  });

  // Add new item from within the purchase dialog
  const [newItemDialog, setNewItemDialog] = useState(false);
  const [newItemForm, setNewItemForm] = useState({
    name: "",
    name_ar: "",
    type: "essential_oil",
    unit: "grams",
    cost_per_unit: 0,
    current_stock: 0,
    min_stock: 0,
    supplier: "",
    notes: "",
  });

  // Expense form
  const [expenseDialog, setExpenseDialog] = useState(false);
  const [editingExpense, setEditingExpense] = useState<Expense | null>(null);
  const [expenseForm, setExpenseForm] = useState({
    category: "rent",
    description: "",
    amount: 0,
    expense_date: new Date().toISOString().slice(0, 10),
    payment_method: "cash",
    rent_duration_days: 1,
    vendor: "",
    notes: "",
  });

  // Asset form
  const [assetDialog, setAssetDialog] = useState(false);
  const [editingAsset, setEditingAsset] = useState<any>(null);
  const [assetForm, setAssetForm] = useState({
    name: "",
    name_ar: "",
    asset_number: "",
    category: "equipment",
    description: "",
    quantity: 1,
    purchase_cost: 0,
    purchase_date: new Date().toISOString().slice(0, 10),
    supplier: "",
    invoice_number: "",
    depreciation_method: "straight_line",
    useful_life_years: 5,
    total_estimated_units: 0,
    salvage_value: 0,
    depreciation_start_date: new Date().toISOString().slice(0, 10),
    location: "",
    serial_number: "",
    notes: "",
  });

  // Tax form
  const [taxDialog, setTaxDialog] = useState(false);
  const [editingTax, setEditingTax] = useState<any>(null);
  const [taxForm, setTaxForm] = useState({
    name: "",
    name_ar: "",
    tax_type: "income_tax",
    rate: 0,
    is_active: true,
    post_to_ledger: false,
    tax_inclusive: true,
    effective_date: new Date().toISOString().slice(0, 10),
    expiry_date: "",
    description: "",
    calculation_method: "percentage",
    fixed_amount: 0,
    applicable_to: "profit",
    applicable_categories: [],
    notes: "",
  });

  const [reportPeriod, setReportPeriod] = useState("monthly");

  const t = {
    title: lang === "ar" ? "الإدارة المالية والمشتريات والمصاريف" : "Financial, Purchases & Expenses",
    subtitle: lang === "ar" ? "قائمة بالفواتير والمصاريف والتقارير المالية" : "Purchase invoices, expenses, and financial reports",
    reports: lang === "ar" ? "التقارير المالية" : "Financial Reports",
    purchases: lang === "ar" ? "فواتير الشراء" : "Purchase Invoices",
    expenses: lang === "ar" ? "المصاريف" : "Expenses",
    addPurchase: lang === "ar" ? "إضافة فاتورة شراء" : "Add Purchase",
    addExpense: lang === "ar" ? "إضافة مصروف" : "Add Expense",
    edit: lang === "ar" ? "تعديل" : "Edit",
    delete: lang === "ar" ? "حذف" : "Delete",
    save: lang === "ar" ? "حفظ" : "Save",
    cancel: lang === "ar" ? "إلغاء" : "Cancel",
    selectItem: lang === "ar" ? "اختر الصنف" : "Select Item",
    addNewItem: lang === "ar" ? "إضافة صنف جديد" : "Add New Item",
    quantity: lang === "ar" ? "الكمية" : "Quantity",
    unit: lang === "ar" ? "الوحدة" : "Unit",
    costPerUnit: lang === "ar" ? "التكلفة للواحدة (SYP)" : "Cost per unit (SYP)",
    totalCost: lang === "ar" ? "التكلفة الإجمالية (SYP)" : "Total cost (SYP)",
    date: lang === "ar" ? "التاريخ" : "Date",
    supplier: lang === "ar" ? "المورد" : "Supplier",
    invoiceNo: lang === "ar" ? "رقم الفاتورة" : "Invoice No",
    notes: lang === "ar" ? "ملاحظات" : "Notes",
    category: lang === "ar" ? "نوع المصروف" : "Category",
    catRent: lang === "ar" ? "إيجار" : "Rent",
    catTransport: lang === "ar" ? "مواصلات" : "Transport",
    catSalaries: lang === "ar" ? "رواتب" : "Salaries",
    catMarketing: lang === "ar" ? "تسويق" : "Marketing",
    catUtilities: lang === "ar" ? "فواتير" : "Utilities",
    catOther: lang === "ar" ? "أخرى" : "Other",
    amount: lang === "ar" ? "المبلغ (SYP)" : "Amount (SYP)",
    paymentMethod: lang === "ar" ? "طريقة الدفع" : "Payment Method",
    cash: lang === "ar" ? "نقدي" : "Cash",
    credit: lang === "ar" ? "آجل" : "Credit",
    vendor: lang === "ar" ? "المورد / الجهة" : "Vendor / Entity",
    rentDuration: lang === "ar" ? "مدة الإيجار (بالأيام)" : "Rent duration (days)",
    description: lang === "ar" ? "الوصف" : "Description",
    itemName: lang === "ar" ? "اسم الصنف" : "Item Name",
    itemNameAr: lang === "ar" ? "الاسم بالعربية" : "Name (Arabic)",
    itemType: lang === "ar" ? "النوع" : "Type",
    currentStock: lang === "ar" ? "المخزون الحالي" : "Current Stock",
    minStock: lang === "ar" ? "الحد الأدنى" : "Min Stock",
    unitType: lang === "ar" ? "وحدة القياس" : "Unit",
    noPurchases: lang === "ar" ? "لا توجد فواتير شراء" : "No purchases",
    noExpenses: lang === "ar" ? "لا توجد مصاريف" : "No expenses",
    totalRevenue: lang === "ar" ? "الإجمالي" : "Total",
    // Report labels
    daily: lang === "ar" ? "يومي" : "Daily",
    weekly: lang === "ar" ? "أسبوعي" : "Weekly",
    monthly: lang === "ar" ? "شهري" : "Monthly",
    yearly: lang === "ar" ? "سنوي" : "Yearly",
    revenue: lang === "ar" ? "الإيرادات" : "Revenue",
    expensesLabel: lang === "ar" ? "المصاريف" : "Expenses",
    purchasesLabel: lang === "ar" ? "المشتريات" : "Purchases",
    netProfit: lang === "ar" ? "صافي الربح" : "Net Profit",
    inventoryValue: lang === "ar" ? "قيمة المخزون" : "Inventory Value",
    cashBalance: lang === "ar" ? "الرصيد النقدي" : "Cash Balance",
    bankBalance: lang === "ar" ? "الرصيد البنكي" : "Bank Balance",
    netPosition: lang === "ar" ? "المركز المالي الصافي" : "Net Position",
    cogs: lang === "ar" ? "تكلفة البضاعة" : "COGS",
    wages: lang === "ar" ? "الرواتب" : "Wages",
    operatingExpenses: lang === "ar" ? "المصاريف التشغيلية" : "Operating Expenses",
    type_oil: lang === "ar" ? "زيت أساسي" : "Essential Oil",
    type_alcohol: lang === "ar" ? "كحول" : "Alcohol",
    type_bottle: lang === "ar" ? "زجاجة" : "Bottle",
    type_packaging: lang === "ar" ? "تغليف" : "Packaging",
    unit_grams: lang === "ar" ? "غرام" : "grams",
    unit_liters: lang === "ar" ? "لتر" : "liters",
    unit_pieces: lang === "ar" ? "قطعة" : "pieces",
  };

  const typeLabel = (type: string) => {
    const map: Record<string, string> = {
      perfume_oil: t.type_oil,
      alcohol: t.type_alcohol,
      packaging: t.type_packaging,
      other: lang === "ar" ? "أخرى" : "Other",
    };
    return map[type] || type;
  };

  const unitLabel = (unit: string) => {
    const map: Record<string, string> = {
      g: t.unit_grams,
      kg: t.unit_grams,
      ml: lang === "ar" ? "مل" : "ml",
      l: t.unit_liters,
      liter: t.unit_liters,
      liters: t.unit_liters,
      pcs: t.unit_pieces,
      pc: t.unit_pieces,
      piece: t.unit_pieces,
      pieces: t.unit_pieces,
    };
    return map[unit] || unit;
  };

  const fmt = (n: number) => Number(n || 0).toLocaleString();

  const loadMaterials = useCallback(async () => {
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/inventory/materials`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      setMaterials(Array.isArray(data) ? data : []);
    } catch (error) {
      toast.error("Failed to load materials");
      setMaterials([]);
    }
  }, [apiBaseUrl]);

  const loadPurchases = useCallback(async () => {
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/inventory/purchases`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      setPurchases(Array.isArray(data) ? data : []);
    } catch (error) {
      toast.error("Failed to load purchases");
      setPurchases([]);
    }
  }, [apiBaseUrl]);

  const loadExpenses = useCallback(async () => {
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/inventory/expenses`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      setExpenses(Array.isArray(data) ? data : []);
    } catch (error) {
      toast.error("Failed to load expenses");
      setExpenses([]);
    }
  }, [apiBaseUrl]);

  const loadSales = useCallback(async () => {
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/inventory/sales`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      setSales(Array.isArray(data) ? data : []);
    } catch (error) {
      toast.error("Failed to load sales");
      setSales([]);
    }
  }, [apiBaseUrl]);

  const loadReport = useCallback(async () => {
    try {
      const params = new URLSearchParams();
      if (accountingStartDate) params.set('start_date', accountingStartDate);
      if (accountingEndDate) params.set('end_date', accountingEndDate);
      const query = params.toString();
      const response = await fetch(`${apiBaseUrl}/api/admin/inventory/financial-dashboard${query ? `?${query}` : ''}`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data?.message || data?.error || 'Failed to load financial report');
      setReportData(data);
    } catch (error) {
      toast.error("Failed to load financial report");
    }
  }, [apiBaseUrl, accountingStartDate, accountingEndDate]);

  const loadAssets = useCallback(async () => {
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/fixed-assets`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      setAssets(Array.isArray(data) ? data : []);
    } catch (error) {
      toast.error("Failed to load assets");
      setAssets([]);
    }
  }, [apiBaseUrl]);

  const previewDepreciation = async () => {
    const start = `${depreciationDate.slice(0, 8)}01`;
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/fixed-assets/depreciation/preview`, {
        method: 'POST', headers: withAuthHeaders({ 'Content-Type': 'application/json', Accept: 'application/json' }),
        body: JSON.stringify({ start_date: start, end_date: depreciationDate, production_units: productionUnits }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data?.message || 'Failed to calculate depreciation');
      setDepreciationPreview(data);
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
    }
  };

  const postDepreciation = async () => {
    if (!depreciationPreview || Number(depreciationPreview.total || 0) <= 0) return;
    try {
      const start = `${depreciationDate.slice(0, 8)}01`;
      const response = await fetch(`${apiBaseUrl}/api/admin/fixed-assets/depreciation/post`, {
        method: 'POST', headers: withAuthHeaders({ 'Content-Type': 'application/json', Accept: 'application/json' }),
        body: JSON.stringify({ start_date: start, end_date: depreciationDate, production_units: productionUnits }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data?.message || 'Failed to post depreciation');
      toast.success(lang === 'ar' ? `تم ترحيل إهلاك ${Number(data.total || 0).toLocaleString()} SYP` : `Posted depreciation: ${Number(data.total || 0).toLocaleString()} SYP`);
      setDepreciationPreview(null); void loadAssets(); void loadReport();
    } catch (error) { toast.error(error instanceof Error ? error.message : String(error)); }
  };

  const loadFinancialAccounts = useCallback(async () => {
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/accounting/accounts`, { headers: withAuthHeaders({ Accept: 'application/json' }) });
      const data = await response.json();
      if (!response.ok) throw new Error(data?.message || 'Failed to load accounts');
      setAccounts(Array.isArray(data?.accounts) ? data.accounts : []);
    } catch (error) {
      toast.error(error instanceof Error ? error.message : 'Failed to load accounts');
    }
  }, [apiBaseUrl]);

  useEffect(() => {
    if (activeTab === 'opening-balances' || activeTab === 'accounts') void loadFinancialAccounts();
  }, [activeTab, loadFinancialAccounts]);

  const createFinancialAccount = async () => {
    const name = newAccountForm.name.trim();
    if (!name) {
      toast.error(lang === 'ar' ? 'أدخل اسم الحساب' : 'Enter account name');
      return;
    }
    setAccountSaving(true);
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/accounting/accounts`, {
        method: 'POST',
        headers: withAuthHeaders({ 'Content-Type': 'application/json', Accept: 'application/json' }),
        body: JSON.stringify({ ...newAccountForm, name }),
      });
      const data = await response.json();
      if (!response.ok || !data?.ok) throw new Error(data?.message || 'Failed to create account');
      setAccounts((prev) => [...prev, data.account].sort((a:any, b:any) => String(a.code).localeCompare(String(b.code), undefined, { numeric: true })));
      setOpeningFinancialForm((prev) => ({ ...prev, financial_account_id: String(data.account.id) }));
      setAccountDialogOpen(false);
      setNewAccountForm({ name: '', name_ar: '', account_type: 'cash', currency: 'SYP', bank_name: '', account_number: '' });
      toast.success(lang === 'ar' ? 'تم إنشاء الحساب ويمكنك الآن إدخال رصيده الافتتاحي' : 'Account created; you can now enter its opening balance');
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
    } finally {
      setAccountSaving(false);
    }
  };

  const loadAccountingBooks = useCallback(async () => {
    if (!accountingStartDate || !accountingEndDate) return;
    try {
      const headers = withAuthHeaders({ Accept: 'application/json' });
      const qs = new URLSearchParams({ start_date: accountingStartDate, end_date: accountingEndDate }).toString();
      const [a, j, tb, st] = await Promise.all([
        fetch(`${apiBaseUrl}/api/admin/accounting/accounts`, { headers }),
        fetch(`${apiBaseUrl}/api/admin/accounting/journals?${qs}`, { headers }),
        fetch(`${apiBaseUrl}/api/admin/accounting/trial-balance?${qs}`, { headers }),
        fetch(`${apiBaseUrl}/api/admin/accounting/statements?${qs}`, { headers }),
      ]);
      const [ad, jd, td, sd] = await Promise.all([a.json(), j.json(), tb.json(), st.json()]);
      if (!a.ok) throw new Error(ad?.message || 'Failed to load accounts');
      if (!j.ok) throw new Error(jd?.message || 'Failed to load journals');
      if (!tb.ok) throw new Error(td?.message || 'Failed to load trial balance');
      if (!st.ok) throw new Error(sd?.message || 'Failed to load statements');
      setAccounts(Array.isArray(ad?.accounts) ? ad.accounts : []);
      setJournalRows(Array.isArray(jd?.journals) ? jd.journals : []);
      setTrialBalanceData(td);
      setStatementsData(sd);
      if (!selectedLedgerAccount && ad?.accounts?.[0]?.id) setSelectedLedgerAccount(String(ad.accounts[0].id));
    } catch (error) {
      toast.error(error instanceof Error ? error.message : 'Failed to load accounting books');
    }
  }, [apiBaseUrl, accountingStartDate, accountingEndDate, selectedLedgerAccount]);

  const loadLedger = useCallback(async () => {
    if (!selectedLedgerAccount || !accountingStartDate || !accountingEndDate) return;
    try {
      const qs = new URLSearchParams({ start_date: accountingStartDate, end_date: accountingEndDate }).toString();
      const response = await fetch(`${apiBaseUrl}/api/admin/accounting/ledger/${selectedLedgerAccount}?${qs}`, {
        headers: withAuthHeaders({ Accept: 'application/json' }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data?.message || 'Failed to load ledger');
      setLedgerRows(Array.isArray(data?.rows) ? data.rows : []);
      setLedgerOpening(Number(data?.opening_balance || 0));
      setLedgerClosing(Number(data?.closing_balance || 0));
    } catch (error) {
      toast.error(error instanceof Error ? error.message : 'Failed to load ledger');
      setLedgerRows([]);
    }
  }, [apiBaseUrl, selectedLedgerAccount, accountingStartDate, accountingEndDate]);

  const postManualJournal = async () => {
    const lines = manualJournalLines.map((l) => ({
      account_id: Number(l.account_id),
      debit: Number(l.debit || 0),
      credit: Number(l.credit || 0),
      description: l.description || undefined,
    })).filter((l) => l.account_id && (l.debit > 0 || l.credit > 0));
    const debit = lines.reduce((sum, l) => sum + l.debit, 0);
    const credit = lines.reduce((sum, l) => sum + l.credit, 0);
    if (!manualJournalDescription.trim()) { toast.error(lang === 'ar' ? 'أدخل وصف القيد' : 'Enter a journal description'); return; }
    if (lines.length < 2 || Math.abs(debit - credit) > 0.005) { toast.error(lang === 'ar' ? 'القيد يجب أن يحتوي على سطرين على الأقل وأن يكون متوازنًا' : 'Journal needs at least two lines and must balance'); return; }
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/accounting/journals`, {
        method: 'POST',
        headers: withAuthHeaders({ 'Content-Type': 'application/json', Accept: 'application/json' }),
        body: JSON.stringify({ entry_date: manualJournalDate, description: manualJournalDescription.trim(), lines }),
      });
      const data = await response.json();
      if (!response.ok || !data?.ok) throw new Error(data?.message || 'Failed to post journal');
      toast.success(lang === 'ar' ? 'تم ترحيل القيد اليومي' : 'Journal posted');
      setManualJournalDialog(false);
      setManualJournalDescription('');
      setManualJournalLines([{ account_id: '', debit: '', credit: '', description: '' }]);
      void loadAccountingBooks();
      void loadLedger();
      void loadReport();
    } catch (error) { toast.error(error instanceof Error ? error.message : String(error)); }
  };

  const reverseJournal = async (id: number) => {
    if (!window.confirm(lang === 'ar' ? 'سيتم إنشاء قيد عكسي بدل حذف القيد. متابعة؟' : 'A reversal journal will be posted instead of deleting the journal. Continue?')) return;
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/accounting/journals/${id}/reverse`, { method: 'POST', headers: withAuthHeaders({ Accept: 'application/json' }) });
      const data = await response.json();
      if (!response.ok || !data?.ok) throw new Error(data?.message || 'Failed to reverse journal');
      toast.success(lang === 'ar' ? 'تم عكس القيد' : 'Journal reversed');
      void loadAccountingBooks();
      void loadLedger();
    } catch (error) { toast.error(error instanceof Error ? error.message : String(error)); }
  };

  const toDateInputValue = (value: unknown) => {
    const text = String(value ?? '').trim();
    if (!text) return '';
    // HTML date inputs accept only YYYY-MM-DD. Backend timestamps may be ISO strings.
    return text.slice(0, 10);
  };

  const loadOpeningBalances = useCallback(async () => {
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/opening-balances`, { headers: withAuthHeaders({ Accept: 'application/json' }) });
      const data = await response.json();
      if (!response.ok) throw new Error(data?.error || data?.message || 'Failed to load opening balances');
      const rows = Array.isArray(data) ? data : [];
      setOpeningBalances(rows);
      const firstDate = rows.length ? toDateInputValue(rows[rows.length - 1]?.opening_balance_date) : '';
      if (firstDate) { setAccountingStartDefault(firstDate); setAccountingStartDate(current => current || firstDate); setPeriodStart(current => current || firstDate); setOpeningDate(current => current || firstDate); }
      const active = rows.find((row:any) => Number(row.id) === Number(activeOpeningId)) || rows[0];
      if (active) {
        if (!activeOpeningId) setActiveOpeningId(Number(active.id));
        setOpeningInventoryRows(Array.isArray(active.inventory) ? active.inventory : []);
        setOpeningFixedAssetRows(Array.isArray(active.fixed_assets) ? active.fixed_assets : []);
      }
      if (Array.isArray(active?.financial_accounts)) {
        // Keep the master account list loaded separately; this is only the rows already attached to this opening balance.
      }
    } catch (error) { toast.error(error instanceof Error ? error.message : 'Failed to load opening balances'); }
  }, [apiBaseUrl]);

  const importGoLiveOpeningData = async (id:number) => {
    setOpeningImporting(true);
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/opening-balances/${id}/import-go-live`, { method:'POST', headers:withAuthHeaders({Accept:'application/json'}) });
      const data = await response.json();
      if (!response.ok || !data?.ok) throw new Error(data?.error || data?.message || 'Opening import failed');
      setOpeningInventoryRows(Array.isArray(data.inventory) ? data.inventory : []);
      setOpeningFixedAssetRows(Array.isArray(data.fixed_assets) ? data.fixed_assets : []);
      setActiveOpeningId(id);
      await loadOpeningBalances();
      toast.success(lang==='ar' ? `تم استيراد ${data.inventory_count} مادة و${data.fixed_asset_count} أداة — إجمالي الأصول ${fmt(Number(data.total_opening_assets||0))} ل.س` : `Imported ${data.inventory_count} materials and ${data.fixed_asset_count} tools — total opening assets ${fmt(Number(data.total_opening_assets||0))} SYP`);
    } catch(error){ toast.error(error instanceof Error ? error.message : String(error)); } finally { setOpeningImporting(false); }
  };

  const createOpeningBalance = async () => {
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/opening-balances`, { method:'POST', headers:withAuthHeaders({'Content-Type':'application/json',Accept:'application/json'}), body:JSON.stringify({opening_balance_date:openingDate, notes:openingNote || undefined}) });
      const data=await response.json();
      if (!response.ok) throw new Error(data?.error || data?.message || 'Failed to create opening balance');
      setActiveOpeningId(Number(data.id));
      setAccountingStartDefault(toDateInputValue(openingDate));
      setAccountingStartDate(current => current || toDateInputValue(openingDate));
      setPeriodStart(current => current || toDateInputValue(openingDate));
      setOpeningInventoryRows([]); setOpeningFixedAssetRows([]);
      setOpeningFinancialForm({ financial_account_id: '', balance: '', exchange_rate: '1', notes: '' });
      setOpeningNote('');
      await loadOpeningBalances();
      toast.success(lang==='ar'?'تم إنشاء رصيد افتتاحي جديد':'Opening balance created');
    } catch(error){ toast.error(error instanceof Error ? error.message : String(error)); }
  };

  const addOpeningInventory = async () => {
    if (!activeOpeningId || !openingMaterialId) { toast.error(lang==='ar'?'أنشئ/اختر الرصيد الافتتاحي والمادة أولًا':'Create/select an opening balance and a material first'); return; }
    const material=materials.find((m:any)=>String(m.id)===openingMaterialId);
    const qty=Number(openingQty), unitCost=Number(openingUnitCost);
    if (!material || !Number.isFinite(qty) || qty<=0 || !Number.isFinite(unitCost) || unitCost<0) { toast.error(lang==='ar'?'أدخل الكمية والتكلفة بشكل صحيح':'Enter a valid quantity and unit cost'); return; }
    try {
      const response=await fetch(`${apiBaseUrl}/api/admin/opening-balances/${activeOpeningId}/inventory`,{method:'POST',headers:withAuthHeaders({'Content-Type':'application/json',Accept:'application/json'}),body:JSON.stringify({material_id:Number(material.id),material_name:material.name,material_name_ar:material.name_ar || undefined,material_type:openingType,unit:material.base_unit,quantity:qty,unit_cost:unitCost,currency:'SYP',exchange_rate:1})});
      const data=await response.json();
      if(!response.ok) throw new Error(data?.error || data?.message || 'Failed to add opening inventory');
      setOpeningQty(''); setOpeningUnitCost('');
      toast.success(lang==='ar'?'تمت إضافة مادة افتتاحية':'Opening inventory item added');
      await loadOpeningBalances();
    } catch(error){ toast.error(error instanceof Error ? error.message : String(error)); }
  };

  const addOpeningFinancialAccount = async () => {
    if (!activeOpeningId || !openingFinancialForm.financial_account_id) {
      toast.error(lang === 'ar' ? 'اختر حسابًا من دليل الحسابات أولًا' : 'Select an account from the chart of accounts first');
      return;
    }
    const balance = Number(openingFinancialForm.balance);
    const exchangeRate = Number(openingFinancialForm.exchange_rate);
    if (!Number.isFinite(balance) || balance < 0 || !Number.isFinite(exchangeRate) || exchangeRate <= 0) {
      toast.error(lang === 'ar' ? 'أدخل الرصيد وسعر الصرف بشكل صحيح' : 'Enter a valid balance and exchange rate');
      return;
    }
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/opening-balances/${activeOpeningId}/financial-accounts`, {
        method: 'POST',
        headers: withAuthHeaders({ 'Content-Type': 'application/json', Accept: 'application/json' }),
        body: JSON.stringify({ financial_account_id: Number(openingFinancialForm.financial_account_id), balance, exchange_rate: exchangeRate, notes: openingFinancialForm.notes || undefined }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data?.error || data?.message || 'Failed to add opening financial balance');
      setOpeningFinancialForm({ financial_account_id: '', balance: '', exchange_rate: '1', notes: '' });
      await loadOpeningBalances();
      toast.success(lang === 'ar' ? 'تمت إضافة رصيد الحساب إلى الافتتاحية' : 'Opening account balance added');
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
    }
  };


  const addOpeningPartyBalance = async () => {
    if (!activeOpeningId || !openingPartyForm.party_name.trim()) { toast.error(lang==='ar' ? 'أدخل اسم الجهة بعد إنشاء الرصيد الافتتاحي' : 'Enter the party name after creating the opening balance'); return; }
    const amount = Number(openingPartyForm.amount);
    const exchangeRate = Number(openingPartyForm.exchange_rate);
    if (!Number.isFinite(amount) || amount < 0 || !Number.isFinite(exchangeRate) || exchangeRate <= 0) { toast.error(lang==='ar' ? 'أدخل المبلغ وسعر الصرف بشكل صحيح' : 'Enter a valid amount and exchange rate'); return; }
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/opening-balances/${activeOpeningId}/payables-receivables`, { method:'POST', headers:withAuthHeaders({'Content-Type':'application/json',Accept:'application/json'}), body:JSON.stringify({ ...openingPartyForm, amount, exchange_rate: exchangeRate }) });
      const data = await response.json();
      if (!response.ok) throw new Error(data?.error || data?.message || 'Failed to add opening receivable/payable');
      setOpeningPartyForm({ party_name:'', party_name_ar:'', type:'payable', amount:'', currency:'SYP', exchange_rate:'1', due_date:'', description:'', notes:'' });
      await loadOpeningBalances();
      toast.success(lang==='ar' ? 'تمت إضافة الذمة الافتتاحية' : 'Opening receivable/payable added');
    } catch(error) { toast.error(error instanceof Error ? error.message : String(error)); }
  };

  const confirmOpeningBalance = async (id:number) => {
    if(!window.confirm(lang==='ar'?'بعد التأكيد سيُرحّل القيد المحاسبي ويُحدّث دفتر مخزون المواد. متابعة؟':'Confirmation posts the accounting entry and updates the unified inventory ledger. Continue?')) return;
    try {
      const response=await fetch(`${apiBaseUrl}/api/admin/opening-balances/${id}/confirm`,{method:'POST',headers:withAuthHeaders({Accept:'application/json'})});
      const data=await response.json();
      if(!response.ok) throw new Error(data?.error || data?.message || 'Failed to confirm opening balance');
      toast.success(lang==='ar'?'تم ترحيل الرصيد الافتتاحي':'Opening balance posted');
      await loadOpeningBalances(); void loadAccountingBooks(); void loadLedger(); void loadReport();
    }catch(error){toast.error(error instanceof Error ? error.message : String(error));}
  };

  const lockOpeningBalance = async (id:number) => {
    try {
      const response=await fetch(`${apiBaseUrl}/api/admin/opening-balances/${id}/lock`,{method:'POST',headers:withAuthHeaders({Accept:'application/json'})});
      const data=await response.json();
      if(!response.ok) throw new Error(data?.error || data?.message || 'Failed to lock opening balance');
      await loadOpeningBalances();
      toast.success(lang==='ar'?'تم قفل الرصيد الافتتاحي':'Opening balance locked');
    }catch(error){toast.error(error instanceof Error ? error.message : String(error));}
  };

  const loadAccountingControls = useCallback(async () => {
    try {
      const headers = withAuthHeaders({ Accept: 'application/json' });
      const [p, r, o] = await Promise.all([
        fetch(`${apiBaseUrl}/api/admin/accounting/periods`, { headers }),
        fetch(`${apiBaseUrl}/api/admin/accounting/reconciliations`, { headers }),
        fetch(`${apiBaseUrl}/api/admin/accounting/reconciliation-options`, { headers }),
      ]);
      const [pd, rd, od] = await Promise.all([p.json(), r.json(), o.json()]);
      if (!p.ok) throw new Error(pd?.message || 'Failed to load accounting periods');
      if (!r.ok) throw new Error(rd?.message || 'Failed to load reconciliations');
      setAccountingPeriods(Array.isArray(pd?.periods) ? pd.periods : []);
      setReconciliations(Array.isArray(rd?.reconciliations) ? rd.reconciliations : []);
      setReconciliationOptions(Array.isArray(od?.accounts) ? od.accounts : []);
      if (!reconciliationAccountId && od?.accounts?.[0]?.id) setReconciliationAccountId(String(od.accounts[0].id));
    } catch (error) {
      toast.error('Failed to load accounting controls');
      setAccountingPeriods([]); setReconciliations([]); setReconciliationOptions([]);
    }
  }, [apiBaseUrl, reconciliationAccountId]);

  const createAccountingPeriod = async () => {
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/accounting/periods`, {
        method: 'POST', headers: withAuthHeaders({ 'Content-Type': 'application/json', Accept: 'application/json' }),
        body: JSON.stringify({ period_start: periodStart, period_end: periodEnd, name: periodName || undefined }),
      });
      const data = await response.json();
      if (!response.ok || !data?.ok) throw new Error(data?.message || 'Failed to create period');
      toast.success(lang === 'ar' ? 'تم إنشاء الفترة المحاسبية' : 'Accounting period created');
      setPeriodName(''); void loadAccountingControls();
    } catch (error) { toast.error(error instanceof Error ? error.message : String(error)); }
  };

  const closeAccountingPeriod = async (id: number) => {
    if (!window.confirm(lang === 'ar' ? 'إقفال الفترة سيمنع التعديلات المالية ضمنها. متابعة؟' : 'Closing this period will block financial changes inside it. Continue?')) return;
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/accounting/periods/${id}/close`, { method:'POST', headers:withAuthHeaders({Accept:'application/json'}) });
      const data = await response.json();
      if (!response.ok || !data?.ok) throw new Error(data?.message || 'Failed to close period');
      toast.success(lang === 'ar' ? 'تم إقفال الفترة' : 'Period closed'); void loadAccountingControls();
    } catch (error) { toast.error(error instanceof Error ? error.message : String(error)); }
  };

  const reopenAccountingPeriod = async (id: number) => {
    if (!window.confirm(lang === 'ar' ? 'إعادة فتح الفترة تتطلب صلاحية المالك. متابعة؟' : 'Reopening a period requires owner access. Continue?')) return;
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/accounting/periods/${id}/reopen`, { method:'POST', headers:withAuthHeaders({Accept:'application/json'}) });
      const data = await response.json();
      if (!response.ok || !data?.ok) throw new Error(data?.message || 'Failed to reopen period');
      toast.success(lang === 'ar' ? 'تمت إعادة فتح الفترة' : 'Period reopened'); void loadAccountingControls();
    } catch (error) { toast.error(error instanceof Error ? error.message : String(error)); }
  };

  const saveReconciliation = async () => {
    const balance = Number(statementBalance);
    if (!reconciliationAccountId || !Number.isFinite(balance) || balance < 0) { toast.error(lang === 'ar' ? 'أدخل الحساب ورصيد كشف صحيح' : 'Select an account and enter a valid statement balance'); return; }
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/accounting/reconciliations`, {
        method:'POST', headers:withAuthHeaders({'Content-Type':'application/json',Accept:'application/json'}),
        body:JSON.stringify({financial_account_id:Number(reconciliationAccountId), reconciliation_date:reconciliationDate, statement_balance:balance, notes:reconciliationNotes || undefined}),
      });
      const data = await response.json();
      if (!response.ok || !data?.ok) throw new Error(data?.message || 'Failed to reconcile account');
      toast.success(data.reconciliation?.status === 'matched' ? (lang === 'ar' ? 'تمت المطابقة بنجاح' : 'Account matched') : (lang === 'ar' ? 'تم تسجيل فرق المطابقة للمراجعة' : 'Reconciliation difference recorded'));
      setStatementBalance(''); setReconciliationNotes(''); void loadAccountingControls();
    } catch (error) { toast.error(error instanceof Error ? error.message : String(error)); }
  };

  const loadTaxes = useCallback(async () => {
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/tax-configurations`, {
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      const data = await response.json();
      setTaxes(Array.isArray(data) ? data : []);
    } catch (error) {
      toast.error("Failed to load taxes");
      setTaxes([]);
    }
  }, [apiBaseUrl]);


  useEffect(() => {
    void loadMaterials();
    void loadPurchases();
    void loadExpenses();
    void loadSales();
    void loadReport();
    void loadAssets();
    void loadTaxes();
    void loadAccountingControls();
    void loadAccountingBooks();
    void loadOpeningBalances();
  }, [loadMaterials, loadPurchases, loadExpenses, loadSales, loadReport, loadAssets, loadTaxes, loadAccountingControls, loadAccountingBooks, loadOpeningBalances]);

  useEffect(() => {
    void loadLedger();
  }, [loadLedger]);

  const handleMaterialChange = (id: string) => {
    const material = materials.find((m) => String(m.id) === id);
    if (material) {
      setPurchaseForm((prev) => ({
        ...prev,
        material_id: id,
        item_name: material.name || "",
        cost_per_unit: Number(material.avg_unit_cost ?? material.cost_per_unit ?? 0),
      }));
    }
  };

  const selectedMaterial = materials.find((m) => String(m.id) === purchaseForm.material_id);
  const purchaseTotal = Number(purchaseForm.quantity || 0) * Number(purchaseForm.cost_per_unit || 0);

  const handlePurchaseSubmit = async () => {
    if (!purchaseForm.material_id) {
      toast.error(lang === "ar" ? "يرجى اختيار المادة" : "Please select a material");
      return;
    }
    if (Number(purchaseForm.quantity) <= 0) {
      toast.error(lang === "ar" ? "يرجى إدخال كمية صحيحة" : "Please enter a valid quantity");
      return;
    }
    if (Number(purchaseForm.cost_per_unit) < 0) {
      toast.error(lang === "ar" ? "يرجى إدخال تكلفة صحيحة" : "Please enter a valid cost");
      return;
    }

    try {
      setLoading(true);
      const url = editingPurchase
        ? `${apiBaseUrl}/api/admin/inventory/purchases/${editingPurchase.id}`
        : `${apiBaseUrl}/api/admin/inventory/purchases`;
      const method = editingPurchase ? "PATCH" : "POST";

      const response = await fetch(url, {
        method,
        headers: withAuthHeaders({ "Content-Type": "application/json" }),
        body: JSON.stringify({
          material_id: purchaseForm.material_id,
          quantity: Number(purchaseForm.quantity),
          cost_per_unit: Number(purchaseForm.cost_per_unit),
          purchase_date: purchaseForm.purchase_date,
          supplier: purchaseForm.supplier,
          invoice_number: purchaseForm.invoice_number,
          notes: purchaseForm.notes,
          payment_method: purchaseForm.payment_method,
          paid_amount: purchaseForm.payment_method === "credit" ? 0 : purchaseTotal,
        }),
      });
      const data = await response.json();

      if (!response.ok) {
        toast.error(data?.message || "Failed to save purchase");
        return;
      }

      toast.success(editingPurchase ? "Purchase updated" : "Purchase added");
      setPurchaseDialog(false);
      setEditingPurchase(null);
      setPurchaseForm({
        material_id: "",
        item_name: "",
        quantity: 0,
        cost_per_unit: 0,
        purchase_date: new Date().toISOString().slice(0, 10),
        supplier: "",
        invoice_number: "",
        notes: "",
        item_type: "raw_material",
        payment_method: "cash",
        paid_amount: 0,
      });
      void loadPurchases();
      void loadReport();
      void loadMaterials();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
      toast.error("Failed to save purchase");
    } finally {
      setLoading(false);
    }
  };

  const handleEditPurchase = (purchase: Purchase) => {
    setEditingPurchase(purchase);
    const materialId = String(purchase.material_id ?? purchase.raw_material_id ?? "");
    const material = materials.find((m) => String(m.id) === materialId);
    setPurchaseForm({
      material_id: materialId,
      item_name: purchase.item_name || material?.name || "",
      quantity: Number(purchase.quantity || 0),
      cost_per_unit: Number(purchase.unit_cost || 0),
      purchase_date: (purchase.purchase_date || "").slice(0, 10) || new Date().toISOString().slice(0, 10),
      supplier: purchase.supplier || "",
      invoice_number: purchase.invoice_number || "",
      notes: purchase.notes || "",
      item_type: "raw_material",
      payment_method: (purchase as any).payment_method || "cash",
      paid_amount: Number((purchase as any).paid_amount || 0),
    });
    setPurchaseDialog(true);
  };

  const handleDeletePurchase = async (purchaseId: number | string) => {
    if (!purchaseId && purchaseId !== 0) return;
    const id = String(purchaseId);
    const confirmed = window.confirm(
      lang === "ar"
        ? "هل أنت متأكد من حذف فاتورة الشراء؟"
        : "Are you sure you want to delete this purchase invoice?"
    );
    if (!confirmed) return;
    try {
      setLoading(true);
      const response = await fetch(`${apiBaseUrl}/api/admin/inventory/purchases/${id}`, {
        method: "DELETE",
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      if (!response.ok) {
        const data = await response.json().catch(() => null);
        throw new Error(data?.message || "Failed to delete purchase");
      }
      toast.success(lang === "ar" ? "تم حذف فاتورة الشراء" : "Purchase invoice deleted");
      await loadPurchases();
    } catch (error) {
      toast.error("Failed to delete purchase");
      toast.error(
        lang === "ar" ? "تعذّر حذف فاتورة الشراء" : "Failed to delete purchase invoice"
      );
    } finally {
      setLoading(false);
    }
  };

  const handleNewItemSubmit = async () => {
    if (!newItemForm.name.trim()) {
      toast.error(lang === "ar" ? "يرجى إدخال اسم الصنف" : "Please enter item name");
      return;
    }
    try {
      setLoading(true);
      const response = await fetch(`${apiBaseUrl}/api/admin/inventory/materials`, {
        method: "POST",
        headers: withAuthHeaders({ "Content-Type": "application/json" }),
        body: JSON.stringify({
          name: newItemForm.name,
          name_ar: newItemForm.name_ar || null,
          material_category: newItemForm.type === "essential_oil" ? "perfume_oil" : newItemForm.type === "packaging" || newItemForm.type === "bottle" ? "packaging" : newItemForm.type,
          base_unit: newItemForm.unit === "grams" ? "g" : newItemForm.unit === "liters" ? "l" : "pcs",
          current_stock: Number(newItemForm.current_stock || 0),
          min_stock: Number(newItemForm.min_stock || 0),
          avg_unit_cost: Number(newItemForm.cost_per_unit || 0),
          supplier_name: newItemForm.supplier || null,
          notes: newItemForm.notes || null,
          is_active: true,
          track_fractional: newItemForm.unit !== "pieces",
        }),
      });
      const data = await response.json();
      if (!response.ok) {
        toast.error(data?.message || "Failed to add item");
        return;
      }
      toast.success(lang === "ar" ? "تم إضافة الصنف" : "Item added");
      setNewItemDialog(false);
      setNewItemForm({
        name: "",
        name_ar: "",
        type: "essential_oil",
        unit: "grams",
        cost_per_unit: 0,
        current_stock: 0,
        min_stock: 0,
        supplier: "",
        notes: "",
      });
      void loadMaterials();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
      toast.error("Failed to add item");
    } finally {
      setLoading(false);
    }
  };

  const expenseCategoryLabel = (cat: string) => {
    const map: Record<string, string> = {
      rent: t.catRent,
      transport: t.catTransport,
      salaries: t.catSalaries,
      marketing: t.catMarketing,
      utilities: t.catUtilities,
      other: t.catOther,
    };
    return map[cat] || cat;
  };

  const handleExpenseSubmit = async () => {
    if (!expenseForm.amount || Number(expenseForm.amount) <= 0) {
      toast.error(lang === "ar" ? "يرجى إدخال مبلغ صحيح" : "Please enter a valid amount");
      return;
    }
    if (!expenseForm.description.trim()) {
      toast.error(lang === "ar" ? "يرجى إدخال الوصف" : "Please enter a description");
      return;
    }

    try {
      setLoading(true);
      const url = editingExpense
        ? `${apiBaseUrl}/api/admin/inventory/expenses/${editingExpense.id}`
        : `${apiBaseUrl}/api/admin/inventory/expenses`;
      const method = editingExpense ? "PATCH" : "POST";

      const payload: any = {
        category: expenseForm.category,
        description: expenseForm.description,
        amount: Number(expenseForm.amount),
        expense_date: expenseForm.expense_date,
        payment_method: expenseForm.payment_method,
        vendor: expenseForm.vendor,
        notes: expenseForm.notes,
      };
      if (expenseForm.category === "rent") {
        payload.rent_duration_days = Number(expenseForm.rent_duration_days || 0);
      }

      const response = await fetch(url, {
        method,
        headers: withAuthHeaders({ "Content-Type": "application/json" }),
        body: JSON.stringify(payload),
      });
      const data = await response.json();

      if (!response.ok) {
        toast.error(data?.message || "Failed to save expense");
        return;
      }

      toast.success(editingExpense ? "Expense updated" : "Expense added");
      setExpenseDialog(false);
      setEditingExpense(null);
      setExpenseForm({
        category: "rent",
        description: "",
        amount: 0,
        expense_date: new Date().toISOString().slice(0, 10),
        payment_method: "cash",
        rent_duration_days: 1,
        vendor: "",
        notes: "",
      });
      void loadExpenses();
      void loadReport();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
      toast.error("Failed to save expense");
    } finally {
      setLoading(false);
    }
  };

  const handleEditExpense = (expense: Expense) => {
    setEditingExpense(expense);
    setExpenseForm({
      category: expense.category || "other",
      description: expense.description || "",
      amount: Number(expense.amount || 0),
      expense_date: expense.expense_date || new Date().toISOString().slice(0, 10),
      payment_method: expense.payment_method || "cash",
      rent_duration_days: Number(expense.rent_duration_days || 1),
      vendor: expense.vendor || "",
      notes: expense.notes || "",
    });
    setExpenseDialog(true);
  };

  const handleDeleteExpense = async (id: number) => {
    if (!window.confirm(lang === "ar" ? "حذف هذا المصروف؟" : "Delete this expense?")) return;
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/inventory/expenses/${id}`, {
        method: "DELETE",
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      if (!response.ok) {
        toast.error(lang === "ar" ? "فشل الحذف" : "Failed to delete");
        return;
      }
      toast.success(lang === "ar" ? "تم الحذف" : "Deleted");
      void loadExpenses();
      void loadReport();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
      toast.error(lang === "ar" ? "فشل الحذف" : "Failed to delete");
    }
  };

  const handleAssetSubmit = async () => {
    if (!assetForm.name.trim()) {
      toast.error(lang === "ar" ? "يرجى إدخال اسم الأصل" : "Please enter asset name");
      return;
    }
    if (!assetForm.asset_number.trim()) {
      toast.error(lang === "ar" ? "يرجى إدخال رقم الأصل" : "Please enter asset number");
      return;
    }
    if (Number(assetForm.purchase_cost) <= 0) {
      toast.error(lang === "ar" ? "يرجى إدخال تكلفة صحيحة" : "Please enter a valid cost");
      return;
    }

    try {
      setLoading(true);
      const url = editingAsset
        ? `${apiBaseUrl}/api/admin/fixed-assets/${editingAsset.id}`
        : `${apiBaseUrl}/api/admin/fixed-assets`;
      const method = editingAsset ? "PATCH" : "POST";

      const response = await fetch(url, {
        method,
        headers: withAuthHeaders({ "Content-Type": "application/json" }),
        body: JSON.stringify(assetForm),
      });
      const data = await response.json();

      if (!response.ok) {
        toast.error(data?.message || "Failed to save asset");
        return;
      }

      toast.success(editingAsset ? "Asset updated" : "Asset added");
      setAssetDialog(false);
      setEditingAsset(null);
      setAssetForm({
        name: "",
        name_ar: "",
        asset_number: "",
        category: "equipment",
        description: "",
        quantity: 1,
        purchase_cost: 0,
        purchase_date: new Date().toISOString().slice(0, 10),
        supplier: "",
        invoice_number: "",
        depreciation_method: "straight_line",
        useful_life_years: 5,
        total_estimated_units: 0,
        salvage_value: 0,
        depreciation_start_date: new Date().toISOString().slice(0, 10),
        location: "",
        serial_number: "",
        notes: "",
      });
      void loadAssets();
      void loadReport();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
      toast.error("Failed to save asset");
    } finally {
      setLoading(false);
    }
  };

  const handleDeleteAsset = async (id: number) => {
    if (!window.confirm(lang === "ar" ? "حذف هذا الأصل؟" : "Delete this asset?")) return;
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/fixed-assets/${id}`, {
        method: "DELETE",
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      if (!response.ok) {
        toast.error(lang === "ar" ? "فشل الحذف" : "Failed to delete");
        return;
      }
      toast.success(lang === "ar" ? "تم الحذف" : "Deleted");
      void loadAssets();
      void loadReport();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
      toast.error(lang === "ar" ? "فشل الحذف" : "Failed to delete");
    }
  };

  const handleTaxSubmit = async () => {
    if (!taxForm.name.trim()) {
      toast.error(lang === "ar" ? "يرجى إدخال اسم الضريبة" : "Please enter tax name");
      return;
    }
    if (Number(taxForm.rate) < 0) {
      toast.error(lang === "ar" ? "يرجى إدخال نسبة صحيحة" : "Please enter a valid rate");
      return;
    }

    try {
      setLoading(true);
      const url = editingTax
        ? `${apiBaseUrl}/api/admin/tax-configurations/${editingTax.id}`
        : `${apiBaseUrl}/api/admin/tax-configurations`;
      const method = editingTax ? "PATCH" : "POST";

      const response = await fetch(url, {
        method,
        headers: withAuthHeaders({ "Content-Type": "application/json" }),
        body: JSON.stringify(taxForm),
      });
      const data = await response.json();

      if (!response.ok) {
        toast.error(data?.message || "Failed to save tax");
        return;
      }

      toast.success(editingTax ? "Tax updated" : "Tax added");
      setTaxDialog(false);
      setEditingTax(null);
      setTaxForm({
        name: "",
        name_ar: "",
        tax_type: "income_tax",
        rate: 0,
        is_active: true,
        post_to_ledger: false,
        tax_inclusive: true,
        effective_date: new Date().toISOString().slice(0, 10),
        expiry_date: "",
        description: "",
        calculation_method: "percentage",
        fixed_amount: 0,
        applicable_to: "profit",
        applicable_categories: [],
        notes: "",
      });
      void loadTaxes();
      void loadReport();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
      toast.error("Failed to save tax");
    } finally {
      setLoading(false);
    }
  };

  const handleDeleteTax = async (id: number) => {
    if (!window.confirm(lang === "ar" ? "حذف هذه الضريبة؟" : "Delete this tax?")) return;
    try {
      const response = await fetch(`${apiBaseUrl}/api/admin/tax-configurations/${id}`, {
        method: "DELETE",
        headers: withAuthHeaders({ Accept: "application/json" }),
      });
      if (!response.ok) {
        toast.error(lang === "ar" ? "فشل الحذف" : "Failed to delete");
        return;
      }
      toast.success(lang === "ar" ? "تم الحذف" : "Deleted");
      void loadTaxes();
      void loadReport();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : String(error));
      toast.error(lang === "ar" ? "فشل الحذف" : "Failed to delete");
    }
  };

  const reportBreakdown = reportData?.breakdown?.[reportPeriod] || { revenue: [], other_income: [], expenses: [], cogs: [], depreciation: [], purchases: [] };
  const revenueRows: PeriodRow[] = reportBreakdown.revenue || [];
  const expenseRows: PeriodRow[] = reportBreakdown.expenses || [];
  const purchaseRows: PeriodRow[] = reportBreakdown.purchases || [];

  const allPeriodLabels = Array.from(new Set([
    ...revenueRows.map((r) => r.label),
    ...expenseRows.map((r) => r.label),
    ...(reportBreakdown.other_income || []).map((r: PeriodRow) => r.label),
    ...(reportBreakdown.cogs || []).map((r: PeriodRow) => r.label),
    ...(reportBreakdown.depreciation || []).map((r: PeriodRow) => r.label),
    ...purchaseRows.map((r) => r.label),
  ])).sort();

  const maxTotal = Math.max(
    1,
    ...revenueRows.map((r) => Number(r.total)),
    ...expenseRows.map((r) => Number(r.total)),
    ...purchaseRows.map((r) => Number(r.total))
  );

  const renderBarChart = (rows: PeriodRow[], color: string) => (
    <div className="space-y-1 ">
      {rows.length === 0 && (
        <div className="text-sm text-muted-foreground py-4 text-center ">—</div>
      )}
      {rows.map((row) => (
        <div key={row.label} className="flex items-center gap-2 text-sm">
          <span className="w-24 shrink-0 truncate text-muted-foreground">{row.label}</span>
<div className="flex-1 h-4 bg-muted rounded-full overflow-hidden">
            <div
              className="h-full rounded-full"
              style={{ width: `${Math.min(92, Math.max(2, (Number(row.total) / maxTotal) * 100))}%`, backgroundColor: color }}
            />
          </div>
          <div className="w-2 shrink-0" />
          <span className="w-24 shrink-0 text-right font-medium">{fmt(Number(row.total))} SYP</span>
        </div>
      ))}
    </div>
  );

  return (
    <div className="space-y-6  space-x-7">
      <div className="flex items-center justify-between space-x-12">
        <div className="space-y-1 ">
          <h1 className="text-2xl font-display font-bold">{t.title}</h1>
          <p className="text-muted-foreground text-sm">{t.subtitle}</p>
        </div>
        <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200">
          <Wallet className="h-4 w-4 mr-1" />
          {lang === "ar" ? "العملة: ليرة سورية (SYP)" : "Currency: SYP"}
        </Badge>
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab} className="w-auto space-x-5 ">
        <TabsList className="grid w-full grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-12 gap-1 h-auto">
          <TabsTrigger value="journals"><FileText className="h-4 w-4 mr-2" />{lang === "ar" ? "القيود اليومية" : "Journal Entries"}</TabsTrigger>
          <TabsTrigger value="ledger"><BookOpen className="h-4 w-4 mr-2" />{lang === "ar" ? "دفتر الأستاذ" : "General Ledger"}</TabsTrigger>
          <TabsTrigger value="trial-balance"><ListChecks className="h-4 w-4 mr-2" />{lang === "ar" ? "ميزان المراجعة" : "Trial Balance"}</TabsTrigger>
          <TabsTrigger value="statements"><BarChart3 className="h-4 w-4 mr-2" />{lang === "ar" ? "القوائم المالية" : "Financial Statements"}</TabsTrigger>
          <TabsTrigger value="accounts"><ListChecks className="h-4 w-4 mr-2" />{lang === "ar" ? "دليل الحسابات" : "Chart of Accounts"}</TabsTrigger>
          <TabsTrigger value="reports">
            <BarChart3 className="h-4 w-4 mr-2" />
            {t.reports}
          </TabsTrigger>
          <TabsTrigger value="purchases">
            <Receipt className="h-4 w-4 mr-2" />
            {t.purchases}
          </TabsTrigger>
          <TabsTrigger value="expenses">
            <ShoppingCart className="h-4 w-4 mr-2" />
            {t.expenses}
          </TabsTrigger>
          <TabsTrigger value="sales">
            <TrendingUp className="h-4 w-4 mr-2" />
            {lang === "ar" ? "المبيعات" : "Sales"}
          </TabsTrigger>
          <TabsTrigger value="assets">
            <Package className="h-4 w-4 mr-2" />
            {lang === "ar" ? "الأصول الثابتة" : "Fixed Assets"}
          </TabsTrigger>
          <TabsTrigger value="taxes">
            <Receipt className="h-4 w-4 mr-2" />
            {lang === "ar" ? "الضرائب" : "Taxes"}
          </TabsTrigger>
          <TabsTrigger value="opening-balances">
            <Scale className="h-4 w-4 mr-2" />
            {lang === "ar" ? "الرصيد الافتتاحي" : "Opening Balances"}
          </TabsTrigger>
          <TabsTrigger value="accounting-controls">
            <ShieldCheck className="h-4 w-4 mr-2" />
            {lang === "ar" ? "الرقابة المحاسبية" : "Accounting Controls"}
          </TabsTrigger>
        </TabsList>

        {/* General Ledger tabs */}
        <TabsContent value="journals" className="space-y-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-3">
              <div>
                <CardTitle>{lang === "ar" ? "القيود اليومية" : "Journal Entries"}</CardTitle>
                <CardDescription>{lang === "ar" ? "كل القيود المرحّلة من المشتريات والمبيعات والمخزون والمصاريف والتسويات والقيود اليدوية." : "All posted entries from purchases, sales, inventory, expenses, settlements, and manual journals."}</CardDescription>
              </div>
              <Button onClick={() => setManualJournalDialog(true)}><Plus className="h-4 w-4 mr-2" />{lang === "ar" ? "قيد يدوي" : "Manual Journal"}</Button>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                <Input type="date" value={accountingStartDate} onChange={(e) => setAccountingStartDate(e.target.value)} />
                <Input type="date" value={accountingEndDate} onChange={(e) => setAccountingEndDate(e.target.value)} />
                <Button variant="outline" onClick={() => void loadAccountingBooks()}>{lang === "ar" ? "تحديث" : "Refresh"}</Button>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead><tr className="border-b"><th className="p-2 text-start">#</th><th className="p-2 text-start">{lang === "ar" ? "التاريخ" : "Date"}</th><th className="p-2 text-start">{lang === "ar" ? "الوصف" : "Description"}</th><th className="p-2 text-start">{lang === "ar" ? "المصدر" : "Source"}</th><th className="p-2 text-end">{lang === "ar" ? "المبلغ" : "Amount"}</th><th className="p-2 text-start">{lang === "ar" ? "الحالة" : "Status"}</th><th className="p-2" /></tr></thead>
                  <tbody>{journalRows.length === 0 ? <tr><td colSpan={7} className="p-8 text-center text-muted-foreground">{lang === "ar" ? "لا توجد قيود ضمن الفترة." : "No journal entries in this period."}</td></tr> : journalRows.map((j) => {
                    const amount = (j.lines || []).reduce((sum: number, l: any) => sum + Number(l.base_debit || 0), 0);
                    return <tr key={j.id} className="border-b hover:bg-muted/30"><td className="p-2 font-mono">{j.journal_number}</td><td className="p-2">{j.entry_date}</td><td className="p-2">{j.description}</td><td className="p-2"><Badge variant="outline">{j.entry_kind}</Badge></td><td className="p-2 text-end font-semibold">{fmt(amount)} SYP</td><td className="p-2"><Badge variant={j.status === 'reversed' ? 'destructive' : 'default'}>{j.status}</Badge></td><td className="p-2 text-end">{j.status === 'posted' && <Button size="sm" variant="outline" onClick={() => void reverseJournal(j.id)}>{lang === "ar" ? "عكس" : "Reverse"}</Button>}</td></tr>;
                  })}</tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="ledger" className="space-y-4">
          <Card>
            <CardHeader><CardTitle className="flex items-center gap-2"><BookOpen className="h-5 w-5" />{lang === "ar" ? "دفتر الأستاذ العام" : "General Ledger"}</CardTitle></CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
                <Select value={selectedLedgerAccount} onValueChange={setSelectedLedgerAccount}><SelectTrigger><SelectValue placeholder={lang === "ar" ? "اختر الحساب" : "Select account"} /></SelectTrigger><SelectContent>{accounts.map((a) => <SelectItem key={a.id} value={String(a.id)}>{a.code} — {lang === 'ar' ? (a.name_ar || a.name) : a.name}</SelectItem>)}</SelectContent></Select>
                <Input type="date" value={accountingStartDate} onChange={(e) => setAccountingStartDate(e.target.value)} />
                <Input type="date" value={accountingEndDate} onChange={(e) => setAccountingEndDate(e.target.value)} />
                <Button variant="outline" onClick={() => void loadLedger()}>{lang === "ar" ? "تحديث الأستاذ" : "Refresh Ledger"}</Button>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4"><Card><CardContent className="pt-4"><div className="text-sm text-muted-foreground">{lang === "ar" ? "الرصيد الافتتاحي للفترة" : "Opening balance"}</div><div className="text-2xl font-bold">{fmt(ledgerOpening)} SYP</div></CardContent></Card><Card><CardContent className="pt-4"><div className="text-sm text-muted-foreground">{lang === "ar" ? "الرصيد الختامي" : "Closing balance"}</div><div className="text-2xl font-bold">{fmt(ledgerClosing)} SYP</div></CardContent></Card></div>
              <div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr className="border-b"><th className="p-2 text-start">{lang === 'ar' ? 'التاريخ' : 'Date'}</th><th className="p-2 text-start">{lang === 'ar' ? 'رقم القيد' : 'Journal'}</th><th className="p-2 text-start">{lang === 'ar' ? 'البيان' : 'Description'}</th><th className="p-2 text-end">{lang === 'ar' ? 'مدين' : 'Debit'}</th><th className="p-2 text-end">{lang === 'ar' ? 'دائن' : 'Credit'}</th><th className="p-2 text-end">{lang === 'ar' ? 'الرصيد' : 'Balance'}</th></tr></thead><tbody>{ledgerRows.length === 0 ? <tr><td colSpan={6} className="p-8 text-center text-muted-foreground">{lang === 'ar' ? 'لا توجد حركة لهذا الحساب ضمن الفترة.' : 'No activity for this account in this period.'}</td></tr> : ledgerRows.map((r, i) => <tr key={`${r.journal_entry_id}-${i}`} className="border-b"><td className="p-2">{r.entry_date}</td><td className="p-2 font-mono">{r.journal_number}</td><td className="p-2">{r.description}</td><td className="p-2 text-end">{fmt(Number(r.debit || 0))}</td><td className="p-2 text-end">{fmt(Number(r.credit || 0))}</td><td className="p-2 text-end font-semibold">{fmt(Number(r.balance || 0))}</td></tr>)}</tbody></table></div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="trial-balance" className="space-y-4">
          <Card><CardHeader className="flex flex-row items-center justify-between"><div><CardTitle>{lang === 'ar' ? 'ميزان المراجعة' : 'Trial Balance'}</CardTitle><CardDescription>{lang === 'ar' ? 'يجب أن يتساوى إجمالي المدين مع إجمالي الدائن.' : 'Total debits must equal total credits.'}</CardDescription></div><Badge variant={Math.abs(Number(trialBalanceData?.difference || 0)) <= 0.005 ? 'default' : 'destructive'}>{lang === 'ar' ? `الفرق: ${fmt(Number(trialBalanceData?.difference || 0))}` : `Difference: ${fmt(Number(trialBalanceData?.difference || 0))}`}</Badge></CardHeader><CardContent><div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr className="border-b"><th className="p-2 text-start">{lang === 'ar' ? 'الحساب' : 'Account'}</th><th className="p-2 text-end">{lang === 'ar' ? 'مدين' : 'Debit'}</th><th className="p-2 text-end">{lang === 'ar' ? 'دائن' : 'Credit'}</th><th className="p-2 text-end">{lang === 'ar' ? 'الرصيد' : 'Balance'}</th></tr></thead><tbody>{(trialBalanceData?.rows || []).map((r:any)=><tr key={r.account_id} className="border-b"><td className="p-2">{r.code} — {lang === 'ar' ? (r.name_ar || r.name) : r.name}</td><td className="p-2 text-end">{fmt(Number(r.debit || 0))}</td><td className="p-2 text-end">{fmt(Number(r.credit || 0))}</td><td className="p-2 text-end font-semibold">{fmt(Number(r.balance || 0))}</td></tr>)}</tbody><tfoot><tr className="font-bold border-t-2"><td className="p-2">{lang === 'ar' ? 'الإجمالي' : 'Total'}</td><td className="p-2 text-end">{fmt(Number(trialBalanceData?.total_debit || 0))}</td><td className="p-2 text-end">{fmt(Number(trialBalanceData?.total_credit || 0))}</td><td /></tr></tfoot></table></div></CardContent></Card>
        </TabsContent>

        <TabsContent value="statements" className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4"><Card><CardHeader><CardTitle>{lang === 'ar' ? 'قائمة الدخل' : 'Profit & Loss'}</CardTitle></CardHeader><CardContent><div className="space-y-3 text-sm"><div className="flex justify-between"><span>{lang === 'ar' ? 'الإيرادات' : 'Revenue'}</span><b>{fmt(Number(statementsData?.profit_and_loss?.revenue || 0))}</b></div><div className="flex justify-between"><span>{lang === 'ar' ? 'تكلفة المبيعات' : 'COGS'}</span><b>{fmt(Number(statementsData?.profit_and_loss?.cogs || 0))}</b></div><div className="flex justify-between"><span>{lang === 'ar' ? 'المصاريف' : 'Expenses'}</span><b>{fmt(Number(statementsData?.profit_and_loss?.expenses || 0))}</b></div><div className="border-t pt-3 flex justify-between text-lg"><span>{lang === 'ar' ? 'صافي الربح' : 'Net Profit'}</span><b>{fmt(Number(statementsData?.profit_and_loss?.net_profit || 0))} SYP</b></div></div></CardContent></Card><Card><CardHeader><CardTitle>{lang === 'ar' ? 'الميزانية العمومية' : 'Balance Sheet'}</CardTitle></CardHeader><CardContent><div className="space-y-3 text-sm"><div className="flex justify-between"><span>{lang === 'ar' ? 'الأصول' : 'Assets'}</span><b>{fmt(Number(statementsData?.balance_sheet?.assets || 0))}</b></div><div className="flex justify-between"><span>{lang === 'ar' ? 'الالتزامات' : 'Liabilities'}</span><b>{fmt(Number(statementsData?.balance_sheet?.liabilities || 0))}</b></div><div className="flex justify-between"><span>{lang === 'ar' ? 'حقوق الملكية' : 'Equity'}</span><b>{fmt(Number(statementsData?.balance_sheet?.equity || 0))}</b></div><div className="border-t pt-3 flex justify-between"><span>{lang === 'ar' ? 'فرق الميزانية' : 'Balance difference'}</span><Badge variant={Math.abs(Number(statementsData?.balance_sheet?.balance_difference || 0)) <= 0.005 ? 'default' : 'destructive'}>{fmt(Number(statementsData?.balance_sheet?.balance_difference || 0))}</Badge></div></div></CardContent></Card></div>
          <Card><CardHeader><CardTitle>{lang === 'ar' ? 'قائمة التدفقات النقدية' : 'Cash Flow Statement'}</CardTitle><CardDescription>{lang === 'ar' ? 'التدفقات مستخرجة من دفتر الأستاذ ومصنفة إلى تشغيلية واستثمارية وتمويلية.' : 'Derived from the general ledger and classified as operating, investing, and financing cash flows.'}</CardDescription></CardHeader><CardContent><div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-sm"><div><div className="text-muted-foreground">{lang === 'ar' ? 'التشغيلية' : 'Operating'}</div><b>{fmt(Number(statementsData?.cash_flow?.operating || 0))}</b></div><div><div className="text-muted-foreground">{lang === 'ar' ? 'الاستثمارية' : 'Investing'}</div><b>{fmt(Number(statementsData?.cash_flow?.investing || 0))}</b></div><div><div className="text-muted-foreground">{lang === 'ar' ? 'التمويلية' : 'Financing'}</div><b>{fmt(Number(statementsData?.cash_flow?.financing || 0))}</b></div><div><div className="text-muted-foreground">{lang === 'ar' ? 'صافي التغير' : 'Net change'}</div><b>{fmt(Number(statementsData?.cash_flow?.net_change || 0))}</b></div><div><div className="text-muted-foreground">{lang === 'ar' ? 'فرق المطابقة' : 'Reconciliation'}</div><Badge variant={Math.abs(Number(statementsData?.cash_flow?.reconciliation_difference || 0)) <= 0.005 ? 'default' : 'destructive'}>{fmt(Number(statementsData?.cash_flow?.reconciliation_difference || 0))}</Badge></div></div></CardContent></Card>
        </TabsContent>

        <TabsContent value="accounts" className="space-y-4">
          <Card><CardHeader><div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"><div><CardTitle>{lang === 'ar' ? 'دليل الحسابات' : 'Chart of Accounts'}</CardTitle><CardDescription>{lang === 'ar' ? 'الحسابات الموجودة مسبقًا تظهر هنا. أنشئ حسابًا جديدًا فقط عند الحاجة، ثم استخدمه في الافتتاحية أو القيود.' : 'Existing ledger accounts are shown here. Create a new account only when needed, then use it in opening balances or journals.'}</CardDescription></div><Button variant="outline" onClick={()=>setAccountDialogOpen(true)}><Plus className="h-4 w-4 me-1" />{lang==='ar'?'إضافة حساب':'Add account'}</Button></div></CardHeader><CardContent><div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr className="border-b"><th className="p-2 text-start">{lang==='ar'?'الكود':'Code'}</th><th className="p-2 text-start">{lang==='ar'?'الحساب':'Account'}</th><th className="p-2 text-start">{lang==='ar'?'المجموعة':'Group'}</th><th className="p-2 text-start">{lang==='ar'?'طبيعة الحساب':'Normal balance'}</th><th className="p-2 text-end">{lang==='ar'?'الرصيد':'Balance'}</th></tr></thead><tbody>{accounts.map((a)=><tr key={a.id} className="border-b"><td className="p-2 font-mono">{a.code}</td><td className="p-2">{lang==='ar'?(a.name_ar||a.name):a.name}</td><td className="p-2">{a.account_group}</td><td className="p-2">{a.normal_balance}</td><td className="p-2 text-end font-semibold">{fmt(Number(a.current_balance||0))} SYP</td></tr>)}</tbody></table></div></CardContent></Card>
        </TabsContent>

        {/* Reports Tab */}
        <TabsContent value="reports" className="space-y-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
              <div>
                <CardTitle className="text-base flex items-center gap-2">
                  <CalendarDays className="h-5 w-5" />
                  {lang === "ar" ? "التقارير المالية حسب الفترة" : "Financial reports by period"}
                </CardTitle>
                <CardDescription>
                  {lang === "ar" ? "اختر الفترة لعرض الإيرادات والمصاريف والمشتريات" : "Select a period to view revenue, expenses, and purchases"}
                </CardDescription>
              </div>
              <div className="flex flex-wrap items-center gap-2">
                <Input type="date" value={accountingStartDate} onChange={(e) => setAccountingStartDate(e.target.value)} className="w-40" />
                <Input type="date" value={accountingEndDate} onChange={(e) => setAccountingEndDate(e.target.value)} className="w-40" />
                <Button variant="outline" onClick={() => void loadReport()}>{lang === "ar" ? "تحديث التقرير" : "Refresh report"}</Button>
                <Select value={reportPeriod} onValueChange={setReportPeriod}>
                <SelectTrigger className="w-40">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="daily">{t.daily}</SelectItem>
                  <SelectItem value="weekly">{t.weekly}</SelectItem>
                  <SelectItem value="monthly">{t.monthly}</SelectItem>
                  <SelectItem value="yearly">{t.yearly}</SelectItem>
                </SelectContent>
                </Select>
              </div>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="grid gap-4 lg:grid-cols-3">
                <Card className="border-green-200">
                  <CardHeader className="pb-2">
                    <CardTitle className="text-sm flex items-center gap-2 text-green-600">
                      <DollarSign className="h-4 w-4" />
                      {t.revenue}
                    </CardTitle>
                  </CardHeader>
                  <CardContent>{renderBarChart(revenueRows, "hsl(142.1 76.2% 36.3%)")}</CardContent>
                </Card>
                <Card className="border-red-200">
                  <CardHeader className="pb-2">
                    <CardTitle className="text-sm flex items-center gap-2 text-red-600">
                      <ShoppingCart className="h-4 w-4" />
                      {t.expensesLabel}
                    </CardTitle>
                  </CardHeader>
                  <CardContent>{renderBarChart(expenseRows, "hsl(0 72.2% 50.6%)")}</CardContent>
                </Card>
                <Card className="border-blue-200">
                  <CardHeader className="pb-2">
                    <CardTitle className="text-sm flex items-center gap-2 text-blue-600">
                      <Receipt className="h-4 w-4" />
                      {t.purchasesLabel}
                    </CardTitle>
                  </CardHeader>
                  <CardContent>{renderBarChart(purchaseRows, "hsl(221.2 83.2% 53.3%)")}</CardContent>
                </Card>
              </div>

              {/* Combined table */}
              <Card>
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm">{lang === "ar" ? "جدول مفصل" : "Detailed table"}</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="border-b">
                          <th className="text-start py-2">{lang === "ar" ? "الفترة" : "Period"}</th>
                          <th className="text-end py-2">{t.revenue}</th>
                          <th className="text-end py-2">{lang === "ar" ? "تكلفة البضاعة" : "COGS"}</th>
                          <th className="text-end py-2">{t.operatingExpenses}</th>
                          <th className="text-end py-2">{lang === "ar" ? "الإهلاك" : "Depreciation"}</th>
                          <th className="text-end py-2">{t.netProfit}</th>
                        </tr>
                      </thead>
                      <tbody>
                        {allPeriodLabels.length === 0 && (
                          <tr>
                            <td colSpan={6} className="text-center py-6 text-muted-foreground">
                              {lang === "ar" ? "لا توجد بيانات" : "No data"}
                            </td>
                          </tr>
                        )}
                        {allPeriodLabels.map((label) => {
                          const rev = revenueRows.find((r) => r.label === label)?.total || 0;
                          const exp = expenseRows.find((r) => r.label === label)?.total || 0;
                          
                          const cogs = Number(reportBreakdown.cogs?.find((r: PeriodRow) => r.label === label)?.total || 0);
                          const operatingExpenses = Number(reportBreakdown.expenses?.find((r: PeriodRow) => r.label === label)?.total || 0);
                          const depreciation = Number(reportBreakdown.depreciation?.find((r: PeriodRow) => r.label === label)?.total || 0);
                          const otherIncome = Number(reportBreakdown.other_income?.find((r: PeriodRow) => r.label === label)?.total || 0);
                          const net = Number(rev) + otherIncome - cogs - operatingExpenses - depreciation;
                          
                          return (
                            <tr key={label} className="border-b">
                              <td className="py-2 font-medium">{label}</td>
                              <td className="text-end">{fmt(Number(rev))}</td>
                              <td className="text-end text-orange-600">{fmt(cogs)}</td>
                              <td className="text-end">{fmt(operatingExpenses)}</td>
                              <td className="text-end text-gray-600">{fmt(depreciation)}</td>
                              <td className={`text-end font-semibold ${net >= 0 ? "text-green-600" : "text-red-600"}`}>
                                {fmt(net)}
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                </CardContent>
              </Card>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Purchases Tab */}
        <TabsContent value="purchases" className="space-y-4">
          <div className="flex justify-between items-center">
            <div>
              <h2 className="text-lg font-semibold">{t.purchases}</h2>
              <p className="text-sm text-muted-foreground">
                {lang === "ar" ? "تسجيل مشتريات المواد الموحدة وتحديث المخزون مع متوسط التكلفة المرجّح" : "Record purchases of unified materials and update inventory with weighted-average costing"}
              </p>
            </div>
            <Button onClick={() => { setEditingPurchase(null); setPurchaseForm({ material_id: "", item_name: "", quantity: 0, cost_per_unit: 0, purchase_date: new Date().toISOString().slice(0, 10), supplier: "", invoice_number: "", notes: "", item_type: "raw_material", payment_method: "cash", paid_amount: 0 }); setPurchaseDialog(true); }}>
              <Plus className="h-4 w-4 mr-2" />
              {t.addPurchase}
            </Button>
          </div>

          <Card className="bg-amber-50 border-amber-200">
            <CardContent className="pt-4">
              <p className="text-sm text-amber-800">
                <strong>{lang === "ar" ? "ملاحظة:" : "Note:"}</strong> {lang === "ar" ? "المشتريات تُسجّل على المواد الموحدة وتحدّث الرصيد والتكلفة المتوسطة المرجّحة مع حركة مخزون مرتبطة. استخدم صفحة الجرد لتسوية الرصيد الفعلي." : "Purchases are recorded against unified materials and update stock using weighted-average costing with a linked inventory movement. Use Stocktaking to reconcile physical quantities."}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="pt-6">
              {purchases.length === 0 ? (
                <div className="text-center text-muted-foreground py-8">
                  <Receipt className="h-12 w-12 mx-auto mb-4 opacity-40" />
                  <p>{t.noPurchases}</p>
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b">
                        <th className="text-start py-2">{lang === "ar" ? "الصنف" : "Item"}</th>
                        <th className="text-end py-2">{t.quantity}</th>
                        <th className="text-end py-2">{t.unit}</th>
                        <th className="text-end py-2">{t.costPerUnit}</th>
                        <th className="text-end py-2">{t.totalCost}</th>
                        <th className="text-end py-2">{t.date}</th>
                        <th className="text-end py-2">{t.supplier}</th>
                        <th className="text-end py-2">{lang === "ar" ? "إجراءات" : "Actions"}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {purchases.map((p) => (
                        <tr key={p.id} className="border-b">
                          <td className="py-2 font-medium">{p.item_name}</td>
                          <td className="text-end">{fmt(Number(p.quantity))}</td>
                          <td className="text-end">{unitLabel(p.unit)}</td>
                          <td className="text-end">{fmt(Number(p.unit_cost))}</td>
                          <td className="text-end font-semibold">{fmt(Number(p.total_cost))} SYP</td>
                          <td className="text-end text-muted-foreground">{p.purchase_date}</td>
                          <td className="text-end">{p.supplier || "-"}</td>
                          <td className="text-end">
                            <div className="flex justify-end gap-1">
                              <Button size="sm" variant="outline" onClick={() => handleEditPurchase(p)}>
                                <Pencil className="h-3 w-3" />
                              </Button>
                              <Button size="sm" variant="destructive" onClick={() => handleDeletePurchase(p.id)}>
                                <Trash2 className="h-3 w-3" />
                              </Button>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Sales Tab */}
        <TabsContent value="sales" className="space-y-4">
          <div className="grid gap-4 md:grid-cols-4">
            {[
              [lang === "ar" ? "إجمالي المبيعات" : "Gross Sales", sales.filter((s) => (s.sale_status || "active") === "active").reduce((a, s) => a + Number(s.total_price_syp || s.total_price || 0), 0)],
              [lang === "ar" ? "المقبوض" : "Collected", sales.filter((s) => (s.sale_status || "active") === "active").reduce((a, s) => a + Number(s.paid_amount || 0), 0)],
              [lang === "ar" ? "المستحق" : "Receivables", sales.filter((s) => (s.sale_status || "active") === "active").reduce((a, s) => a + Number(s.remaining_amount || 0), 0)],
              [lang === "ar" ? "الربح" : "Profit", sales.filter((s) => (s.sale_status || "active") === "active").reduce((a, s) => a + Number(s.profit_syp || s.profit || 0), 0)],
            ].map(([label, value]) => (
              <Card key={String(label)}><CardHeader className="pb-2"><CardTitle className="text-xs text-muted-foreground">{label}</CardTitle></CardHeader><CardContent><div className="text-xl font-bold">{fmt(Number(value))} SYP</div></CardContent></Card>
            ))}
          </div>

          <div className="grid gap-3 md:grid-cols-3">
            <Select value={salesStatusFilter} onValueChange={setSalesStatusFilter}>
              <SelectTrigger><SelectValue placeholder={lang === "ar" ? "حالة الدفع" : "Payment Status"} /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{lang === "ar" ? "كل حالات الدفع" : "All payment statuses"}</SelectItem>
                <SelectItem value="unpaid">{lang === "ar" ? "غير مدفوعة" : "Unpaid"}</SelectItem>
                <SelectItem value="partial">{lang === "ar" ? "مدفوعة جزئياً" : "Partially Paid"}</SelectItem>
                <SelectItem value="paid">{lang === "ar" ? "مدفوعة" : "Paid"}</SelectItem>
              </SelectContent>
            </Select>
            <Select value={salesPaymentFilter} onValueChange={setSalesPaymentFilter}>
              <SelectTrigger><SelectValue placeholder={lang === "ar" ? "طريقة الدفع" : "Payment Method"} /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{lang === "ar" ? "كل طرق الدفع" : "All methods"}</SelectItem>
                <SelectItem value="cash">{lang === "ar" ? "كاش" : "Cash"}</SelectItem>
                <SelectItem value="cash_on_delivery">{lang === "ar" ? "الدفع عند الاستلام" : "Cash on Delivery"}</SelectItem>
                <SelectItem value="sham_cash">{lang === "ar" ? "شام كاش" : "Sham Cash"}</SelectItem>
                <SelectItem value="bank_transfer">{lang === "ar" ? "حوالة بنكية" : "Bank Transfer"}</SelectItem>
              </SelectContent>
            </Select>
            <Select value={salesSourceFilter} onValueChange={setSalesSourceFilter}>
              <SelectTrigger><SelectValue placeholder={lang === "ar" ? "مصدر البيع" : "Sale Source"} /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{lang === "ar" ? "كل المصادر" : "All sources"}</SelectItem>
                <SelectItem value="online">{lang === "ar" ? "الموقع" : "Website"}</SelectItem>
                <SelectItem value="offline">{lang === "ar" ? "المتجر" : "Physical Store"}</SelectItem>
                <SelectItem value="pos">POS</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <Card>
            <CardContent className="pt-6">
              {(() => {
                const filtered = sales.filter((s) =>
                  (salesStatusFilter === "all" || String(s.payment_status || "unpaid") === salesStatusFilter) &&
                  (salesPaymentFilter === "all" || String(s.payment_method || "") === salesPaymentFilter) &&
                  (salesSourceFilter === "all" || String(s.sale_source || "") === salesSourceFilter)
                );
                return filtered.length === 0 ? (
                  <div className="text-center text-muted-foreground py-8"><TrendingUp className="h-12 w-12 mx-auto mb-4 opacity-40" /><p>{lang === "ar" ? "لا توجد مبيعات مطابقة" : "No matching sales"}</p></div>
                ) : (
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                      <thead><tr className="border-b">
                        <th className="text-start py-2">{lang === "ar" ? "الفاتورة" : "Invoice"}</th>
                        <th className="text-start py-2">{lang === "ar" ? "العميل" : "Customer"}</th>
                        <th className="text-end py-2">{lang === "ar" ? "الإجمالي" : "Total"}</th>
                        <th className="text-end py-2">{lang === "ar" ? "المقبوض" : "Paid"}</th>
                        <th className="text-end py-2">{lang === "ar" ? "المستحق" : "Due"}</th>
                        <th className="text-start py-2">{lang === "ar" ? "الدفع" : "Payment"}</th>
                        <th className="text-start py-2">{lang === "ar" ? "الحالة" : "Status"}</th>
                        <th className="text-start py-2">{lang === "ar" ? "التاريخ" : "Date"}</th>
                        <th className="text-start py-2"></th>
                      </tr></thead>
                      <tbody>{filtered.map((s) => (
                        <tr key={s.id} className="border-b hover:bg-muted/30">
                          <td className="py-2 font-mono text-xs">{s.invoice_number || `#${s.id}`}</td>
                          <td className="py-2">{s.customer_name || s.customer?.name || "-"}</td>
                          <td className="text-end font-semibold">{fmt(Number(s.total_price_syp || s.total_price || 0))}</td>
                          <td className="text-end">{fmt(Number(s.paid_amount || 0))}</td>
                          <td className="text-end">{fmt(Number(s.remaining_amount || 0))}</td>
                          <td>{s.payment_method || "-"}</td>
                          <td className="space-x-1"><Badge variant={s.payment_status === "paid" ? "default" : "outline"}>{s.payment_status || "unpaid"}</Badge>{s.sale_status === "voided" && <Badge variant="destructive">{lang === "ar" ? "ملغاة" : "Voided"}</Badge>}</td>
                          <td className="text-muted-foreground">{s.sale_date || "-"}</td>
                          <td><Button size="sm" variant="outline" onClick={() => setSelectedSale(s)}>{lang === "ar" ? "تفاصيل" : "Details"}</Button></td>
                        </tr>
                      ))}</tbody>
                    </table>
                  </div>
                );
              })()}
            </CardContent>
          </Card>

          <Dialog open={!!selectedSale} onOpenChange={(open) => !open && setSelectedSale(null)}>
            <DialogContent className="max-w-2xl">
              <DialogHeader><DialogTitle>{lang === "ar" ? "تفاصيل الفاتورة" : "Invoice Details"}</DialogTitle><DialogDescription>{selectedSale?.invoice_number || `#${selectedSale?.id}`}</DialogDescription></DialogHeader>
              {selectedSale && <div className="grid gap-4 md:grid-cols-2 text-sm">
                <div><span className="text-muted-foreground">{lang === "ar" ? "العميل" : "Customer"}</span><div className="font-medium">{selectedSale.customer_name || selectedSale.customer?.name || "-"}</div></div>
                <div><span className="text-muted-foreground">{lang === "ar" ? "الهاتف" : "Phone"}</span><div>{selectedSale.customer_phone || selectedSale.customer?.phone || "-"}</div></div>
                <div><span className="text-muted-foreground">{lang === "ar" ? "المنتج" : "Product"}</span><div>{selectedSale.product_name}</div></div>
                <div><span className="text-muted-foreground">{lang === "ar" ? "المصدر" : "Source"}</span><div>{selectedSale.sale_source}</div></div>
                <div><span className="text-muted-foreground">{lang === "ar" ? "الإجمالي" : "Total"}</span><div className="font-bold">{fmt(Number(selectedSale.total_price_syp || selectedSale.total_price || 0))} SYP</div></div>
                <div><span className="text-muted-foreground">{lang === "ar" ? "التكلفة" : "Cost"}</span><div>{fmt(Number(selectedSale.cost_syp || selectedSale.cost || 0))} SYP</div></div>
                <div><span className="text-muted-foreground">{lang === "ar" ? "المقبوض" : "Paid"}</span><div>{fmt(Number(selectedSale.paid_amount || 0))} SYP</div></div>
                <div><span className="text-muted-foreground">{lang === "ar" ? "المستحق" : "Due"}</span><div>{fmt(Number(selectedSale.remaining_amount || 0))} SYP</div></div>
                <div><span className="text-muted-foreground">{lang === "ar" ? "طريقة الدفع" : "Payment method"}</span><div>{selectedSale.payment_method || "-"}</div></div>
                <div><span className="text-muted-foreground">{lang === "ar" ? "حالة الدفع" : "Payment status"}</span><div>{selectedSale.payment_status || "unpaid"}</div></div>
              </div>}
              <DialogFooter><Button onClick={() => setSelectedSale(null)}>{lang === "ar" ? "إغلاق" : "Close"}</Button></DialogFooter>
            </DialogContent>
          </Dialog>
        </TabsContent>

        {/* Expenses Tab */}
        <TabsContent value="expenses" className="space-y-4">
          <div className="flex justify-between items-center">
            <div>
              <h2 className="text-lg font-semibold">{t.expenses}</h2>
              <p className="text-sm text-muted-foreground">
                {lang === "ar" ? "تسجيل المصاريف التشغيلية" : "Record operational expenses"}
              </p>
            </div>
            <Button onClick={() => { setEditingExpense(null); setExpenseForm({ category: "rent", description: "", amount: 0, expense_date: new Date().toISOString().slice(0, 10), payment_method: "cash", rent_duration_days: 1, vendor: "", notes: "" }); setExpenseDialog(true); }}>
              <Plus className="h-4 w-4 mr-2" />
              {t.addExpense}
            </Button>
          </div>

          <Card>
            <CardContent className="pt-6">
              {expenses.length === 0 ? (
                <div className="text-center text-muted-foreground py-8">
                  <ShoppingCart className="h-12 w-12 mx-auto mb-4 opacity-40" />
                  <p>{t.noExpenses}</p>
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b">
                        <th className="text-start py-2">{t.category}</th>
                        <th className="text-start py-2">{t.description}</th>
                        <th className="text-end py-2">{t.amount}</th>
                        <th className="text-end py-2">{t.paymentMethod}</th>
                        <th className="text-end py-2">{t.date}</th>
                        <th className="text-end py-2">{lang === "ar" ? "إجراءات" : "Actions"}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {expenses.map((e) => (
                        <tr key={e.id} className="border-b">
                          <td className="py-2">
                            <Badge variant="outline">{expenseCategoryLabel(e.category)}</Badge>
                            {e.category === "rent" && e.rent_duration_days != null && (
                              <span className="ml-2 text-xs text-muted-foreground">
                                {e.rent_duration_days} {lang === "ar" ? "يوم" : "days"}
                              </span>
                            )}
                          </td>
                          <td className="py-2">{e.description}</td>
                          <td className="text-end font-semibold">{fmt(Number(e.amount))} SYP</td>
                          <td className="text-end">{e.payment_method === "credit" ? t.credit : t.cash}</td>
                          <td className="text-end text-muted-foreground">{e.expense_date}</td>
                          <td className="text-end">
                            <div className="flex justify-end gap-1">
                              <Button size="sm" variant="outline" onClick={() => handleEditExpense(e)}>
                                <Pencil className="h-3 w-3" />
                              </Button>
                              <Button size="sm" variant="destructive" onClick={() => handleDeleteExpense(e.id)}>
                                <Trash2 className="h-3 w-3" />
                              </Button>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Fixed Assets Tab */}
        <TabsContent value="assets" className="space-y-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
              <div>
                <CardTitle className="text-base flex items-center gap-2">
                  <Package className="h-5 w-5" />
                  {lang === "ar" ? "إدارة الأصول الثابتة" : "Fixed Assets Management"}
                </CardTitle>
                <CardDescription>
                  {lang === "ar" ? "تتبع الأصول الثابتة وحساب الإهلاك" : "Track fixed assets and calculate depreciation"}
                </CardDescription>
              </div>
              <Button onClick={() => setAssetDialog(true)}>
                <Plus className="h-4 w-4 mr-2" />
                {lang === "ar" ? "إضافة أصل" : "Add Asset"}
              </Button>
            </CardHeader>
            <CardContent>
              <div className="mb-5 rounded-lg border bg-muted/20 p-4 space-y-3">
                <div>
                  <div className="font-semibold">{lang === "ar" ? "الإهلاك الدوري" : "Periodic Depreciation"}</div>
                  <p className="text-xs text-muted-foreground mt-1">{lang === "ar" ? "يسجل انخفاض قيمة الأصل كمصروف غير نقدي. القسط الثابت = (تكلفة الأصل − القيمة التخريدية) ÷ العمر الإنتاجي." : "Depreciation records the non-cash expense of using a fixed asset. Straight-line = (cost − salvage value) ÷ useful life."}</p>
                </div>
                <div className="flex flex-wrap gap-2 items-end">
                  <div className="space-y-1"><Label>{lang === "ar" ? "حتى تاريخ" : "Through date"}</Label><Input type="date" value={depreciationDate} onChange={e => setDepreciationDate(e.target.value)} /></div>
                  <Button variant="outline" onClick={() => void previewDepreciation()}>{lang === "ar" ? "احسب للشهر" : "Calculate month"}</Button>
                  {depreciationPreview && <Button onClick={() => void postDepreciation()}>{lang === "ar" ? "ترحيل الإهلاك" : "Post depreciation"}</Button>}
                </div>
                {depreciationPreview && <div className="text-sm font-medium">{lang === "ar" ? "إجمالي الإهلاك للفترة:" : "Period depreciation:"} {fmt(Number(depreciationPreview.total || 0))} SYP — {depreciationPreview.entries?.length || 0} {lang === "ar" ? "أصل" : "assets"}</div>}
                {depreciationPreview?.entries?.some((e: any) => e.requires_production_units) && (
                  <div className="mt-3 space-y-2">
                    {depreciationPreview.entries.filter((e: any) => e.requires_production_units).map((e: any) => (
                      <div key={e.asset_id} className="flex flex-col sm:flex-row sm:items-center gap-2 text-sm">
                        <span className="sm:flex-1">{e.asset_number} — {e.name}</span>
                        <Input className="sm:w-56" type="text" inputMode="decimal" placeholder={lang === "ar" ? "وحدات الإنتاج للفترة" : "Units produced in period"} value={productionUnits[String(e.asset_id)] || ""} onChange={(ev) => setProductionUnits((prev) => ({ ...prev, [String(e.asset_id)]: ev.target.value }))} />
                      </div>
                    ))}
                  </div>
                )}
              </div>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b">
                      <th className="text-start py-2">{lang === "ar" ? "رقم الأصل" : "Asset #"}</th>
                      <th className="text-start py-2">{lang === "ar" ? "الاسم" : "Name"}</th>
                      <th className="text-start py-2">{lang === "ar" ? "الفئة" : "Category"}</th>
                      <th className="text-end py-2">{lang === "ar" ? "الكمية" : "Qty"}</th>
                      <th className="text-end py-2">{lang === "ar" ? "التكلفة" : "Cost"}</th>
                      <th className="text-end py-2">{lang === "ar" ? "الإهلاك السنوي" : "Annual Dep."}</th>
                      <th className="text-end py-2">{lang === "ar" ? "القيمة الدفترية" : "Book Value"}</th>
                      <th className="text-end py-2">{lang === "ar" ? "الحالة" : "Status"}</th>
                      <th className="text-end py-2">{lang === "ar" ? "إجراءات" : "Actions"}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {assets.length === 0 ? (
                      <tr>
                        <td colSpan={8} className="text-center py-6 text-muted-foreground">
                          {lang === "ar" ? "لا توجد أصول ثابتة" : "No fixed assets"}
                        </td>
                      </tr>
                    ) : (
                      assets.map((asset) => (
                        <tr key={asset.id} className="border-b">
                          <td className="py-2 font-mono text-xs">{asset.asset_number}</td>
                          <td className="py-2">{asset.name}</td>
                          <td className="py-2 capitalize">{asset.category}</td>
                          <td className="text-end">{asset.quantity || 1}</td>
                          <td className="text-end">{fmt(Number(asset.purchase_cost))} SYP</td>
                          <td className="text-end text-orange-600">{fmt((Number(asset.purchase_cost) - Number(asset.salvage_value)) / Number(asset.useful_life_years))} SYP</td>
                          <td className="text-end font-semibold">{fmt(asset.book_value || Number(asset.purchase_cost))} SYP</td>
                          <td className="py-2">
                            <Badge variant={asset.status === 'active' ? 'default' : 'secondary'}>
                              {asset.status}
                            </Badge>
                          </td>
                          <td className="text-end">
                            <div className="flex justify-end gap-1">
                              <Button size="sm" variant="outline" onClick={() => { setEditingAsset(asset); setAssetForm({ ...assetForm, ...asset }); setAssetDialog(true); }}>
                                <Pencil className="h-3 w-3" />
                              </Button>
                              <Button size="sm" variant="destructive" onClick={() => void handleDeleteAsset(asset.id)}>
                                <Trash2 className="h-3 w-3" />
                              </Button>
                            </div>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Taxes Tab */}
        <TabsContent value="taxes" className="space-y-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
              <div>
                <CardTitle className="text-base flex items-center gap-2">
                  <Receipt className="h-5 w-5" />
                  {lang === "ar" ? "إدارة الضرائب" : "Tax Configuration"}
                </CardTitle>
                <CardDescription>
                  {lang === "ar" ? "تكوين معدلات الضرائب وحسابها" : "Configure tax rates and calculations"}
                </CardDescription>
              </div>
              <Button onClick={() => setTaxDialog(true)}>
                <Plus className="h-4 w-4 mr-2" />
                {lang === "ar" ? "إضافة ضريبة" : "Add Tax"}
              </Button>
            </CardHeader>
            <CardContent>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b">
                      <th className="text-start py-2">{lang === "ar" ? "الاسم" : "Name"}</th>
                      <th className="text-start py-2">{lang === "ar" ? "النوع" : "Type"}</th>
                      <th className="text-end py-2">{lang === "ar" ? "النسبة" : "Rate"}</th>
                      <th className="text-start py-2">{lang === "ar" ? "يُطبق على" : "Applied To"}</th>
                      <th className="text-start py-2">{lang === "ar" ? "الحالة" : "Status"}</th>
                      <th className="text-end py-2">{lang === "ar" ? "إجراءات" : "Actions"}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {taxes.length === 0 ? (
                      <tr>
                        <td colSpan={6} className="text-center py-6 text-muted-foreground">
                          {lang === "ar" ? "لا توجد تكوينات ضريبية" : "No tax configurations"}
                        </td>
                      </tr>
                    ) : (
                      taxes.map((tax) => (
                        <tr key={tax.id} className="border-b">
                          <td className="py-2">{tax.name}</td>
                          <td className="py-2 capitalize">{tax.tax_type}</td>
                          <td className="text-end">{tax.calculation_method === 'percentage' ? `${tax.rate}%` : fmt(tax.fixed_amount)}</td>
                          <td className="py-2 capitalize">{tax.applicable_to.replace('_', ' ')}</td>
                          <td className="py-2">
                            <Badge variant={tax.is_active ? 'default' : 'secondary'}>
                              {tax.is_active ? (lang === "ar" ? "نشط" : "Active") : (lang === "ar" ? "غير نشط" : "Inactive")}
                            </Badge>
                          </td>
                          <td className="text-end">
                            <div className="flex justify-end gap-1">
                              <Button size="sm" variant="outline" onClick={() => { setEditingTax(tax); setTaxForm({ ...taxForm, ...tax }); setTaxDialog(true); }}>
                                <Pencil className="h-3 w-3" />
                              </Button>
                              <Button size="sm" variant="destructive" onClick={() => void handleDeleteTax(tax.id)}>
                                <Trash2 className="h-3 w-3" />
                              </Button>
                            </div>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Opening Balances Tab */}
        <TabsContent value="opening-balances" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2"><Scale className="h-5 w-5" />{lang === "ar" ? "الرصيد الافتتاحي" : "Opening Balance"}</CardTitle>
              <CardDescription>{lang === "ar" ? "هذا هو المكان الوحيد لتحديد تاريخ بداية النظام المحاسبي وإدخال أصول البداية الفعلية. لا يوجد تاريخ محاسبي في إعدادات المتجر." : "This is the only place where the accounting start date and real opening assets are defined."}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
              <div className="rounded-xl border bg-muted/20 p-4 space-y-2">
                <div className="font-semibold">{lang === 'ar' ? 'بداية رأس المال' : 'Opening capital'}</div>
                <div className="text-sm text-muted-foreground">{lang === 'ar' ? 'بما أن بداية المشروع هي المخزون والأدوات المملوكة للمالك ولا يوجد كاش، سيصبح القيد عند الترحيل: مدين مخزون + مدين أصول ثابتة، ودائن رأس مال المالك.' : 'Because the business starts with owner-owned inventory and tools and no cash, posting debits inventory and fixed assets and credits owner capital.'}</div>
              </div>
              <div className="grid gap-3 md:grid-cols-4 items-end">
                <div><Label>{lang==='ar'?'تاريخ بداية النظام المحاسبي':'Accounting system start date'}</Label><Input type="date" value={openingDate} onChange={e=>setOpeningDate(e.target.value)} /></div>
                <div className="md:col-span-2"><Label>{lang==='ar'?'ملاحظة':'Note'}</Label><Input value={openingNote} onChange={e=>setOpeningNote(e.target.value)} placeholder={lang==='ar'?'جرد بداية التشغيل الفعلي':'Physical go-live count'} /></div>
                <Button onClick={()=>void createOpeningBalance()} disabled={!openingDate}>{lang==='ar'?'إنشاء رصيد افتتاحي':'Create opening balance'}</Button>
              </div>
              {activeOpeningId && <Card><CardHeader><CardTitle className="text-base">{lang==='ar'?'استيراد الجرد الكامل تلقائيًا':'Import full physical count automatically'}</CardTitle><CardDescription>{lang==='ar'?'يستورد المواد والأدوات كمسودة فقط، ثم يظهر لك كل سطر وإجمالي القيمة قبل الترحيل.':'Imports materials and operational tools as a draft; every row and total value are shown before posting.'}</CardDescription></CardHeader><CardContent><Button onClick={()=>void importGoLiveOpeningData(activeOpeningId)} disabled={openingImporting}>{openingImporting?(lang==='ar'?'جارٍ الاستيراد...':'Importing...'):(lang==='ar'?'استيراد كامل الجرد + الأدوات':'Import full inventory + tools')}</Button></CardContent></Card>}
              {activeOpeningId && <div className="grid gap-3 md:grid-cols-5">
                <Card><CardContent className="pt-4"><div className="text-xs text-muted-foreground">{lang==='ar'?'قيمة المخزون':'Inventory value'}</div><div className="text-xl font-bold">{fmt(openingInventoryRows.reduce((sum,r)=>sum+Number(r.total_value||0),0))} SYP</div></CardContent></Card>
                <Card><CardContent className="pt-4"><div className="text-xs text-muted-foreground">{lang==='ar'?'قيمة الأدوات':'Tools value'}</div><div className="text-xl font-bold">{fmt(openingFixedAssetRows.reduce((sum,r)=>sum+Number(r.total_value||0),0))} SYP</div></CardContent></Card>
                <Card><CardContent className="pt-4"><div className="text-xs text-muted-foreground">{lang==='ar'?'النقد والحسابات المالية':'Cash & financial accounts'}</div><div className="text-xl font-bold">{fmt(Number(openingBalances.find((o:any)=>Number(o.id)===Number(activeOpeningId))?.financial_accounts?.reduce((sum:number,r:any)=>sum+Number(r.balance_syp||r.balance||0),0)||0))} SYP</div></CardContent></Card>
                <Card><CardContent className="pt-4"><div className="text-xs text-muted-foreground">{lang==='ar'?'إجمالي الأصول':'Total assets'}</div><div className="text-xl font-bold">{fmt(Number(openingBalances.find((o:any)=>Number(o.id)===Number(activeOpeningId))?.draft_total_assets||0))} SYP</div></CardContent></Card>
                <Card><CardContent className="pt-4"><div className="text-xs text-muted-foreground">{lang==='ar'?'رأس مال المالك':'Owner capital'}</div><div className="text-xl font-bold">{fmt(Number(openingBalances.find((o:any)=>Number(o.id)===Number(activeOpeningId))?.draft_owner_capital||0))} SYP</div></CardContent></Card>
              </div>}
              {activeOpeningId && <Card><CardHeader><CardTitle className="text-base">{lang==='ar'?`المواد (${openingInventoryRows.length})`:`Materials (${openingInventoryRows.length})`}</CardTitle></CardHeader><CardContent><div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr className="border-b"><th className="p-2 text-start">{lang==='ar'?'المادة':'Material'}</th><th className="p-2 text-end">{lang==='ar'?'الكمية':'Qty'}</th><th className="p-2 text-end">{lang==='ar'?'تكلفة الوحدة':'Unit cost'}</th><th className="p-2 text-end">{lang==='ar'?'القيمة':'Value'}</th></tr></thead><tbody>{openingInventoryRows.length===0?<tr><td colSpan={4} className="p-6 text-center text-muted-foreground">{lang==='ar'?'لم يتم الاستيراد بعد.':'Nothing imported yet.'}</td></tr>:openingInventoryRows.map((r:any)=><tr key={r.id} className="border-b"><td className="p-2">{r.material_name_ar||r.material_name}</td><td className="p-2 text-end">{fmt(Number(r.quantity||0))} {r.unit}</td><td className="p-2 text-end">{fmt(Number(r.unit_cost||0))}</td><td className="p-2 text-end font-medium">{fmt(Number(r.total_value||0))}</td></tr>)}</tbody></table></div></CardContent></Card>}
              {activeOpeningId && <Card><CardHeader><CardTitle className="text-base">{lang==='ar'?`الأدوات والأصول (${openingFixedAssetRows.length})`:`Tools & fixed assets (${openingFixedAssetRows.length})`}</CardTitle></CardHeader><CardContent><div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr className="border-b"><th className="p-2 text-start">{lang==='ar'?'الأداة':'Tool'}</th><th className="p-2 text-end">{lang==='ar'?'العدد':'Qty'}</th><th className="p-2 text-end">{lang==='ar'?'قيمة الوحدة':'Unit value'}</th><th className="p-2 text-end">{lang==='ar'?'القيمة':'Value'}</th></tr></thead><tbody>{openingFixedAssetRows.length===0?<tr><td colSpan={4} className="p-6 text-center text-muted-foreground">{lang==='ar'?'لا توجد أدوات بعد.':'No tools yet.'}</td></tr>:openingFixedAssetRows.map((r:any)=><tr key={r.id} className="border-b"><td className="p-2">{r.name_ar||r.name}</td><td className="p-2 text-end">{fmt(Number(r.quantity||0))}</td><td className="p-2 text-end">{fmt(Number(r.unit_cost||0))}</td><td className="p-2 text-end font-medium">{fmt(Number(r.total_value||0))}</td></tr>)}</tbody></table></div></CardContent></Card>}
              {activeOpeningId && <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                  <CardHeader>
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                      <div>
                        <CardTitle className="text-base">{lang==='ar'?'الحسابات المالية الافتتاحية':'Opening financial balances'}</CardTitle>
                        <CardDescription>{lang==='ar'?'الحسابات الموجودة مسبقًا في دليل الحسابات تظهر هنا. اختر الحساب ثم أدخل رصيده الافتتاحي.':'Existing ledger accounts are listed here. Choose the account, then enter its opening balance.'}</CardDescription>
                      </div>
                      <Button variant="outline" size="sm" onClick={()=>setAccountDialogOpen(true)}><Plus className="h-4 w-4 me-1" />{lang==='ar'?'حساب جديد':'New account'}</Button>
                    </div>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="grid gap-3 md:grid-cols-2">
                      <div className="md:col-span-2">
                        <Label>{lang==='ar'?'الحساب الموجود':'Existing account'}</Label>
                        <Select value={openingFinancialForm.financial_account_id} onValueChange={v=>setOpeningFinancialForm({...openingFinancialForm, financial_account_id:v})}>
                          <SelectTrigger><SelectValue placeholder={lang==='ar'?'اختر من دليل الحسابات':'Choose from chart of accounts'} /></SelectTrigger>
                          <SelectContent>
                            {accounts.filter((a:any)=>a.account_group==='asset' && a.is_active!==false && ['cash','bank','payment_gateway','other'].includes(a.account_type)).map((a:any)=><SelectItem key={a.id} value={String(a.id)}>{a.code} · {lang==='ar'?(a.name_ar||a.name):a.name}</SelectItem>)}
                          </SelectContent>
                        </Select>
                      </div>
                      <div><Label>{lang==='ar'?'الرصيد':'Balance'}</Label><Input inputMode="decimal" value={openingFinancialForm.balance} onChange={e=>setOpeningFinancialForm({...openingFinancialForm,balance:e.target.value})} placeholder="0" /></div>
                      <div><Label>{lang==='ar'?'سعر الصرف':'Exchange rate'}</Label><Input inputMode="decimal" value={openingFinancialForm.exchange_rate} onChange={e=>setOpeningFinancialForm({...openingFinancialForm,exchange_rate:e.target.value})} /></div>
                    </div>
                    <Input placeholder={lang==='ar'?'ملاحظات (اختياري)':'Notes (optional)'} value={openingFinancialForm.notes} onChange={e=>setOpeningFinancialForm({...openingFinancialForm,notes:e.target.value})}/>
                    <Button onClick={()=>void addOpeningFinancialAccount()} disabled={!openingFinancialForm.financial_account_id}>{lang==='ar'?'إضافة الرصيد للافتتاحية':'Add opening balance'}</Button>
                    <div className="rounded-lg border divide-y">
                      {(() => {
                        const active = openingBalances.find((o:any)=>Number(o.id)===Number(activeOpeningId));
                        const rows = Array.isArray(active?.financial_accounts) ? active.financial_accounts : [];
                        return rows.length ? rows.map((r:any)=><div key={r.id} className="p-3 flex items-center justify-between gap-3"><div><div className="font-medium">{r.financial_account?.name_ar || r.account_name_ar || r.financial_account?.name || r.account_name}</div><div className="text-xs text-muted-foreground">{r.financial_account?.code || '—'} · {r.account_type} · {r.currency}</div></div><div className="font-semibold">{fmt(Number(r.balance_syp||r.balance||0))} SYP</div></div>) : <div className="p-5 text-center text-sm text-muted-foreground">{lang==='ar'?'لم تتم إضافة أرصدة مالية افتتاحية.':'No financial opening balances added yet.'}</div>;
                      })()}
                    </div>
                  </CardContent>
                </Card>
                <Card><CardHeader><CardTitle className="text-base">{lang==='ar'?'الذمم المدينة والدائنة':'Receivables & payables'}</CardTitle></CardHeader><CardContent className="grid gap-3 md:grid-cols-2"><Input placeholder={lang==='ar'?'اسم الجهة':'Party name'} value={openingPartyForm.party_name} onChange={e=>setOpeningPartyForm({...openingPartyForm,party_name:e.target.value})}/><Select value={openingPartyForm.type} onValueChange={v=>setOpeningPartyForm({...openingPartyForm,type:v})}><SelectTrigger><SelectValue/></SelectTrigger><SelectContent><SelectItem value="payable">{lang==='ar'?'ذمم دائنة علينا':'Payable'}</SelectItem><SelectItem value="receivable">{lang==='ar'?'ذمم مدينة لنا':'Receivable'}</SelectItem></SelectContent></Select><Select value={openingPartyForm.currency} onValueChange={v=>setOpeningPartyForm({...openingPartyForm,currency:v})}><SelectTrigger><SelectValue/></SelectTrigger><SelectContent><SelectItem value="SYP">SYP</SelectItem><SelectItem value="USD">USD</SelectItem></SelectContent></Select><Input inputMode="decimal" placeholder={lang==='ar'?'المبلغ':'Amount'} value={openingPartyForm.amount} onChange={e=>setOpeningPartyForm({...openingPartyForm,amount:e.target.value})}/><Input inputMode="decimal" placeholder={lang==='ar'?'سعر الصرف':'Exchange rate'} value={openingPartyForm.exchange_rate} onChange={e=>setOpeningPartyForm({...openingPartyForm,exchange_rate:e.target.value})}/><Input type="date" value={openingPartyForm.due_date} onChange={e=>setOpeningPartyForm({...openingPartyForm,due_date:e.target.value})}/><Button onClick={()=>void addOpeningPartyBalance()}>{lang==='ar'?'إضافة الذمة':'Add balance'}</Button></CardContent></Card>
              </div>}
              <div className="space-y-2">{openingBalances.length===0?<div className="rounded-lg border p-5 text-center text-muted-foreground">{lang==='ar'?'لا توجد أرصدة افتتاحية بعد.':'No opening balances yet.'}</div>:openingBalances.map((o:any)=><div key={o.id} onClick={()=>{setActiveOpeningId(Number(o.id));setOpeningDate(toDateInputValue(o.opening_balance_date));setOpeningInventoryRows(Array.isArray(o.inventory)?o.inventory:[]);setOpeningFixedAssetRows(Array.isArray(o.fixed_assets)?o.fixed_assets:[]);}} className={`rounded-xl border p-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 cursor-pointer ${activeOpeningId===Number(o.id)?'ring-2 ring-primary':''}`}><div><div className="font-medium">#{o.id} · {o.opening_balance_date}</div><div className="text-xs text-muted-foreground">{lang==='ar'?'مخزون:':'Inventory:'} {fmt(Number(o.draft_inventory_value ?? o.total_inventory_value ?? 0))} SYP · {lang==='ar'?'أدوات:':'Tools:'} {fmt(Number(o.draft_fixed_assets_value ?? o.total_fixed_assets_value ?? 0))} · {lang==='ar'?'رأس المال:':'Owner capital:'} {fmt(Number(o.draft_owner_capital ?? 0))} · {lang==='ar'?'الحالة:':'Status:'} {o.status}</div></div><div className="flex gap-2"><Badge variant={o.status==='locked'?'secondary':o.status==='confirmed'?'default':'outline'}>{o.status}</Badge>{o.status==='draft' && <Button size="sm" onClick={(e)=>{e.stopPropagation();void confirmOpeningBalance(o.id)}}>{lang==='ar'?'تأكيد وترحيل':'Confirm & Post'}</Button>}{o.status==='confirmed' && <Button size="sm" variant="outline" onClick={(e)=>{e.stopPropagation();void lockOpeningBalance(o.id)}}>{lang==='ar'?'قفل':'Lock'}</Button>}</div></div>)}</div>
            </CardContent>
          </Card>
        </TabsContent>
        <TabsContent value="accounting-controls" className="space-y-4">
          <Card>
            <CardHeader><CardTitle className="flex items-center gap-2"><LockKeyhole className="h-5 w-5" />{lang === "ar" ? "الفترات المحاسبية" : "Accounting periods"}</CardTitle><CardDescription>{lang === "ar" ? "حدد فترات العمل، ثم أقفلها بعد المراجعة. الإقفال لا يحذف البيانات ويمنع التعديلات المالية داخل الفترة." : "Define work periods and close them after review. Closing preserves data and blocks financial changes inside the period."}</CardDescription></CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-3 md:grid-cols-4"><Input type="date" value={periodStart} min={accountingStartDefault || undefined} onChange={e=>setPeriodStart(e.target.value)}/><Input type="date" value={periodEnd} onChange={e=>setPeriodEnd(e.target.value)}/><Input value={periodName} onChange={e=>setPeriodName(e.target.value)} placeholder={lang==='ar'?'اسم الفترة (اختياري)':'Period name (optional)'}/><Button onClick={()=>void createAccountingPeriod()}>{lang==='ar'?'إضافة فترة':'Create period'}</Button></div>
              <div className="space-y-2">{accountingPeriods.length===0?<div className="rounded-lg border p-5 text-center text-muted-foreground">{lang==='ar'?'لا توجد فترات محاسبية بعد.':'No accounting periods yet.'}</div>:accountingPeriods.map(p=><div key={p.id} className="rounded-xl border p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3"><div><div className="font-medium">{p.name}</div><div className="text-xs text-muted-foreground">{p.period_start} → {p.period_end}</div></div><div className="flex items-center gap-2"><Badge variant={p.status==='closed'?'secondary':p.status==='reopened'?'outline':'default'}>{p.status}</Badge>{p.status==='closed'?<Button size="sm" variant="outline" onClick={()=>void reopenAccountingPeriod(p.id)}><UnlockKeyhole className="h-4 w-4 me-1"/>{lang==='ar'?'إعادة فتح':'Reopen'}</Button>:<Button size="sm" onClick={()=>void closeAccountingPeriod(p.id)}><LockKeyhole className="h-4 w-4 me-1"/>{lang==='ar'?'إقفال':'Close'}</Button>}</div></div>)}</div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle className="flex items-center gap-2"><Landmark className="h-5 w-5" />{lang==='ar'?'مطابقة الحسابات':'Account reconciliation'}</CardTitle><CardDescription>{lang==='ar'?'قارن رصيد الدفاتر برصيد كشف الحساب وسجل الفرق بوضوح.':'Compare book balance with the external statement balance and record any difference.'}</CardDescription></CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-3 md:grid-cols-4"><Select value={reconciliationAccountId} onValueChange={setReconciliationAccountId}><SelectTrigger><SelectValue placeholder={lang==='ar'?'الحساب':'Account'}/></SelectTrigger><SelectContent>{reconciliationOptions.map((a:any)=><SelectItem key={a.id} value={String(a.id)}>{lang==='ar'?(a.name_ar||a.name):a.name} · {Number(a.current_balance||0).toLocaleString()} SYP</SelectItem>)}</SelectContent></Select><Input type="date" value={reconciliationDate} onChange={e=>setReconciliationDate(e.target.value)}/><Input type="text" inputMode="decimal" value={statementBalance} onChange={e=>setStatementBalance(e.target.value)} placeholder={lang==='ar'?'رصيد كشف الحساب':'Statement balance'}/><Button onClick={()=>void saveReconciliation()}>{lang==='ar'?'تسجيل المطابقة':'Record reconciliation'}</Button></div>
              <Textarea value={reconciliationNotes} onChange={e=>setReconciliationNotes(e.target.value)} placeholder={lang==='ar'?'ملاحظات المطابقة (اختياري)':'Reconciliation notes (optional)'}/>
              <div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr className="border-b"><th className="p-2 text-start">{lang==='ar'?'التاريخ':'Date'}</th><th className="p-2 text-start">{lang==='ar'?'الحساب':'Account'}</th><th className="p-2 text-end">{lang==='ar'?'دفاتر':'Books'}</th><th className="p-2 text-end">{lang==='ar'?'كشف':'Statement'}</th><th className="p-2 text-end">{lang==='ar'?'الفرق':'Difference'}</th><th className="p-2 text-start">{lang==='ar'?'الحالة':'Status'}</th></tr></thead><tbody>{reconciliations.length===0?<tr><td colSpan={6} className="p-6 text-center text-muted-foreground">{lang==='ar'?'لا توجد مطابقات بعد.':'No reconciliations yet.'}</td></tr>:reconciliations.map(r=><tr key={r.id} className="border-b"><td className="p-2">{r.reconciliation_date}</td><td className="p-2">{lang==='ar'?(r.account?.name_ar||r.account?.name):r.account?.name}</td><td className="p-2 text-end">{fmt(Number(r.book_balance))}</td><td className="p-2 text-end">{fmt(Number(r.statement_balance))}</td><td className={`p-2 text-end ${Math.abs(Number(r.difference))<0.01?'text-green-600':'text-amber-600'}`}>{fmt(Number(r.difference))}</td><td className="p-2"><Badge variant={r.status==='matched'?'default':'outline'}>{r.status}</Badge></td></tr>)}</tbody></table></div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <Dialog open={accountDialogOpen} onOpenChange={setAccountDialogOpen}>
        <DialogContent className="w-[calc(100vw-1rem)] sm:max-w-lg">
          <DialogHeader><DialogTitle>{lang==='ar'?'إنشاء حساب مالي جديد':'Create financial account'}</DialogTitle><DialogDescription>{lang==='ar'?'أنشئ الحساب مرة واحدة في دليل الحسابات ثم اربطه بالافتتاحية أو استخدمه في القيود.':'Create the ledger account once, then use it in opening balances and journals.'}</DialogDescription></DialogHeader>
          <div className="grid gap-3 sm:grid-cols-2">
            <div><Label>{lang==='ar'?'الاسم':'Name'}</Label><Input value={newAccountForm.name} onChange={e=>setNewAccountForm({...newAccountForm,name:e.target.value})} /></div>
            <div><Label>{lang==='ar'?'الاسم بالعربية':'Arabic name'}</Label><Input value={newAccountForm.name_ar} onChange={e=>setNewAccountForm({...newAccountForm,name_ar:e.target.value})} /></div>
            <div><Label>{lang==='ar'?'نوع الحساب':'Type'}</Label><Select value={newAccountForm.account_type} onValueChange={v=>setNewAccountForm({...newAccountForm,account_type:v})}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="cash">{lang==='ar'?'صندوق / نقدي':'Cash'}</SelectItem><SelectItem value="bank">{lang==='ar'?'بنك':'Bank'}</SelectItem><SelectItem value="payment_gateway">{lang==='ar'?'بوابة دفع':'Payment gateway'}</SelectItem><SelectItem value="other">{lang==='ar'?'أصل مالي آخر':'Other financial asset'}</SelectItem></SelectContent></Select></div>
            <div><Label>{lang==='ar'?'العملة':'Currency'}</Label><Select value={newAccountForm.currency} onValueChange={v=>setNewAccountForm({...newAccountForm,currency:v})}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="SYP">SYP</SelectItem><SelectItem value="USD">USD</SelectItem></SelectContent></Select></div>
            {(newAccountForm.account_type==='bank') && <><div><Label>{lang==='ar'?'اسم البنك':'Bank name'}</Label><Input value={newAccountForm.bank_name} onChange={e=>setNewAccountForm({...newAccountForm,bank_name:e.target.value})} /></div><div><Label>{lang==='ar'?'رقم الحساب':'Account number'}</Label><Input value={newAccountForm.account_number} onChange={e=>setNewAccountForm({...newAccountForm,account_number:e.target.value})} /></div></>}
          </div>
          <DialogFooter><Button variant="outline" onClick={()=>setAccountDialogOpen(false)}>{lang==='ar'?'إلغاء':'Cancel'}</Button><Button onClick={()=>void createFinancialAccount()} disabled={accountSaving}>{accountSaving?(lang==='ar'?'جارٍ الإنشاء...':'Creating...'):(lang==='ar'?'إنشاء الحساب':'Create account')}</Button></DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Purchase Dialog */}
      <Dialog open={purchaseDialog} onOpenChange={(open) => { setPurchaseDialog(open); if (!open) setEditingPurchase(null); }}>
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{editingPurchase ? t.edit : t.addPurchase}</DialogTitle>
            <DialogDescription>
              {lang === "ar" ? "اختر الصنف من المخزون وأدخل الكمية والتكلفة" : "Select an item from inventory and enter quantity and cost"}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="rounded border bg-muted/30 p-3 text-sm text-muted-foreground">
              {lang === "ar" ? "وحدة القياس تظهر تلقائياً حسب الصنف. جميع الأسعار بالليرة السورية." : "The unit of measure appears automatically based on the item. All prices are in SYP."}
            </div>
            <div className="space-y-2">
              <Label>{lang === "ar" ? "المادة" : "Material"} *</Label>
            </div>
            <div className="space-y-2">
              <Label>{t.selectItem}</Label>
              <div className="flex gap-2">
                <div className="flex-1">
                  <Select value={purchaseForm.material_id} onValueChange={handleMaterialChange}>
                    <SelectTrigger>
                      <SelectValue placeholder={t.selectItem} />
                    </SelectTrigger>
                    <SelectContent>
                      {materials.map((m) => (
                        <SelectItem key={m.id} value={String(m.id)}>
                          {m.name} ({typeLabel(m.material_category)}) · {t.currentStock}: {fmt(Number(m.current_stock))} {unitLabel(m.base_unit)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <Button variant="outline" type="button" onClick={() => setNewItemDialog(true)}>
                  <Plus className="h-4 w-4 mr-1" />
                  {t.addNewItem}
                </Button>
              </div>
              <p className="text-xs text-muted-foreground">
                {lang === "ar" ? "اختر مادة موحدة؛ سيتم تحديث الرصيد ومتوسط التكلفة تلقائياً." : "Select a unified material; stock and moving average cost will update automatically."}
              </p>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label>{t.quantity} * <span className="text-muted-foreground">({selectedMaterial ? unitLabel(selectedMaterial.base_unit) : t.unit})</span></Label>
                <Input type="text" inputMode="decimal" value={String(purchaseForm.quantity || "")} onChange={(e) => setPurchaseForm({ ...purchaseForm, quantity: e.target.value as any })} />
              </div>
              <div className="space-y-2">
                <Label>{t.costPerUnit} *</Label>
                <Input type="text" inputMode="decimal" value={String(purchaseForm.cost_per_unit || "")} onChange={(e) => setPurchaseForm({ ...purchaseForm, cost_per_unit: e.target.value as any })} />
              </div>
              <div className="space-y-2">
                <Label>{t.totalCost}</Label>
                <Input type="text" value={fmt(purchaseTotal)} disabled className="bg-green-50 font-medium" />
              </div>
              <div className="space-y-2">
                <Label>{t.date} *</Label>
                <Input type="date" value={purchaseForm.purchase_date} onChange={(e) => setPurchaseForm({ ...purchaseForm, purchase_date: e.target.value })} />
              </div>
              <div className="space-y-2">
                <Label>{lang === "ar" ? "طريقة الدفع" : "Payment Method"}</Label>
                <Select
                  value={purchaseForm.payment_method}
                  onValueChange={(v) => setPurchaseForm({ ...purchaseForm, payment_method: v, paid_amount: v === "credit" ? 0 : purchaseTotal })}
                >
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="cash">{lang === "ar" ? "نقدي" : "Cash"}</SelectItem>
                    <SelectItem value="credit">{lang === "ar" ? "آجل" : "Credit"}</SelectItem>
                  </SelectContent>
                </Select>
                {purchaseForm.payment_method === "credit" && (
                  <p className="text-xs text-amber-600">
                    {lang === "ar" ? "سيُسجَّل كذمة دائنة غير مدفوعة (يمكن تسديدها لاحقًا من نفس الشاشة)." : "Recorded as an unpaid payable; settle it later from this screen."}
                  </p>
                )}
              </div>
              <div className="space-y-2">
                <Label>{t.supplier}</Label>
                <Input value={purchaseForm.supplier} onChange={(e) => setPurchaseForm({ ...purchaseForm, supplier: e.target.value })} />
              </div>
              <div className="space-y-2">
                <Label>{t.invoiceNo}</Label>
                <Input value={purchaseForm.invoice_number} onChange={(e) => setPurchaseForm({ ...purchaseForm, invoice_number: e.target.value })} />
              </div>
              <div className="space-y-2 md:col-span-2">
                <Label>{t.notes}</Label>
                <Textarea value={purchaseForm.notes} onChange={(e) => setPurchaseForm({ ...purchaseForm, notes: e.target.value })} rows={2} />
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setPurchaseDialog(false)}>{t.cancel}</Button>
            <Button onClick={() => void handlePurchaseSubmit()} disabled={loading}>{t.save}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* New Item Dialog */}
      <Dialog open={newItemDialog} onOpenChange={setNewItemDialog}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{t.addNewItem}</DialogTitle>
            <DialogDescription>
              {lang === "ar" ? "سيُضاف الصنف إلى المخزون ويمكن استخدامه في المشتريات" : "The item will be added to inventory and available for purchases"}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label>{t.itemName} *</Label>
                <Input value={newItemForm.name} onChange={(e) => setNewItemForm({ ...newItemForm, name: e.target.value })} />
              </div>
              <div className="space-y-2">
                <Label>{t.itemNameAr}</Label>
                <Input value={newItemForm.name_ar} onChange={(e) => setNewItemForm({ ...newItemForm, name_ar: e.target.value })} />
              </div>
              <div className="space-y-2">
                <Label>{t.itemType} *</Label>
                <Select value={newItemForm.type} onValueChange={(v) => setNewItemForm({ ...newItemForm, type: v })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="essential_oil">{t.type_oil}</SelectItem>
                    <SelectItem value="alcohol">{t.type_alcohol}</SelectItem>
                    <SelectItem value="bottle">{t.type_bottle}</SelectItem>
                    <SelectItem value="packaging">{t.type_packaging}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t.unitType} *</Label>
                <Select value={newItemForm.unit} onValueChange={(v) => setNewItemForm({ ...newItemForm, unit: v })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="grams">{t.unit_grams}</SelectItem>
                    <SelectItem value="liters">{t.unit_liters}</SelectItem>
                    <SelectItem value="pieces">{t.unit_pieces}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t.costPerUnit} *</Label>
                <Input type="text" inputMode="decimal" value={String(newItemForm.cost_per_unit || "")} onChange={(e) => setNewItemForm({ ...newItemForm, cost_per_unit: e.target.value as any })} />
              </div>
              <div className="space-y-2">
                <Label>{t.currentStock}</Label>
                <Input type="text" inputMode="decimal" value={String(newItemForm.current_stock || "")} onChange={(e) => setNewItemForm({ ...newItemForm, current_stock: e.target.value as any })} />
              </div>
              <div className="space-y-2">
                <Label>{t.minStock}</Label>
                <Input type="text" inputMode="decimal" value={String(newItemForm.min_stock || "")} onChange={(e) => setNewItemForm({ ...newItemForm, min_stock: e.target.value as any })} />
              </div>
              <div className="space-y-2">
                <Label>{t.supplier}</Label>
                <Input value={newItemForm.supplier} onChange={(e) => setNewItemForm({ ...newItemForm, supplier: e.target.value })} />
              </div>
              <div className="space-y-2 md:col-span-2">
                <Label>{t.notes}</Label>
                <Textarea value={newItemForm.notes} onChange={(e) => setNewItemForm({ ...newItemForm, notes: e.target.value })} rows={2} />
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setNewItemDialog(false)}>{t.cancel}</Button>
            <Button onClick={() => void handleNewItemSubmit()} disabled={loading}>{t.save}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Expense Dialog */}
      <Dialog open={expenseDialog} onOpenChange={(open) => { setExpenseDialog(open); if (!open) setEditingExpense(null); }}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{editingExpense ? t.edit : t.addExpense}</DialogTitle>
            <DialogDescription>
              {lang === "ar" ? "سجل المصروف وتحدد مدة الإيجار بالأيام عند الحاجة" : "Record an expense and specify rent duration in days when applicable"}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>{t.category} *</Label>
              <Select value={expenseForm.category} onValueChange={(v) => setExpenseForm({ ...expenseForm, category: v })}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="rent">{t.catRent}</SelectItem>
                  <SelectItem value="transport">{t.catTransport}</SelectItem>
                  <SelectItem value="salaries">{t.catSalaries}</SelectItem>
                  <SelectItem value="marketing">{t.catMarketing}</SelectItem>
                  <SelectItem value="utilities">{t.catUtilities}</SelectItem>
                  <SelectItem value="other">{t.catOther}</SelectItem>
                </SelectContent>
              </Select>
            </div>

            {expenseForm.category === "rent" && (
              <div className="space-y-2">
                <Label>{t.rentDuration}</Label>
                <Input type="text" inputMode="decimal" value={String(expenseForm.rent_duration_days || "")} onChange={(e) => setExpenseForm({ ...expenseForm, rent_duration_days: e.target.value as any })} />
              </div>
            )}

            <div className="space-y-2">
              <Label>{t.description} *</Label>
              <Input value={expenseForm.description} onChange={(e) => setExpenseForm({ ...expenseForm, description: e.target.value })} />
            </div>

            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label>{t.amount} *</Label>
                <Input type="text" inputMode="decimal" value={String(expenseForm.amount || "")} onChange={(e) => setExpenseForm({ ...expenseForm, amount: e.target.value as any })} />
              </div>
              <div className="space-y-2">
                <Label>{t.paymentMethod} *</Label>
                <Select value={expenseForm.payment_method} onValueChange={(v) => setExpenseForm({ ...expenseForm, payment_method: v })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="cash">{t.cash}</SelectItem>
                    <SelectItem value="credit">{t.credit}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t.date} *</Label>
                <Input type="date" value={expenseForm.expense_date} onChange={(e) => setExpenseForm({ ...expenseForm, expense_date: e.target.value })} />
              </div>
              <div className="space-y-2">
                <Label>{t.vendor}</Label>
                <Input value={expenseForm.vendor} onChange={(e) => setExpenseForm({ ...expenseForm, vendor: e.target.value })} />
              </div>
              <div className="space-y-2 md:col-span-2">
                <Label>{t.notes}</Label>
                <Textarea value={expenseForm.notes} onChange={(e) => setExpenseForm({ ...expenseForm, notes: e.target.value })} rows={2} />
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setExpenseDialog(false)}>{t.cancel}</Button>
            <Button onClick={() => void handleExpenseSubmit()} disabled={loading}>{t.save}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Fixed Asset Dialog */}
      <Dialog open={assetDialog} onOpenChange={(open) => { setAssetDialog(open); if (!open) setEditingAsset(null); }}>
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{editingAsset ? t.edit : lang === "ar" ? "إضافة أصل ثابت" : "Add Fixed Asset"}</DialogTitle>
            <DialogDescription>
              {lang === "ar" ? "سجل الأصول الثابتة ومعلومات الإهلاك" : "Record fixed assets and depreciation information"}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label>{lang === "ar" ? "اسم الأصل" : "Asset Name"} *</Label>
                <Input value={assetForm.name} onChange={(e) => setAssetForm({ ...assetForm, name: e.target.value })} />
              </div>
              <div className="space-y-2">
                <Label>{lang === "ar" ? "رقم الأصل" : "Asset Number"} *</Label>
                <Input value={assetForm.asset_number} onChange={(e) => setAssetForm({ ...assetForm, asset_number: e.target.value })} />
              </div>
              <div className="space-y-2">
                <Label>{lang === "ar" ? "الفئة" : "Category"} *</Label>
                <Select value={assetForm.category} onValueChange={(v) => setAssetForm({ ...assetForm, category: v })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="machinery">{lang === "ar" ? "آلات" : "Machinery"}</SelectItem>
                    <SelectItem value="equipment">{lang === "ar" ? "معدات" : "Equipment"}</SelectItem>
                    <SelectItem value="vehicle">{lang === "ar" ? "مركبات" : "Vehicle"}</SelectItem>
                    <SelectItem value="furniture">{lang === "ar" ? "أثاث" : "Furniture"}</SelectItem>
                    <SelectItem value="electronics">{lang === "ar" ? "إلكترونيات" : "Electronics"}</SelectItem>
                    <SelectItem value="building">{lang === "ar" ? "مباني" : "Building"}</SelectItem>
                    <SelectItem value="land">{lang === "ar" ? "أراضي" : "Land"}</SelectItem>
                    <SelectItem value="other">{lang === "ar" ? "أخرى" : "Other"}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{lang === "ar" ? "الكمية" : "Quantity"} *</Label>
                <Input type="text" inputMode="decimal" value={String(assetForm.quantity || 1)} onChange={(e) => setAssetForm({ ...assetForm, quantity: e.target.value as any })} />
              </div>
              <div className="space-y-2">
                <Label>{lang === "ar" ? "التكلفة الإجمالية" : "Total Cost"} *</Label>
                <Input type="text" inputMode="decimal" value={String(assetForm.purchase_cost || "")} onChange={(e) => setAssetForm({ ...assetForm, purchase_cost: e.target.value as any })} />
                <p className="text-xs text-muted-foreground">{lang === "ar" ? "التكلفة لكل وحدة" : "Cost per unit"}: {assetForm.quantity > 0 ? fmt(Number(assetForm.purchase_cost) / assetForm.quantity) : 0} SYP</p>
              </div>
              <div className="space-y-2">
                <Label>{lang === "ar" ? "تاريخ الشراء" : "Purchase Date"} *</Label>
                <Input type="date" value={assetForm.purchase_date} onChange={(e) => setAssetForm({ ...assetForm, purchase_date: e.target.value })} />
              </div>
              <div className="space-y-2">
                <Label>{lang === "ar" ? "طريقة الإهلاك" : "Depreciation Method"} *</Label>
                <Select value={assetForm.depreciation_method} onValueChange={(v) => setAssetForm({ ...assetForm, depreciation_method: v })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="straight_line">{lang === "ar" ? "القسط الثابت" : "Straight Line"}</SelectItem>
                    <SelectItem value="declining_balance">{lang === "ar" ? "الرصيد المتناقص" : "Declining Balance"}</SelectItem>
                    <SelectItem value="units_of_production">{lang === "ar" ? "وحدات الإنتاج" : "Units of Production"}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{lang === "ar" ? "العمر الافتراضي (سنوات)" : "Useful Life (Years)"} *</Label>
                <Input type="text" inputMode="decimal" value={String(assetForm.useful_life_years || "")} onChange={(e) => setAssetForm({ ...assetForm, useful_life_years: e.target.value as any })} />
              </div>
              {assetForm.depreciation_method === "units_of_production" && (
                <div className="space-y-2">
                  <Label>{lang === "ar" ? "إجمالي وحدات الإنتاج المتوقعة" : "Estimated Total Production Units"} *</Label>
                  <Input type="text" inputMode="decimal" value={String(assetForm.total_estimated_units || "")} onChange={(e) => setAssetForm({ ...assetForm, total_estimated_units: e.target.value as any })} />
                </div>
              )}
              <div className="space-y-2">
                <Label>{lang === "ar" ? "القيمة التخريدية" : "Salvage Value"}</Label>
                <Input type="text" inputMode="decimal" value={String(assetForm.salvage_value || "")} onChange={(e) => setAssetForm({ ...assetForm, salvage_value: e.target.value as any })} />
              </div>
              <div className="space-y-2">
                <Label>{lang === "ar" ? "تاريخ بدء الإهلاك" : "Depreciation Start Date"}</Label>
                <Input type="date" value={assetForm.depreciation_start_date} onChange={(e) => setAssetForm({ ...assetForm, depreciation_start_date: e.target.value })} />
              </div>
              <div className="space-y-2">
                <Label>{lang === "ar" ? "الموقع" : "Location"}</Label>
                <Input value={assetForm.location} onChange={(e) => setAssetForm({ ...assetForm, location: e.target.value })} />
              </div>
              <div className="space-y-2">
                <Label>{lang === "ar" ? "الرقم التسلسلي" : "Serial Number"}</Label>
                <Input value={assetForm.serial_number} onChange={(e) => setAssetForm({ ...assetForm, serial_number: e.target.value })} />
              </div>
              <div className="space-y-2 md:col-span-2">
                <Label>{lang === "ar" ? "الوصف" : "Description"}</Label>
                <Textarea value={assetForm.description} onChange={(e) => setAssetForm({ ...assetForm, description: e.target.value })} rows={2} />
              </div>
              <div className="space-y-2 md:col-span-2">
                <Label>{t.notes}</Label>
                <Textarea value={assetForm.notes} onChange={(e) => setAssetForm({ ...assetForm, notes: e.target.value })} rows={2} />
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setAssetDialog(false)}>{t.cancel}</Button>
            <Button onClick={() => void handleAssetSubmit()} disabled={loading}>{t.save}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Tax Configuration Dialog */}
      <Dialog open={taxDialog} onOpenChange={(open) => { setTaxDialog(open); if (!open) setEditingTax(null); }}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{editingTax ? t.edit : lang === "ar" ? "إضافة ضريبة" : "Add Tax"}</DialogTitle>
            <DialogDescription>
              {lang === "ar" ? "تكوين معدلات الضرائب" : "Configure tax rates"}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>{lang === "ar" ? "اسم الضريبة" : "Tax Name"} *</Label>
              <Input value={taxForm.name} onChange={(e) => setTaxForm({ ...taxForm, name: e.target.value })} />
            </div>
            <div className="space-y-2">
              <Label>{lang === "ar" ? "نوع الضريبة" : "Tax Type"} *</Label>
              <Select value={taxForm.tax_type} onValueChange={(v) => setTaxForm({ ...taxForm, tax_type: v })}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="vat">{lang === "ar" ? "ضريبة القيمة المضافة" : "VAT"}</SelectItem>
                  <SelectItem value="income_tax">{lang === "ar" ? "ضريبة الدخل" : "Income Tax"}</SelectItem>
                  <SelectItem value="sales_tax">{lang === "ar" ? "ضريبة المبيعات" : "Sales Tax"}</SelectItem>
                  <SelectItem value="other">{lang === "ar" ? "أخرى" : "Other"}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{lang === "ar" ? "النسبة (%)" : "Rate (%)"} *</Label>
              <Input type="text" inputMode="decimal" value={String(taxForm.rate || "")} onChange={(e) => setTaxForm({ ...taxForm, rate: e.target.value as any })} />
            </div>
            <div className="space-y-2">
              <Label>{lang === "ar" ? "تُطبق على" : "Applied To"} *</Label>
              <Select value={taxForm.applicable_to} onValueChange={(v) => setTaxForm({ ...taxForm, applicable_to: v })}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all_revenue">{lang === "ar" ? "جميع الإيرادات" : "All Revenue"}</SelectItem>
                  <SelectItem value="profit">{lang === "ar" ? "الربح" : "Profit"}</SelectItem>
                  <SelectItem value="specific_categories">{lang === "ar" ? "فئات محددة" : "Specific Categories"}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{lang === "ar" ? "تاريخ السريان" : "Effective Date"} *</Label>
              <Input type="date" value={taxForm.effective_date} onChange={(e) => setTaxForm({ ...taxForm, effective_date: e.target.value })} />
            </div>
            <div className="space-y-2">
              <Label>{lang === "ar" ? "حالة" : "Status"}</Label>
              <Select value={taxForm.is_active ? "true" : "false"} onValueChange={(v) => setTaxForm({ ...taxForm, is_active: v === "true" })}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="true">{lang === "ar" ? "نشط" : "Active"}</SelectItem>
                  <SelectItem value="false">{lang === "ar" ? "غير نشط" : "Inactive"}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2 md:col-span-2">
              <Label>{lang === "ar" ? "الوصف" : "Description"}</Label>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={!!taxForm.post_to_ledger} onChange={(e)=>setTaxForm({...taxForm, post_to_ledger:e.target.checked})} />{lang === "ar" ? "ترحيل الضريبة إلى الأستاذ" : "Post transaction tax to GL"}</label>
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={!!taxForm.tax_inclusive} onChange={(e)=>setTaxForm({...taxForm, tax_inclusive:e.target.checked})} />{lang === "ar" ? "السعر شامل الضريبة" : "Tax inclusive pricing"}</label>
              </div>
              <Textarea value={taxForm.description} onChange={(e) => setTaxForm({ ...taxForm, description: e.target.value })} rows={2} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setTaxDialog(false)}>{t.cancel}</Button>
            <Button onClick={() => void handleTaxSubmit()} disabled={loading}>{t.save}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      <Dialog open={manualJournalDialog} onOpenChange={setManualJournalDialog}>
        <DialogContent className="max-w-3xl">
          <DialogHeader><DialogTitle>{lang === 'ar' ? 'إضافة قيد يومي يدوي' : 'Post Manual Journal Entry'}</DialogTitle><DialogDescription>{lang === 'ar' ? 'القيد يُرحّل فقط إذا كان متوازنًا والفترة مفتوحة.' : 'The entry posts only when balanced and the accounting period is open.'}</DialogDescription></DialogHeader>
          <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3"><div className="space-y-1"><Label>{lang === 'ar' ? 'التاريخ' : 'Date'}</Label><Input type="date" value={manualJournalDate} onChange={(e)=>setManualJournalDate(e.target.value)} /></div><div className="space-y-1"><Label>{lang === 'ar' ? 'الوصف' : 'Description'}</Label><Input value={manualJournalDescription} onChange={(e)=>setManualJournalDescription(e.target.value)} /></div></div>
            <div className="space-y-2">{manualJournalLines.map((line, index)=><div key={index} className="grid grid-cols-1 md:grid-cols-[1fr_150px_150px_1fr_40px] gap-2 items-center"><Select value={line.account_id} onValueChange={(v)=>setManualJournalLines(prev=>prev.map((x,i)=>i===index?{...x,account_id:v}:x))}><SelectTrigger><SelectValue placeholder={lang === 'ar' ? 'الحساب' : 'Account'} /></SelectTrigger><SelectContent>{accounts.map((a)=><SelectItem key={a.id} value={String(a.id)}>{a.code} — {lang==='ar'?(a.name_ar||a.name):a.name}</SelectItem>)}</SelectContent></Select><Input type="text" inputMode="decimal" placeholder={lang==='ar'?'مدين':'Debit'} value={line.debit} onChange={(e)=>setManualJournalLines(prev=>prev.map((x,i)=>i===index?{...x,debit:e.target.value.replace(/[^0-9.]/g,'')}:x))}/><Input type="text" inputMode="decimal" placeholder={lang==='ar'?'دائن':'Credit'} value={line.credit} onChange={(e)=>setManualJournalLines(prev=>prev.map((x,i)=>i===index?{...x,credit:e.target.value.replace(/[^0-9.]/g,'')}:x))}/><Input placeholder={lang==='ar'?'بيان السطر':'Line description'} value={line.description} onChange={(e)=>setManualJournalLines(prev=>prev.map((x,i)=>i===index?{...x,description:e.target.value}:x))}/><Button type="button" variant="ghost" onClick={()=>setManualJournalLines(prev=>prev.filter((_,i)=>i!==index))}>×</Button></div>)}</div>
            <Button type="button" variant="outline" onClick={()=>setManualJournalLines(prev=>[...prev,{account_id:'',debit:'',credit:'',description:''}])}><Plus className="h-4 w-4 mr-2" />{lang==='ar'?'إضافة سطر':'Add line'}</Button>
          </div>
          <DialogFooter><Button variant="outline" onClick={()=>setManualJournalDialog(false)}>{t.cancel}</Button><Button onClick={()=>void postManualJournal()}>{lang==='ar'?'ترحيل القيد':'Post Journal'}</Button></DialogFooter>
        </DialogContent>
      </Dialog>

    </div>
  );
};

export default FinancialManagement;
