<?php

namespace App\Events;

use App\Models\StockReceipt;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Entrée en stock validée et enregistrée. Le service automatique des précommandes
 * (étape 5) écoute cet événement.
 */
class StockReceived
{
    use Dispatchable;

    public function __construct(public readonly StockReceipt $receipt) {}
}
