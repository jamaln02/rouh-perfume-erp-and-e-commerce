import { useState } from "react";
import { useLanguage } from "@/hooks/useLanguage";
import { withAuthHeaders } from "@/lib/auth";
import { Upload, FileSpreadsheet, CheckCircle, XCircle, Download } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { toast } from "sonner";

const BulkImport = () => {
  const { lang } = useLanguage();
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "");
  const [file, setFile] = useState<File | null>(null);
  const [uploading, setUploading] = useState(false);
  const [results, setResults] = useState<{ success: number; failed: number; errors: string[] } | null>(null);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFile = e.target.files?.[0];
    if (selectedFile && (selectedFile.type === "text/csv" || selectedFile.name.endsWith('.csv'))) {
      setFile(selectedFile);
      setResults(null);
    } else {
      toast.error(lang === "ar" ? "يرجى اختيار ملف CSV صالح" : "Please select a valid CSV file");
    }
  };

  const handleUpload = async () => {
    if (!file) return;

    setUploading(true);
    const formData = new FormData();
    formData.append('file', file);

    try {
      const response = await window.fetch(`${apiBaseUrl}/api/admin/products/bulk-import`, {
        method: 'POST',
        headers: withAuthHeaders({}),
        body: formData,
      });

      const data = await response.json();

      if (response.ok) {
        setResults({
          success: data.success || 0,
          failed: data.failed || 0,
          errors: data.errors || []
        });
        toast.success(lang === "ar" ? "تم رفع الملف بنجاح" : "File uploaded successfully");
      } else {
        toast.error(data.message || (lang === "ar" ? "فشل رفع الملف" : "Failed to upload file"));
      }
    } catch (error) {
      toast.error(lang === "ar" ? "حدث خطأ أثناء الرفع" : "Error uploading file");
    } finally {
      setUploading(false);
    }
  };

  const downloadTemplate = () => {
    const template = `name,name_ar,description,description_ar,price,stock,category_slug,fragrance,is_new,best_seller,featured,image_url
"Royal Amber","العنبر الملكي","A luxurious oriental fragrance with deep amber notes","عطر شرقي فاخر بنغمات عميقة من العنبر",150000,50,men,oud,true,true,true,
"Rose Eternelle","الوردة الأبدية","An elegant feminine rose scent","رائحة ورد أنثوية راقية",180000,30,women,floral,false,true,true,
`;
    const blob = new Blob([template], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'products_template.csv';
    a.click();
    window.URL.revokeObjectURL(url);
  };

  return (
    <div className="space-y-6 space-x-7">
      <h1 className="text-2xl font-display font-bold">
        {lang === "ar" ? "استيراد المنتجات بالجملة" : "Bulk Product Import"}
      </h1>

      <Card className="border-border">
        <CardHeader>
          <CardTitle className="text-base flex items-center gap-2">
            <FileSpreadsheet className="h-5 w-5" />
            {lang === "ar" ? "رفع ملف CSV" : "Upload CSV File"}
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center justify-between">
            <Button
              onClick={downloadTemplate}
              variant="outline"
              className="flex items-center gap-2"
            >
              <Download size={16} />
              {lang === "ar" ? "تحميل نموذج CSV" : "Download CSV Template"}
            </Button>
          </div>

          <div className="border-2 border-dashed border-border rounded-lg p-8 text-center">
            <input
              type="file"
              accept=".csv"
              onChange={handleFileChange}
              className="hidden"
              id="csv-upload"
            />
            <label
              htmlFor="csv-upload"
              className="cursor-pointer flex flex-col items-center gap-3"
            >
              <Upload size={48} className="text-muted-foreground" />
              <p className="text-sm text-muted-foreground">
                {lang === "ar" ? "اسحب ملف CSV هنا أو انقر للاختيار" : "Drag and drop CSV file here or click to select"}
              </p>
              {file && (
                <p className="text-sm font-medium text-primary">
                  {file.name}
                </p>
              )}
            </label>
          </div>

          <Button
            onClick={handleUpload}
            disabled={!file || uploading}
            className="w-full"
          >
            {uploading
              ? (lang === "ar" ? "جاري الرفع..." : "Uploading...")
              : (lang === "ar" ? "رفع الملف" : "Upload File")
            }
          </Button>
        </CardContent>
      </Card>

      {results && (
        <Card className="border-border">
          <CardHeader>
            <CardTitle className="text-base">
              {lang === "ar" ? "نتائج الاستيراد" : "Import Results"}
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="flex items-center gap-2 text-green-600">
                <CheckCircle size={20} />
                <span className="font-medium">
                  {results.success} {lang === "ar" ? "منتج تم بنجاح" : "products imported successfully"}
                </span>
              </div>
              {results.failed > 0 && (
                <div className="flex items-center gap-2 text-red-600">
                  <XCircle size={20} />
                  <span className="font-medium">
                    {results.failed} {lang === "ar" ? "فشل" : "failed"}
                  </span>
                </div>
              )}
            </div>

            {results.errors.length > 0 && (
              <div className="space-y-2">
                <h4 className="font-medium text-sm">
                  {lang === "ar" ? "الأخطاء:" : "Errors:"}
                </h4>
                <div className="bg-destructive/10 border border-destructive/20 rounded-lg p-3 max-h-40 overflow-y-auto">
                  {results.errors.map((error, i) => (
                    <p key={i} className="text-sm text-destructive">
                      {error}
                    </p>
                  ))}
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      )}
    </div>
  );
};

export default BulkImport;
