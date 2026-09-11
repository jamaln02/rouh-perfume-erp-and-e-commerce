import { useState } from "react";
import { useLanguage } from "@/hooks/useLanguage";
import { Share2, Facebook, Twitter, Linkedin, Link as LinkIcon, Check } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { toast } from "sonner";

interface SocialShareProps {
  url: string;
  title: string;
  description?: string;
}

const SocialShare = ({ url, title, description }: SocialShareProps) => {
  const { lang } = useLanguage();
  const [copied, setCopied] = useState(false);

  const shareUrl = encodeURIComponent(url);
  const shareTitle = encodeURIComponent(title);
  const shareDescription = encodeURIComponent(description || title);

  const shareLinks = {
    facebook: `https://www.facebook.com/sharer/sharer.php?u=${shareUrl}&title=${shareTitle}`,
    twitter: `https://twitter.com/intent/tweet?url=${shareUrl}&text=${shareTitle}`,
    linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${shareUrl}`,
    whatsapp: `https://wa.me/?text=${shareTitle}%20${shareUrl}`,
  };

  const handleShare = async (platform: string) => {
    if (platform === 'native') {
      if (navigator.share) {
        try {
          await navigator.share({
            title,
            url,
            text: description,
          });
        } catch (error) {
          // User dismissed the native share sheet — no action needed.
        }
      } else {
        toast.error(lang === "ar" ? "المشاركة غير مدعومة في هذا المتصفح" : "Sharing not supported in this browser");
      }
    } else {
      window.open(shareLinks[platform as keyof typeof shareLinks], '_blank', 'width=600,height=400');
    }
  };

  const copyToClipboard = async () => {
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      toast.success(lang === "ar" ? "تم نسخ الرابط" : "Link copied");
      setTimeout(() => setCopied(false), 2000);
    } catch (error) {
      toast.error(lang === "ar" ? "فشل نسخ الرابط" : "Failed to copy link");
    }
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" size="sm" className="gap-2">
          <Share2 size={16} />
          {lang === "ar" ? "مشاركة" : "Share"}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-48">
        {navigator.share && (
          <DropdownMenuItem onClick={() => handleShare('native')} className="gap-2">
            <Share2 size={16} />
            {lang === "ar" ? "مشاركة..." : "Share..."}
          </DropdownMenuItem>
        )}
        <DropdownMenuItem onClick={() => handleShare('facebook')} className="gap-2">
          <Facebook size={16} className="text-blue-600" />
          Facebook
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => handleShare('twitter')} className="gap-2">
          <Twitter size={16} className="text-sky-500" />
          Twitter
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => handleShare('linkedin')} className="gap-2">
          <Linkedin size={16} className="text-blue-700" />
          LinkedIn
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => handleShare('whatsapp')} className="gap-2">
          <Share2 size={16} className="text-green-500" />
          WhatsApp
        </DropdownMenuItem>
        <DropdownMenuItem onClick={copyToClipboard} className="gap-2">
          {copied ? <Check size={16} className="text-green-500" /> : <LinkIcon size={16} />}
          {copied ? (lang === "ar" ? "تم النسخ" : "Copied") : (lang === "ar" ? "نسخ الرابط" : "Copy Link")}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
};

export default SocialShare;
