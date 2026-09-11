import { useState, useEffect } from "react";
import { useLanguage } from "@/hooks/useLanguage";
import { useAuth } from "@/hooks/useAuth";
import { useNavigate } from "react-router-dom";
import { withAuthHeaders } from "@/lib/auth";
import { Sparkles, Gift, TrendingUp, Crown } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { toast } from "sonner";
import { useStorefrontConfig } from "@/hooks/useStorefrontConfig";

const LoyaltyRewards = () => {
  const { t, lang } = useLanguage();
  const { user } = useAuth();
  const navigate = useNavigate();
  const [points, setPoints] = useState(0);
  const [loading, setLoading] = useState(true);
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "");
  const { config } = useStorefrontConfig();

  useEffect(() => {
    if (!user) return;

    fetch(`${apiBaseUrl}/api/profiles/${user.id}`, { headers: withAuthHeaders({ Accept: "application/json" }) })
      .then((res) => res.json())
      .then((data) => {
        setPoints(Number(data?.profile?.loyalty_points || 0));
        setLoading(false);
      })
      .catch(() => setLoading(false));
  }, [apiBaseUrl, user]);

  const unit = Math.max(1, Number(config.loyalty.redeem_points));
  const unitDiscount = Math.max(0, Number(config.loyalty.redeem_discount));
  const calculateDiscount = (pts: number) => Math.floor((pts / unit) * unitDiscount);

  const rewardUnits = [1, 2, 5];
  const rewards = rewardUnits.map((multiplier, index) => ({
    points: unit * multiplier,
    discount: unitDiscount * multiplier,
    icon: [Gift, TrendingUp, Crown][index],
    title: lang === "ar" ? `خصم ${(unitDiscount * multiplier).toLocaleString()} ل.س` : `${(unitDiscount * multiplier).toLocaleString()} SYP Discount`,
    description: lang === "ar" ? `استبدل ${(unit * multiplier).toLocaleString()} نقطة` : `Redeem ${(unit * multiplier).toLocaleString()} points`,
  }));

  const handleRedeem = (requiredPoints: number) => {
    if (points < requiredPoints) {
      toast.error(lang === "ar" ? "ليس لديك نقاط كافية" : "Not enough points");
      return;
    }
    navigate(`/checkout?loyalty_points=${requiredPoints}`);
  };

  if (!user) {
    return (
      <Card className="border-border">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Sparkles className="text-gold" />
            {lang === "ar" ? "نقاط الولاء" : "Loyalty Points"}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-muted-foreground text-sm">
            {lang === "ar" ? "سجّل دخولك للاستفادة من نقاط الولاء" : "Sign in to access loyalty rewards"}
          </p>
        </CardContent>
      </Card>
    );
  }

  if (!config.loyalty.enabled) {
    return (
      <Card className="border-border">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Sparkles className="text-gold" />
            {lang === "ar" ? "نقاط الولاء" : "Loyalty Points"}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-muted-foreground text-sm">
            {lang === "ar" ? "نظام نقاط الولاء غير مفعل حالياً." : "The loyalty program is currently disabled."}
          </p>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className="border-border">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Sparkles className="text-gold" />
          {lang === "ar" ? "نقاط الولاء" : "Loyalty Points"}
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {loading ? (
          <div className="animate-pulse h-20 bg-muted rounded-lg" />
        ) : (
          <>
            <div className="bg-gradient-to-r from-primary/15 to-primary/5 border border-primary/20 rounded-xl p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-xs text-muted-foreground mb-1">
                    {lang === "ar" ? "رصيد نقاطك" : "Your Points Balance"}
                  </p>
                  <p className="text-3xl font-bold text-primary">{points.toLocaleString()}</p>
                </div>
                <div className="text-right">
                  <p className="text-xs text-muted-foreground mb-1">
                    {lang === "ar" ? "قيمة الخصم المتاح" : "Available Discount"}
                  </p>
                  <p className="text-xl font-bold text-gold">{calculateDiscount(points).toLocaleString()} SYP</p>
                </div>
              </div>
            </div>

            <div className="space-y-2">
              <p className="text-sm font-semibold text-foreground">
                {lang === "ar" ? "استبدل نقاطك" : "Redeem Your Points"}
              </p>
              {rewards.map((reward) => {
                const Icon = reward.icon;
                const canRedeem = points >= reward.points;
                return (
                  <div
                    key={reward.points}
                    className={`flex items-center justify-between p-3 rounded-xl border transition-all ${
                      canRedeem
                        ? "border-border hover:border-gold/50 cursor-pointer"
                        : "border-border/50 opacity-50 cursor-not-allowed"
                    }`}
                  >
                    <div className="flex items-center gap-3">
                      <div className={`p-2 rounded-lg ${canRedeem ? "bg-gold/10 text-gold" : "bg-muted text-muted-foreground"}`}>
                        <Icon size={20} />
                      </div>
                      <div>
                        <p className="font-medium text-foreground">{reward.title}</p>
                        <p className="text-xs text-muted-foreground">{reward.description}</p>
                      </div>
                    </div>
                    <Button
                      size="sm"
                      disabled={!canRedeem}
                      onClick={() => void handleRedeem(reward.points)}
                      className={canRedeem ? "bg-gold hover:bg-gold-dark" : ""}
                    >
                      {lang === "ar" ? "استبدال" : "Redeem"}
                    </Button>
                  </div>
                );
              })}
            </div>

            <p className="text-xs text-muted-foreground text-center">
              {lang === "ar" ? `اكسب ${Number(config.loyalty.earn_points).toLocaleString()} نقطة مقابل كل ${Number(config.loyalty.earn_amount).toLocaleString()} ل.س من مشترياتك` : `Earn ${Number(config.loyalty.earn_points).toLocaleString()} point(s) for every ${Number(config.loyalty.earn_amount).toLocaleString()} SYP spent`}
            </p>
          </>
        )}
      </CardContent>
    </Card>
  );
};

export default LoyaltyRewards;
