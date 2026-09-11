<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $orderNumber,
        public readonly string $status,
        public readonly string $customerName
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $statusMessages = [
            'confirmed' => 'Your order has been confirmed and is being prepared.',
            'shipped' => 'Your order has been shipped and is on its way!',
            'delivered' => 'Your order has been delivered. Enjoy your perfumes!',
            'cancelled' => 'Your order has been cancelled.',
        ];

        $message = $statusMessages[$this->status] ?? "Your order status has been updated to: {$this->status}";

        return (new MailMessage)
            ->subject("Order Status Update - #{$this->orderNumber}")
            ->greeting("Dear {$this->customerName},")
            ->line($message)
            ->line("Order #{$this->orderNumber}")
            ->action('Track Your Order', url("/track"))
            ->line('Thank you for choosing Rouh Perfumes!');
    }

    public function toArray($notifiable): array
    {
        return [
            'order_number' => $this->orderNumber,
            'status' => $this->status,
            'customer_name' => $this->customerName,
        ];
    }
}
