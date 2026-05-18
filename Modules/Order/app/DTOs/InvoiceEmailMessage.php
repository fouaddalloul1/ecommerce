<?php

namespace Modules\Order\DTOs;

use Modules\Order\Models\Order;

class InvoiceEmailMessage
{
    public Order $order;
    public string $pdfPath;

    public function __construct(Order $order, string $pdfPath)
    {
        $this->order = $order;
        $this->pdfPath = $pdfPath;
    }
}
