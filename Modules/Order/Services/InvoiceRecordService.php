<?php

namespace Modules\Order\Services;

use Modules\Order\Models\Order;
use Illuminate\Support\Carbon;

class InvoiceRecordService
{
    /**
     * Check if invoice already generated.
     */
    public function isGenerated(Order $order): bool
    {
        return (bool) $order->invoice_generated_at;
    }

    /**
     * Mark order as having an invoice and save the path.
     */
    public function markGenerated(Order $order, string $relativePath): void
    {
        $order->update([
            'invoice_path' => $relativePath,
            'invoice_generated_at' => Carbon::now(),
        ]);
    }
}
