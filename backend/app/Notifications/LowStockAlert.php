<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $productName,
        public readonly string $productNameAr,
        public readonly int $currentStock,
        public readonly int $threshold
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Low Stock Alert - Rouh Perfumes')
            ->greeting('Dear Admin,')
            ->line("The following product is running low on stock:")
            ->line('')
            ->line("Product: {$this->productName} / {$this->productNameAr}")
            ->line("Current Stock: {$this->currentStock}")
            ->line("Threshold: {$this->threshold}")
            ->line('')
            ->action('Manage Stock', url('/admin/products'))
            ->line('Please restock this item soon to avoid stockouts.');
    }

    public function toArray($notifiable): array
    {
        return [
            'product_name' => $this->productName,
            'product_name_ar' => $this->productNameAr,
            'current_stock' => $this->currentStock,
            'threshold' => $this->threshold,
        ];
    }
}
