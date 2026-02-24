<?php

namespace App\Domain\Order\Interfaces;

interface StockServiceInterface
{
    /**
     * Validate stock availability and deduct quantities. Throws on insufficient stock.
     */
    public function validateAndDeduct(array $items): void;

    /**
     * Restore stock quantities (e.g. on order cancellation).
     */
    public function restore(array $items): void;
}
