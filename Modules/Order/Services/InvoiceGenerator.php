<?php

namespace Modules\Order\Services;

use Modules\Order\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceGenerator
{
    public function generatePdf(Order $order): string
    {
        $html = view('order::emails.invoice', ['order' => $order])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        return $pdf->output();
    }
}
