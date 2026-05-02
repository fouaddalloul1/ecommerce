<?php

namespace Modules\Order\Traits;

use Illuminate\Support\Facades\Storage;

trait HandlesInvoiceStorage
{
    protected string $disk = 'local';
    protected string $baseDir = 'invoices';

    /**
     * Store invoice content and return the relative path.
     */
    protected function putInvoiceFile(int $orderId, string $content): string
    {
        $path = "{$this->baseDir}/{$orderId}.pdf";

        // Ensure directory exists
        Storage::disk($this->disk)->makeDirectory($this->baseDir);

        Storage::disk($this->disk)->put($path, $content);

        return $path;
    }

    /**
     * Return absolute filesystem path for a relative storage path.
     */
    protected function absolutePath(string $relativePath): string
    {
        return Storage::disk($this->disk)->path($relativePath);
    }

    /**
     * Check existence.
     */
    protected function exists(string $relativePath): bool
    {
        return Storage::disk($this->disk)->exists($relativePath);
    }

    /**
     * Delete invoice file if exists.
     */
    protected function deleteInvoiceFile(string $relativePath): bool
    {
        if ($this->exists($relativePath)) {
            return Storage::disk($this->disk)->delete($relativePath);
        }
        return false;
    }
}
