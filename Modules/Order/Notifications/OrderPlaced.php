<?php
namespace Modules\Order\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Modules\Order\Models\Order;
use Modules\Order\Mail\InvoiceMail;
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

    public function toMail($notifiable)
    {
        $absolute = storage_path('app/' . ($this->order->invoice_path ?? ''));

        if (file_exists($absolute)) {
            return (new InvoiceMail($this->order, $absolute))->to($notifiable->email);
        }

        // fallback: simple confirmation with link to order
        $signedUrl = URL::temporarySignedRoute('orders.show', now()->addHours(24), ['order' => $this->order->id]);

        return (new MailMessage)
            ->subject("Order #{$this->order->id} placed")
            ->greeting("Hello {$notifiable->name},")
            ->line("We received your order #{$this->order->id}.")
            ->action('View order', $signedUrl);
    }
}
