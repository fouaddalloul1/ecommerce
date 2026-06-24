<?php

namespace Modules\Order\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Modules\Order\DTOs\InvoiceEmailMessage;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public InvoiceEmailMessage $msg;

    public function __construct(InvoiceEmailMessage $msg)
    {
        $this->msg = $msg;
    }

    public function build()
    {
        return $this
            ->subject("Invoice for Order #{$this->msg->order->id}")

            /*
             * This template uses Laravel Markdown mail components,
             * so it must be rendered with markdown(), not view().
             */
            ->markdown('order::emails.invoice-email')

            ->attach($this->msg->pdfPath, [
                'as' => "invoice-{$this->msg->order->id}.pdf",
                'mime' => 'application/pdf',
            ]);
    }
}
