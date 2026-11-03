<?php

namespace App\Listeners;

use App\Enums\Channel;
use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Services\Billing\CreditNoteIssuer;
use App\Services\Billing\InvoiceIssuer;
use App\Support\Settings;

/**
 * Facturation au fil des statuts :
 * - « La remise génère la facture » (règle 7) ; pour le gros, dès la confirmation si
 *   le réglage le prévoit (question ouverte du cahier 5.4) ;
 * - une commande déjà facturée puis annulée reçoit une note de crédit du solde entier.
 */
class IssueInvoice
{
    public function __construct(private InvoiceIssuer $issuer, private CreditNoteIssuer $credits) {}

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;
        $atConfirmation = $event->to === OrderStatus::Confirmed
            && $order->channel === Channel::Wholesale
            && Settings::get('invoice.wholesale_issued_at') === 'confirmation';

        if ($event->to === OrderStatus::HandedOver || $atConfirmation) {
            $this->issuer->issue($order, $event->user);

            return;
        }

        if ($event->to === OrderStatus::Cancelled && ($invoice = $order->invoice()->with('lines')->first())) {
            $amounts = $invoice->lines->mapWithKeys(fn ($l) => [$l->id => $l->creditableCents()])->filter()->all();
            if ($amounts || $invoice->delivery_fee_cents > (int) $invoice->creditNotes()->sum('delivery_cents')) {
                $this->credits->issue($invoice, $amounts, true, __('billing.credit.cancelled', ['reason' => $order->cancel_reason]), $event->user);
            }
        }
    }
}
