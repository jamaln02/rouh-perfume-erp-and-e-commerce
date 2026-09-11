import { Minus, Plus } from "lucide-react";

interface QuantityControlProps {
  quantity: number;
  onDecrease: () => void;
  onIncrease: () => void;
  min?: number;
  max?: number;
  lang?: "ar" | "en";
}

export const QuantityControl = ({
  quantity,
  onDecrease,
  onIncrease,
  min = 1,
  max,
  lang = "ar"
}: QuantityControlProps) => {
  const canDecrease = quantity > min;
  const canIncrease = max === undefined || quantity < max;

  return (
    <div className="flex items-center gap-2 border border-border rounded-lg">
      <button
        onClick={onDecrease}
        disabled={!canDecrease}
        aria-label={lang === "ar" ? "إنقاص الكمية" : "Decrease quantity"}
        className="p-1.5 text-foreground hover:text-gold disabled:opacity-30 disabled:cursor-not-allowed"
      >
        <Minus size={14} />
      </button>
      <span className="text-sm font-medium text-foreground w-6 text-center">{quantity}</span>
      <button
        onClick={onIncrease}
        disabled={!canIncrease}
        aria-label={lang === "ar" ? "زيادة الكمية" : "Increase quantity"}
        className="p-1.5 text-foreground hover:text-gold disabled:opacity-30 disabled:cursor-not-allowed"
      >
        <Plus size={14} />
      </button>
    </div>
  );
};
