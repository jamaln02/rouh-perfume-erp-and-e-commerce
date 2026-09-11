<?php

namespace App\Services;

class WhatsAppNotificationService
{
    private string $phoneNumber;

    public function __construct()
    {
        $this->phoneNumber = trim((string) config('rouh.contact.whatsapp_number', env('ROUH_WHATSAPP_NUMBER', '')));
    }

    /**
     * Send order confirmation via WhatsApp
     */
    public function sendOrderConfirmation(
        string $customerPhone,
        string $orderNumber,
        float $total,
        string $customerName,
        array $items,
        string $city,
        string $language = 'ar'
    ): string {
        // Clean phone number (remove +963 if present, add country code)
        $cleanPhone = $this->cleanPhoneNumber($customerPhone);
        
        // Create the message
        $message = $this->formatOrderConfirmationMessage(
            $orderNumber,
            $total,
            $customerName,
            $items,
            $city,
            $language
        );
        
        // Generate WhatsApp link
        $whatsappLink = "https://wa.me/{$cleanPhone}?text=" . urlencode($message);
        
        return $whatsappLink;
    }

    /**
     * Format order confirmation message
     */
    private function formatOrderConfirmationMessage(
        string $orderNumber,
        float $total,
        string $customerName,
        array $items,
        string $city,
        string $language
    ): string {
        if ($language === 'ar') {
            $message = "🎉 *تم تأكيد طلبك!* 🎉\n\n";
            $message .= "مرحباً {$customerName}،\n\n";
            $message .= "*رقم الطلب:* #{$orderNumber}\n";
            $message .= "*المدينة:* {$city}\n\n";
            $message .= "*المنتجات:*\n";
            
            foreach ($items as $item) {
                $message .= "• {$item['product_name']}";
                if (!empty($item['size'])) {
                    $message .= " ({$item['size']})";
                }
                $message .= " × {$item['quantity']}\n";
            }
            
            $message .= "\n*الإجمالي:* {$total} ل.س\n\n";
            $message .= "شكراً لتسوقك مع روح للعطور! 💐\n";
            $message .= "سيتم التواصل معك قريباً لتأكيد التوصيل.";
        } else {
            $message = "🎉 *Order Confirmed!* 🎉\n\n";
            $message .= "Hello {$customerName},\n\n";
            $message .= "*Order Number:* #{$orderNumber}\n";
            $message .= "*City:* {$city}\n\n";
            $message .= "*Items:*\n";
            
            foreach ($items as $item) {
                $message .= "• {$item['product_name']}";
                if (!empty($item['size'])) {
                    $message .= " ({$item['size']})";
                }
                $message .= " × {$item['quantity']}\n";
            }
            
            $message .= "\n*Total:* {$total} SYP\n\n";
            $message .= "Thank you for shopping with Rouh Perfumes! 💐\n";
            $message .= "We will contact you soon to confirm delivery.";
        }
        
        return $message;
    }

    /**
     * Clean phone number to international format
     */
    private function cleanPhoneNumber(string $phone): string
    {
        // Remove any non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove leading 0 if present
        $phone = ltrim($phone, '0');
        
        // Add Syria country code if not present
        if (!str_starts_with($phone, '963')) {
            $phone = '963' . $phone;
        }
        
        return $phone;
    }

    /**
     * Send order status update via WhatsApp
     */
    public function sendOrderStatusUpdate(
        string $customerPhone,
        string $orderNumber,
        string $status,
        string $customerName,
        string $language = 'ar'
    ): string {
        $cleanPhone = $this->cleanPhoneNumber($customerPhone);
        
        $statusMessages = [
            'confirmed' => [
                'ar' => '✅ تم تأكيد طلبك وجاري تجهيزه',
                'en' => '✅ Your order has been confirmed and is being prepared'
            ],
            'shipped' => [
                'ar' => '🚚 تم شحن طلبك وهو في الطريق إليك',
                'en' => '🚚 Your order has been shipped and is on its way'
            ],
            'delivered' => [
                'ar' => '📦 تم تسليم طلبك بنجاح',
                'en' => '📦 Your order has been delivered successfully'
            ],
            'cancelled' => [
                'ar' => '❌ تم إلغاء طلبك',
                'en' => '❌ Your order has been cancelled'
            ],
        ];

        $statusMessage = $statusMessages[$status][$language] ?? $statusMessages[$status]['ar'];
        
        if ($language === 'ar') {
            $message = "*تحديث حالة الطلب #{$orderNumber}*\n\n";
            $message .= "مرحباً {$customerName}،\n\n";
            $message .= "{$statusMessage}\n\n";
            $message .= "شكراً لتسوقك مع روح للعطور! 💐";
        } else {
            $message = "*Order Status Update #{$orderNumber}*\n\n";
            $message .= "Hello {$customerName},\n\n";
            $message .= "{$statusMessage}\n\n";
            $message .= "Thank you for shopping with Rouh Perfumes! 💐";
        }
        
        $whatsappLink = "https://wa.me/{$cleanPhone}?text=" . urlencode($message);
        
        return $whatsappLink;
    }
}
