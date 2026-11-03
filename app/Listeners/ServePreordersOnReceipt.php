<?php

namespace App\Listeners;

use App\Events\StockReceived;
use App\Services\Orders\PreorderServing;

/**
 * Entrée en stock validée → précommandes servies tout de suite (pas de file :
 * l'employé voit le résultat sur la fiche de l'entrée). Seuls les courriels partent par la file.
 */
class ServePreordersOnReceipt
{
    public function __construct(private PreorderServing $serving) {}

    public function handle(StockReceived $event): void
    {
        $receipt = $event->receipt;

        $this->serving->serve($receipt->lots()->pluck('product_id'), $receipt, $receipt->notify_preorders);
    }
}
