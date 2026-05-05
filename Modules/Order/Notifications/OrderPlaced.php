<?php
namespace Modules\Order\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Modules\Order\Models\Order;

class OrderPlaced extends Notification
{
    use Queueable;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function via($notifiable)
    {
        return ['mail']; // add 'database', 'broadcast' etc. if needed
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order #{$this->order->id} placed")
            ->greeting("Hello {$notifiable->name},")
            ->line("We received your order #{$this->order->id}.")
            ->action('View order', url("/orders/{$this->order->id}"))
            ->line('Thank you for shopping with us.');
    }
}
