<?php

namespace Modules\Order\Services;

use Modules\Order\Models\Order;
use Modules\Order\Mail\InvoiceMail;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Log;
use Throwable;

class InvoiceMailerService
{
    protected InvoiceStorageService $storage;
    protected Mailer $mailer;

    public function __construct(InvoiceStorageService $storage, Mailer $mailer)
    {
        $this->storage = $storage;
        $this->mailer = $mailer;
    }

    /**
     * Send invoice email. Accepts relative storage path.
     */
    public function sendInvoice(Order $order, string $relativePath): void
    {
        try {
            $absolute = $this->storage->getAbsolutePath($relativePath);

            if (! file_exists($absolute)) {
                Log::error('Invoice file missing when attempting to email', [
                    'order_id' => $order->id,
                    'path' => $absolute,
                ]);
                return;
            }

            $mailable = new InvoiceMail($order, $absolute);

            $this->mailer->to($order->user->email)->send($mailable);
        } catch (Throwable $e) {
            Log::error('Failed to send invoice email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
