<?php

namespace App\Events;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** Tout changement de statut d'une commande (après enregistrement). */
class OrderStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly ?OrderStatus $from,
        public readonly OrderStatus $to,
        public readonly ?User $user,
    ) {}
}
