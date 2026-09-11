import { useCallback, useEffect, useMemo, useState } from "react";
import { useLanguage } from "@/hooks/useLanguage";
import { withAuthHeaders } from "@/lib/auth";
import { useAuth } from "@/hooks/useAuth";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Pencil, Plus, Shield, UserCheck, UserX } from "lucide-react";
import { toast } from "sonner";

type Role = "admin" | "manager" | "employee" | "customer";
type UserRow = { id:string; full_name:string|null; email:string|null; phone:string|null; created_at:string; role:Role; is_active:boolean };

const permissionGroups = [
  ["الطلبات", ["orders.view","orders.create","orders.edit","orders.delete","orders.prepare"]],
  ["العملاء", ["customers.view","customers.create","customers.edit","customers.delete"]],
  ["المخزون", ["inventory.view","inventory.manage","inventory.count","inventory.request"]],
  ["المشتريات", ["purchases.view","purchases.manage"]],
  ["المبيعات", ["sales.view","sales.manage"]],
  ["المالية والمصاريف", ["finance.view","expenses.manage","expenses.create"]],
  ["المنتجات", ["products.view","products.manage"]],
  ["التصنيع", ["manufacturing.view","manufacturing.manage"]],
  ["التقارير", ["reports.view"]],
  ["الإدارة", ["users.manage","audit.view","assets.manage","settings.manage","dashboard.view"]],
] as const;

const roleDefaults: Record<Role,string[]> = {
  admin: [],
  manager: ["dashboard.view","orders.view","orders.create","orders.edit","orders.delete","orders.prepare","customers.view","customers.create","customers.edit","inventory.view","inventory.manage","inventory.count","inventory.request","purchases.view","purchases.manage","sales.view","sales.manage","finance.view","expenses.manage","expenses.create","products.view","products.manage","manufacturing.view","manufacturing.manage","reports.view","audit.view","assets.manage"],
  employee: ["dashboard.view","orders.view","orders.prepare","customers.view","inventory.view","inventory.count","inventory.request","expenses.create"],
  customer: [],
};

