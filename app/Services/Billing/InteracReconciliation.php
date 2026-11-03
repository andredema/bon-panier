<?php

namespace App\Services\Billing;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * Virements Interac attendus : commandes payables par Interac (non annulées, ou annulées
 * mais déjà facturées) dont le solde n'est pas réglé. Chaque commande reçoit ->due (cents).
 */
final class InteracReconciliation
{
    /** @return Collection<int, Order> */
    public function pending(): Collection
    {
        return Order::where('payment_method', Order::PAY_INTERAC)
            ->where(fn ($q) => $q->where('status', '!=', OrderStatus::Cancelled->value)->orWhereHas('invoice'))
            ->with(['customer', 'invoice.creditNotes'])
            ->withSum('payments', 'amount_cents')
            ->orderBy('created_at')
            ->get()
            ->each(fn (Order $o) => $o->due = ($o->invoice
                ? $o->invoice->total_cents - (int) $o->invoice->creditNotes->sum('total_cents')
                : $o->total_cents) - (int) $o->payments_sum_amount_cents)
            ->filter(fn (Order $o) => $o->due > 0)
            ->values();
    }
}
