<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $orderNumber,
        public readonly float $total,
        public readonly string $customerName,
        public readonly array $items
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Order Confirmed - Rouh Perfumes')
            ->greeting("Dear {$this->customerName},")
            ->line("Thank you for your order #{$this->orderNumber}!")
            ->line('Your order has been confirmed and is being processed.')
            ->line('Order Details:')
            ->line('---')
            ->when(!empty($this->items), function ($message) {
                foreach ($this->items as $item) {
                    $message->line("• {$item['name']} ({$item['quantity']}x) - {$item['price']} SYP");
                }
            })
            ->line('---')
            ->line("Total: {$this->total} SYP")
            ->line('We will notify you when your order ships.')
            ->action('Track Your Order', url("/track"))
            ->line('Thank you for choosing Rouh Perfumes!');
    }

    public function toArray($notifiable): array
    {
        return [
            'order_number' => $this->orderNumber,
            'total' => $this->total,
            'customer_name' => $this->customerName,
            'items' => $this->items,
        ];
    }
}
