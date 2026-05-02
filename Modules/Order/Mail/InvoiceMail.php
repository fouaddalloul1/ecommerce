<?php

namespace Modules\Order\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Modules\Order\Models\Order;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;
    public string $filePath;

    public function __construct(Order $order, ?string $filePath = null)
    {
        $this->order = $order;
        $this->filePath = $filePath;
    }


    public function build()
    {
        $mail = $this->subject("Invoice for Order #{$this->order->id}")
            ->view('order::emails.invoice_mail') // your mail view
            ->with(['order' => $this->order]);

        if ($this->filePath && file_exists($this->filePath)) {
            $mail->attach($this->filePath, [
                'as' => "invoice-{$this->order->id}.pdf",
                'mime' => 'application/pdf',
            ]);
        }
        return $mail;
    }
}
