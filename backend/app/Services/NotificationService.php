<?php

namespace App\Services;

use App\Notifications\OrderConfirmation;
use App\Notifications\OrderStatusUpdate;
use App\Notifications\LowStockAlert;
use App\Models\User;

class NotificationService
{
    /**
     * Send order confirmation email to customer
     */
    public function sendOrderConfirmation(
        string $email,
        string $orderNumber,
        float $total,
        string $customerName,
        array $items
    ): void {
        try {
            // Create a temporary user-like object for notification
            $notifiable = new class($email) {
                public function __construct(public readonly string $email) {}
                public function routeNotificationForMail(): string { return $this->email; }
            };

            $notifiable->notify(new OrderConfirmation($orderNumber, $total, $customerName, $items));
        } catch (\Exception $e) {
            // Log error but don't fail the process
            \Log::error('Failed to send order confirmation email: ' . $e->getMessage());
        }
    }

    /**
     * Send order status update email to customer
     */
    public function sendOrderStatusUpdate(
        string $email,
        string $orderNumber,
        string $status,
        string $customerName
    ): void {
        $notifiable = new class($email) {
            public function __construct(public readonly string $email) {}
            public function routeNotificationForMail(): string { return $this->email; }
        };

        $notifiable->notify(new OrderStatusUpdate($orderNumber, $status, $customerName));
    }

    /**
     * Send low stock alert to admin users
     */
    public function sendLowStockAlert(
        string $productName,
        string $productNameAr,
        int $currentStock,
        int $threshold = 5
    ): void {
        // Get all admin users
        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {
            $admin->notify(new LowStockAlert($productName, $productNameAr, $currentStock, $threshold));
        }
    }

    /**
     * Check and send low stock alerts for products
     */
    public function checkAndAlertLowStock(): void
    {
        $products = \DB::table('products')
            ->where('stock', '<=', 5)
            ->where('stock', '>', 0)
            ->get();

        foreach ($products as $product) {
            $this->sendLowStockAlert(
                $product->name,
                $product->name_ar,
                $product->stock,
                5
            );
        }
    }
}