const AdminUsers = () => {
  const { lang } = useLanguage();
  const { user: currentUser } = useAuth();
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "");
  const [users,setUsers]=useState<UserRow[]>([]); const [loading,setLoading]=useState(true);
  const [open,setOpen]=useState(false); const [editing,setEditing]=useState<UserRow|null>(null);
  const [saving,setSaving]=useState(false); const [permissionCatalog,setPermissionCatalog]=useState<Record<string,{label:string}>>({});
  const [form,setForm]=useState({name:"",email:"",phone:"",password:"",role:"employee" as Role,permissions:[] as string[],is_active:true});
  const t=(ar:string,en:string)=>lang==="ar"?ar:en;
  const roleLabel=(r:Role)=>({admin:t("صاحب المشروع","Owner"),manager:t("مدير","Manager"),employee:t("موظف","Employee"),customer:t("عميل","Customer")}[r]);

  const load=useCallback(async()=>{ setLoading(true); try { const r=await fetch(`${apiBaseUrl}/api/admin/users`,{headers:withAuthHeaders({Accept:"application/json"})}); const d=await r.json(); setUsers(Array.isArray(d?.users)?d.users:[]); } catch { toast.error(t("تعذر تحميل المستخدمين","Failed to load users")); } finally{setLoading(false);} },[apiBaseUrl,lang]);
  useEffect(()=>{void load();},[load]);

  const openCreate=()=>{setEditing(null);setForm({name:"",email:"",phone:"",password:"",role:"employee",permissions:roleDefaults.employee, is_active:true});setOpen(true);};
  const openEdit=async(u:UserRow)=>{setEditing(u);setForm({name:u.full_name||"",email:u.email||"",phone:u.phone||"",password:"",role:u.role,permissions:roleDefaults[u.role]||[], is_active:u.is_active});setOpen(true); try {const r=await fetch(`${apiBaseUrl}/api/admin/users/${u.id}/permissions`,{headers:withAuthHeaders({Accept:"application/json"})});const d=await r.json(); if(Array.isArray(d?.permissions)) setForm(f=>({...f,permissions:d.permissions})); if(d?.catalog) setPermissionCatalog(d.catalog);} catch { /* defaults remain */ }};

  const save=async()=>{setSaving(true); try { const payload:any={name:form.name,email:form.email,phone:form.phone,role:form.role,permissions:form.permissions,is_active:form.is_active}; if(form.password) payload.password=form.password; const url=editing?`${apiBaseUrl}/api/admin/users/${editing.id}`:`${apiBaseUrl}/api/admin/users`; const r=await fetch(url,{method:editing?"PATCH":"POST",headers:withAuthHeaders({'Content-Type':'application/json',Accept:'application/json'}),body:JSON.stringify(payload)}); const d=await r.json(); if(!r.ok||!d?.ok) throw new Error(d?.message||"Request failed"); toast.success(t("تم حفظ المستخدم","User saved")); setOpen(false); await load(); } catch(e){toast.error(e instanceof Error?e.message:t("تعذر الحفظ","Save failed"));} finally{setSaving(false);}};

  const permissionText=(p:string)=>permissionCatalog[p]?.label||({
    "orders.view":t("عرض الطلبات","View orders"),"orders.create":t("إنشاء الطلبات","Create orders"),"orders.edit":t("تعديل الطلبات","Edit orders"),"orders.delete":t("حذف الطلبات","Delete orders"),"orders.prepare":t("تجهيز الطلبات","Prepare orders"),
    "customers.view":t("عرض العملاء","View customers"),"customers.create":t("إضافة العملاء","Create customers"),"customers.edit":t("تعديل العملاء","Edit customers"),"customers.delete":t("أرشفة العملاء","Archive customers"),
    "inventory.view":t("عرض كميات المخزون","View inventory quantities"),"inventory.manage":t("إدارة المخزون","Manage inventory"),"inventory.count":t("إجراء الجرد","Perform stock counts"),"inventory.request":t("طلب بضاعة","Request stock"),"purchases.view":t("عرض المشتريات","View purchases"),"purchases.manage":t("إدارة المشتريات","Manage purchases"),
    "sales.view":t("عرض المبيعات","View sales"),"sales.manage":t("إدارة المبيعات","Manage sales"),"finance.view":t("عرض المالية","View financials"),"expenses.manage":t("إدارة المصاريف","Manage expenses"),"expenses.create":t("إدخال المصاريف","Create expenses"),
    "products.view":t("عرض المنتجات","View products"),"products.manage":t("إدارة المنتجات","Manage products"),"manufacturing.view":t("عرض التصنيع","View manufacturing"),"manufacturing.manage":t("إدارة التصنيع","Manage manufacturing"),
    "reports.view":t("عرض التقارير","View reports"),"users.manage":t("إدارة المستخدمين","Manage users"),"audit.view":t("سجل العمليات","Audit log"),"assets.manage":t("إدارة الأصول","Manage assets"),"settings.manage":t("الإعدادات","Settings"),"dashboard.view":t("لوحة التحكم","Dashboard")
  } as Record<string,string>)[p]||p;
  const toggle=(p:string,checked:boolean)=>setForm(f=>({...f,permissions:checked?[...f.permissions,p]:f.permissions.filter(x=>x!==p)}));
  const summaries=useMemo(()=>({total:users.length, managers:users.filter(u=>u.role==='manager').length, employees:users.filter(u=>u.role==='employee').length, active:users.filter(u=>u.is_active).length}),[users]);

  return <div className="w-full max-w-[1600px] mx-auto space-y-5">
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div><h1 className="text-2xl sm:text-3xl font-display font-bold">{t("المستخدمون والصلاحيات","Users & Permissions")}</h1><p className="text-sm text-muted-foreground mt-1">{t("تحكم مركزي بالمستخدمين والصلاحيات دون كشف البيانات المالية للموظفين","Centralized user and permission management")}</p></div>
      <Button onClick={openCreate} className="gap-2 w-full sm:w-auto"><Plus className="h-4 w-4"/>{t("إضافة مستخدم","Add user")}</Button>
    </div>
    <div className="grid grid-cols-2 lg:grid-cols-4 gap-3"><Card><CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">{t("إجمالي المستخدمين","Total users")}</CardTitle></CardHeader><CardContent className="text-2xl font-bold">{summaries.total}</CardContent></Card><Card><CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">{t("المديرون","Managers")}</CardTitle></CardHeader><CardContent className="text-2xl font-bold">{summaries.managers}</CardContent></Card><Card><CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">{t("الموظفون","Employees")}</CardTitle></CardHeader><CardContent className="text-2xl font-bold">{summaries.employees}</CardContent></Card><Card><CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">{t("الحسابات النشطة","Active")}</CardTitle></CardHeader><CardContent className="text-2xl font-bold">{summaries.active}</CardContent></Card></div>
    <Card><CardContent className="p-0"><div className="overflow-x-auto"><table className="w-full min-w-[820px] text-sm"><thead className="bg-muted/40"><tr><th className="p-3 text-start">{t("المستخدم","User")}</th><th className="p-3 text-start">{t("الدور","Role")}</th><th className="p-3 text-start">{t("الحالة","Status")}</th><th className="p-3 text-start">{t("الهاتف","Phone")}</th><th className="p-3 text-start">{t("الإجراءات","Actions")}</th></tr></thead><tbody>{loading?<tr><td colSpan={5} className="p-10 text-center text-muted-foreground">{t("جاري التحميل…","Loading…")}</td></tr>:users.map(u=><tr key={u.id} className="border-t hover:bg-muted/20"><td className="p-3"><div className="font-medium">{u.full_name||"—"}</div><div className="text-xs text-muted-foreground">{u.email||"—"}</div></td><td className="p-3"><Badge variant={u.role==='admin'?"default":"outline"}>{roleLabel(u.role)}</Badge></td><td className="p-3">{u.is_active?<Badge className="gap-1"><UserCheck className="h-3 w-3"/>{t("نشط","Active")}</Badge>:<Badge variant="destructive" className="gap-1"><UserX className="h-3 w-3"/>{t("معطل","Inactive")}</Badge>}</td><td className="p-3">{u.phone||"—"}</td><td className="p-3"><Button size="sm" variant="outline" onClick={()=>void openEdit(u)} className="gap-2"><Pencil className="h-4 w-4"/>{t("تعديل","Edit")}</Button></td></tr>)}</tbody></table></div></CardContent></Card>

    <Dialog open={open} onOpenChange={setOpen}><DialogContent className="w-[calc(100vw-1rem)] sm:max-w-3xl max-h-[92vh] overflow-y-auto"><DialogHeader><DialogTitle className="flex items-center gap-2"><Shield className="h-5 w-5"/>{editing?t("تعديل المستخدم","Edit user"):t("إضافة مستخدم","Add user")}</DialogTitle><DialogDescription>{t("اختر الدور ثم اضبط الصلاحيات الإضافية لهذا الحساب.","Choose a role and adjust the user's permissions.")}</DialogDescription></DialogHeader>
      <div className="grid gap-4 sm:grid-cols-2"><div className="space-y-2"><Label>{t("الاسم","Name")}</Label><Input value={form.name} onChange={e=>setForm({...form,name:e.target.value})}/></div><div className="space-y-2"><Label>{t("الهاتف","Phone")}</Label><Input value={form.phone} onChange={e=>setForm({...form,phone:e.target.value})}/></div><div className="space-y-2"><Label>{t("البريد","Email")}</Label><Input type="email" value={form.email} onChange={e=>setForm({...form,email:e.target.value})}/></div><div className="space-y-2"><Label>{t(editing?"كلمة مرور جديدة (اختياري)":"كلمة المرور","Password")}</Label><Input type="password" value={form.password} onChange={e=>setForm({...form,password:e.target.value})} placeholder={editing?t("اتركها فارغة للإبقاء عليها","Leave blank to keep current"):t("12 حرفًا على الأقل","At least 12 characters")}/></div><div className="space-y-2"><Label>{t("الحالة","Status")}</Label><Select value={form.is_active?"active":"inactive"} onValueChange={v=>setForm({...form,is_active:v==="active"})}><SelectTrigger><SelectValue/></SelectTrigger><SelectContent><SelectItem value="active">{t("نشط","Active")}</SelectItem><SelectItem value="inactive">{t("معطل","Inactive")}</SelectItem></SelectContent></Select></div><div className="space-y-2 sm:col-span-2"><Label>{t("الدور","Role")}</Label><Select value={form.role} onValueChange={v=>setForm({...form,role:v as Role,permissions:roleDefaults[v as Role]||[]})}><SelectTrigger><SelectValue/></SelectTrigger><SelectContent><SelectItem value="admin">{roleLabel("admin")}</SelectItem><SelectItem value="manager">{roleLabel("manager")}</SelectItem><SelectItem value="employee">{roleLabel("employee")}</SelectItem><SelectItem value="customer">{roleLabel("customer")}</SelectItem></SelectContent></Select></div></div>
      {form.role==='admin'?<div className="rounded-lg border bg-muted/30 p-4 text-sm">{t("صاحب المشروع يملك جميع الصلاحيات تلقائياً.","Owner automatically receives all permissions.")}</div>:<div className="space-y-4">{permissionGroups.map(([group,perms])=><div key={group} className="rounded-xl border p-4"><div className="font-semibold mb-3">{t(group,group)}</div><div className="grid grid-cols-1 sm:grid-cols-2 gap-3">{perms.map(p=><label key={p} className="flex items-center gap-2 rounded-lg px-2 py-2 hover:bg-muted/30 cursor-pointer"><Checkbox checked={form.permissions.includes(p)} onCheckedChange={v=>toggle(p,Boolean(v))}/><span className="text-sm">{permissionText(p)}</span></label>)}</div></div>)}</div>}
      <DialogFooter><Button variant="outline" onClick={()=>setOpen(false)}>{t("إلغاء","Cancel")}</Button><Button onClick={()=>void save()} disabled={saving || !form.name || !form.email || (!editing && !form.password)}>{saving?t("جاري الحفظ…","Saving…"):t("حفظ المستخدم","Save user")}</Button></DialogFooter>
    </DialogContent></Dialog>
  </div>;
};
export default AdminUsers;
