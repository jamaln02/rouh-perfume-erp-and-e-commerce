import { ReactNode } from "react";

interface FilterButtonProps {
  isActive: boolean;
  onClick: () => void;
  children: ReactNode;
  className?: string;
}

export const FilterButton = ({ isActive, onClick, children, className = "" }: FilterButtonProps) => {
  return (
    <button
      onClick={onClick}
      className={`text-start text-sm px-4 py-2.5 rounded-xl transition-all duration-200 ${
        isActive
          ? "bg-gold text-accent-foreground font-medium shadow-gold"
          : "text-muted-foreground hover:text-foreground hover:bg-muted"
      } ${className}`}
    >
      {children}
    </button>
  );
};
