<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/** Commande remise au client, stock sorti : la facture sera émise à l'écoute de cet événement (étape 7). */
class OrderHandedOver
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
