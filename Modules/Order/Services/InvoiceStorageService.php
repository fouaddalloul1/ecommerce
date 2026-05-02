<?php

namespace Modules\Order\Services;

use Modules\Order\Traits\HandlesInvoiceStorage;

class InvoiceStorageService
{
    use HandlesInvoiceStorage;

    /**
     * Store invoice and return relative path.
     */
    public function storeInvoice(int $orderId, string $pdfContent): string
    {
        return $this->putInvoiceFile($orderId, $pdfContent);
    }

    /**
     * Return absolute path for mail attachment or other file operations.
     */
    public function getAbsolutePath(string $relativePath): string
    {
        return $this->absolutePath($relativePath);
    }

    /**
     * Remove an invoice file.
     */
    public function removeInvoice(string $relativePath): bool
    {
        return $this->deleteInvoiceFile($relativePath);
    }
}
