import { useState } from "react";
import { Navigate } from "react-router-dom";
import { useAuth } from "@/hooks/useAuth";
import { useLanguage } from "@/hooks/useLanguage";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { toast } from "sonner";
import { Eye, EyeOff, Mail, Lock, User, Phone } from "lucide-react";
import { motion } from "framer-motion";

const PHONE_PATTERN = /^\+9639\d{8}$/;

const normalizePhone = (value: string) => value.replace(/[\s\-()]/g, "").trim();

const Auth = () => {
  const { user, loading, signIn, signUp } = useAuth();
  const { lang } = useLanguage();
  const [isLogin, setIsLogin] = useState(true);
  const [identifier, setIdentifier] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [fullName, setFullName] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  if (loading) return <div className="min-h-screen flex items-center justify-center"><div className="animate-spin h-8 w-8 border-2 border-primary border-t-transparent rounded-full" /></div>;
  if (user) return <Navigate to="/" replace />;

  const ar = lang === "ar";
  const t = {
    login: ar ? "تسجيل الدخول" : "Sign In",
    signup: ar ? "إنشاء حساب" : "Create Account",
    email: ar ? "البريد الإلكتروني" : "Email",
    identifier: ar ? "البريد الإلكتروني أو رقم الهاتف" : "Email or phone number",
    phone: ar ? "رقم الموبايل" : "Mobile number",
    phoneHint: ar ? "يجب أن يبدأ الرقم بـ +963، مثال: +9639XXXXXXXX" : "The number must start with +963, e.g. +9639XXXXXXXX",
    password: ar ? "كلمة المرور" : "Password",
    name: ar ? "الاسم الكامل" : "Full Name",
    noAccount: ar ? "ليس لديك حساب؟" : "Don't have an account?",
    hasAccount: ar ? "لديك حساب بالفعل؟" : "Already have an account?",
    welcome: ar ? "مرحباً بك في روح" : "Welcome to Rouh",
    subtitle: ar ? "عطور فاخرة تعكس أناقتك" : "Luxury fragrances reflecting your elegance",
    accountCreated: ar ? "تم إنشاء الحساب وتسجيل الدخول" : "Account created and signed in",
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      if (isLogin) {
        const loginIdentifier = identifier.trim();
        const normalizedPhone = normalizePhone(loginIdentifier);
        const looksLikePhone = /^\+?\d[\d\s\-()]*$/.test(loginIdentifier);
        if (looksLikePhone && !PHONE_PATTERN.test(normalizedPhone)) {
          throw new Error(ar ? "رقم الهاتف يجب أن يبدأ بـ +963 ويكون بصيغة سورية صحيحة." : "Phone number must start with +963 and use a valid Syrian format.");
        }
        if (!looksLikePhone && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(loginIdentifier)) {
          throw new Error(ar ? "أدخل بريدك الإلكتروني أو رقم هاتفك بصيغة +9639XXXXXXXX." : "Enter your email address or phone number in the format +9639XXXXXXXX.");
        }
        const { error } = await signIn(looksLikePhone ? normalizedPhone : loginIdentifier, password);
        if (error) throw error;
        toast.success(ar ? "تم تسجيل الدخول بنجاح" : "Signed in successfully");
      } else {
        const normalizedPhone = normalizePhone(phone);
        if (!PHONE_PATTERN.test(normalizedPhone)) {
          throw new Error(ar ? "رقم الهاتف يجب أن يبدأ بـ +963، مثال: +9639XXXXXXXX" : "Phone number must start with +963, e.g. +9639XXXXXXXX");
        }
        const { error } = await signUp(email.trim(), password, fullName.trim(), normalizedPhone);
        if (error) throw error;
        toast.success(t.accountCreated);
      }
    } catch (error: unknown) {
      toast.error(error instanceof Error ? error.message : String(error));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-dark flex items-center justify-center p-4">
      <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="w-full max-w-md">
        <div className="text-center mb-8">
          <h1 className="font-display text-4xl text-gradient-gold mb-2">{t.welcome}</h1>
          <p className="text-muted-foreground">{t.subtitle}</p>
        </div>
        <div className="glass-dark rounded-2xl p-8 border border-gold/20">
          <div className="flex gap-2 mb-6">
            <Button variant={isLogin ? "default" : "ghost"} className={`flex-1 ${isLogin ? "bg-gradient-gold text-primary-foreground" : "text-muted-foreground"}`} onClick={() => setIsLogin(true)}>{t.login}</Button>
            <Button variant={!isLogin ? "default" : "ghost"} className={`flex-1 ${!isLogin ? "bg-gradient-gold text-primary-foreground" : "text-muted-foreground"}`} onClick={() => setIsLogin(false)}>{t.signup}</Button>
          </div>
          <form onSubmit={handleSubmit} className="space-y-4">
            {!isLogin && <div className="space-y-2"><Label htmlFor="name" className=" text-gold-dark">{t.name}</Label><div className="relative "><User className="absolute start-3 top-3 h-4 w-4 text-muted-foreground " /><Input id="name" autoComplete="name" value={fullName} onChange={e => setFullName(e.target.value)} className="auth-input ps-10" required /></div></div>}
            {isLogin ? (
              <div className="space-y-2 "><Label htmlFor="identifier" className=" text-gold-dark">{t.identifier}</Label><div className="relative"><User className="absolute start-3 top-3 h-4 w-4 text-muted-foreground" /><Input id="identifier" autoComplete="username" dir="ltr" value={identifier} onChange={e => setIdentifier(e.target.value) } className="auth-input ps-10" placeholder={ar ? "name@example.com أو +9639XXXXXXXX" : "name@example.com or +9639XXXXXXXX"} required /></div></div>
            ) : (
              <>
                <div className="space-y-2"><Label htmlFor="email" className="text-gold-dark">{t.email}</Label><div className="relative"><Mail className="absolute start-3 top-3 h-4 w-4 text-muted-foreground" /><Input id="email" type="email" autoComplete="email" dir="ltr" value={email} onChange={e => setEmail(e.target.value)} className="auth-input ps-10" required /></div></div>
                <div className="space-y-2"><Label htmlFor="phone" className="text-gold-dark">{t.phone}</Label><div className="relative"><Phone className="absolute start-3 top-3 h-4 w-4 text-muted-foreground" /><Input id="phone" type="tel" autoComplete="tel" dir="ltr" value={phone} onChange={e => setPhone(e.target.value)} className="auth-input ps-10" placeholder="+9639XXXXXXXX" required pattern="\+9639[0-9]{8}" /></div><p className="text-xs text-muted-foreground">{t.phoneHint}</p></div>
              </>
            )}
            <div className="space-y-2"><Label htmlFor="password" className="text-gold-dark">{t.password}</Label><div className="relative"><Lock className="absolute start-3 top-3 h-4 w-4 text-muted-foreground" /><Input id="password" type={showPassword ? "text" : "password"} autoComplete={isLogin ? "current-password" : "new-password"} value={password} onChange={e => setPassword(e.target.value)} className="auth-input ps-10 pe-10" required minLength={12} /><button type="button" onClick={() => setShowPassword(!showPassword)} className="absolute end-3 top-3 text-muted-foreground hover:text-foreground">{showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}</button></div></div>
            <Button type="submit" disabled={submitting} className="w-full bg-gradient-gold hover:opacity-90 text-primary-foreground font-semibold h-12">{submitting ? <div className="animate-spin h-5 w-5 border-2 border-white border-t-transparent rounded-full" /> : (isLogin ? t.login : t.signup)}</Button>
          </form>
          <p className="text-center text-muted-foreground text-sm mt-6">{isLogin ? t.noAccount : t.hasAccount}{" "}<button onClick={() => setIsLogin(!isLogin)} className="text-primary hover:underline font-medium">{isLogin ? t.signup : t.login}</button></p>
        </div>
      </motion.div>
    </div>
  );
};

export default Auth;
