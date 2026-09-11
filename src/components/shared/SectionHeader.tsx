import { ReactNode } from "react";

interface SectionHeaderProps {
  title: string;
  subtitle?: string;
  action?: ReactNode;
  className?: string;
}

export const SectionHeader = ({ title, subtitle, action, className = "" }: SectionHeaderProps) => {
  return (
    <div className={`flex items-center justify-between mb-10 ${className}`}>
      <div>
        <h2 className="font-display text-3xl font-bold text-gradient-gold">{title}</h2>
        {subtitle && <p className="text-muted-foreground mt-2">{subtitle}</p>}
      </div>
      {action && <div>{action}</div>}
    </div>
  );
};
