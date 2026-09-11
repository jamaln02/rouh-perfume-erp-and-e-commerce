import { useState, useRef, useEffect } from "react";
import { Loader2 } from "lucide-react";

interface ImageWithLazyLoadProps {
  src: string;
  alt: string;
  className?: string;
  loading?: "lazy" | "eager";
}

export const ImageWithLazyLoad = ({ src, alt, className, loading = "lazy" }: ImageWithLazyLoadProps) => {
  const [displaySrc, setDisplaySrc] = useState(src);
  const [isLoaded, setIsLoaded] = useState(false);
  const [isError, setIsError] = useState(false);
  const imgRef = useRef<HTMLImageElement>(null);

  useEffect(() => {
    setDisplaySrc(src);
    setIsLoaded(false);
    setIsError(false);
  }, [src]);

  const handleError = () => {
    if (displaySrc !== "/placeholder.svg") {
      setDisplaySrc("/placeholder.svg");
      setIsError(true);
      setIsLoaded(true);
    }
  };

  return (
    <div className={`relative overflow-hidden ${className}`}>
      {!isLoaded && !isError && (
        <div className="absolute inset-0 flex items-center justify-center bg-muted animate-pulse">
          <Loader2 className="w-8 h-8 text-muted-foreground animate-spin" />
        </div>
      )}
      <img
        ref={imgRef}
        src={displaySrc}
        alt={alt}
        loading={loading}
        className={`w-full h-full object-cover transition-transform duration-700 group-hover:scale-110 transition-opacity duration-300 ${
          isLoaded ? 'opacity-100' : 'opacity-0'
        }`}
        onLoad={() => setIsLoaded(true)}
        onError={handleError}
      />
    </div>
  );
};
