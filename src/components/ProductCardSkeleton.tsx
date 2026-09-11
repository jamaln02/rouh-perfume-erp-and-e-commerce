import { Skeleton } from "@/components/ui/skeleton";

const ProductCardSkeleton = () => (
  <div className="rounded-2xl bg-card border border-border/50 overflow-hidden">
    <Skeleton className="w-full aspect-[3/4] rounded-none" />
    <div className="p-4 space-y-2">
      <Skeleton className="h-4 w-3/4" />
      <Skeleton className="h-3 w-1/2" />
      <Skeleton className="h-5 w-1/3 mt-2" />
    </div>
  </div>
);

export default ProductCardSkeleton;