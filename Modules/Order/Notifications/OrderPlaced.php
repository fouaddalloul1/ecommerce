<?php

namespace Modules\Order\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Modules\Order\Models\Order;
use Modules\Order\DTOs\OrderMessage;

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
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $msg = new OrderMessage($this->order, $notifiable);
        return $this->buildMail($msg);
    }

    protected function buildMail(OrderMessage $msg): MailMessage
    {
        return (new MailMessage)
            ->subject("Order #{$msg->order->id} placed")
            ->greeting("Hello {$msg->notifiable->name},")
            ->line("We received your order #{$msg->order->id}.")
            ->action('View order', url("/orders/{$msg->order->id}"))
            ->line('Thank you for shopping with us.');
    }
}
