import { ReactNode } from "react";

interface ProductActionProps {
  onClick: (e: React.MouseEvent) => void;
  children: ReactNode;
  isActive?: boolean;
  title?: string;
  className?: string;
}

export const ProductAction = ({ onClick, children, isActive = false, title, className = "" }: ProductActionProps) => {
  const baseClasses = "p-3 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-gold focus:ring-offset-2";
  const stateClasses = isActive
    ? "bg-gold text-accent-foreground shadow-gold"
    : "bg-card/90 backdrop-blur text-foreground hover:bg-gold hover:text-accent-foreground";
  
  return (
    <button
      onClick={onClick}
      title={title}
      className={`${baseClasses} ${stateClasses} ${className}`}
    >
      {children}
    </button>
  );
};
