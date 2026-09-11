interface PriceDisplayProps {
  price: number;
  currency?: string;
  lang?: "ar" | "en";
  className?: string;
  size?: "sm" | "md" | "lg";
}

export const PriceDisplay = ({
  price,
  currency = "SYP",
  lang = "ar",
  className = "",
  size = "md"
}: PriceDisplayProps) => {
  const formatPrice = (price: number) =>
    new Intl.NumberFormat(lang === "ar" ? "ar-SY" : "en-SY").format(price);

  const sizeClasses = {
    sm: "text-sm",
    md: "text-lg",
    lg: "text-2xl"
  };

  return (
    <p className={`text-gold font-bold ${sizeClasses[size]} ${className}`}>
      {formatPrice(price)} <span className="text-xs font-normal text-muted-foreground">{currency}</span>
    </p>
  );
};
