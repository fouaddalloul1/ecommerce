<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Daily Sales Batch Size
    |--------------------------------------------------------------------------
    |
    | Number of raw order_items rows processed in one iteration. The value can
    | be tuned through benchmarking without changing the job implementation.
    |
    */
    'daily_sales_chunk_size' => (int) env('DAILY_SALES_CHUNK_SIZE', 500),

    /* Maximum number of ranked products written to the generated PDF. */
    'daily_sales_pdf_product_limit' => (int) env('DAILY_SALES_PDF_PRODUCT_LIMIT', 100),
];
