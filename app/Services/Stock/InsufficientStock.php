<?php

namespace App\Services\Stock;

use App\Models\Product;
use RuntimeException;

class InsufficientStock extends RuntimeException
{
    public function __construct(public readonly Product $product, public readonly int $requestedMilli, public readonly int $availableMilli)
    {
        parent::__construct("Stock insuffisant pour {$product->name}.");
    }
}
