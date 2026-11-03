<?php

namespace App\Services\Billing;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\Quantity;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Émission de la facture (cahier 5.4) : à la remise, ou à la confirmation pour les
 * commandes de gros si le réglage le prévoit. Une seule facture par commande.
 *
 * Tout est figé au moment de l'émission : lignes (avec les lots sortis), client,
 * coordonnées et numéros de taxes du commerce, taux. Les paiements déjà reçus
 * (virement Interac avant la remise) sont rattachés à la facture.
 */
final class InvoiceIssuer
{
    public function __construct(private DocumentNumbering $numbering) {}

    public function issue(Order $order, ?User $user = null, bool $email = true): Invoice
    {
        if ($existing = $order->invoice()->first()) {
            return $existing;
        }

        // Valeurs telles qu'enregistrées (défauts de la base compris) avant de les figer
        $order->refresh()->load(['customer', 'items.lots.lot', 'items.product']);

        $invoice = DB::transaction(function () use ($order, $user) {
            $issued = now();
            $dueDays = (int) Settings::get('invoice.due_days');
            $tpsRate = Settings::rate('tax.tps_rate');
            $tvqRate = Settings::rate('tax.tvq_rate');

            $invoice = Invoice::create([
                'number' => $this->numbering->next(DocumentNumbering::INVOICE, (int) $issued->format('Y')),
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'issued_at' => $issued,
                'due_on' => $dueDays > 0 ? $issued->copy()->addDays($dueDays)->toDateString() : $issued->toDateString(),
                'customer_snapshot' => [
                    'name' => $order->customer->name,
                    'phone' => $order->customer->phone,
                    'email' => $order->customer->email,
                    'address' => $order->delivery_address ?? $order->customer->address,
                ],
                'shop_snapshot' => [
                    'name' => Settings::get('shop.name'),
                    'address' => Settings::get('shop.address'),
                    'phone' => Settings::get('shop.phone'),
                    'email' => Settings::get('shop.email'),
                    'tps_number' => Settings::get('tax.tps_number'),
                    'tvq_number' => Settings::get('tax.tvq_number'),
                    'interac_email' => Settings::get('interac.email'),
                ],
                'tps_rate' => $tpsRate,
                'tvq_rate' => $tvqRate,
                'fulfilment' => $order->fulfilment,
                'slot_start' => $order->slot_start,
                'slot_end' => $order->slot_end,
                'payment_method' => $order->payment_method,
                'subtotal_cents' => $order->subtotal_cents,
                'taxable_cents' => $order->taxable_cents,
                'delivery_fee_cents' => $order->delivery_fee_cents,
                // Les frais de livraison étaient taxés si la TPS dépasse celle des seuls produits taxables
                'delivery_taxable' => $order->delivery_fee_cents > 0
                    && $order->tax_tps_cents !== Quantity::divRound($order->taxable_cents * $tpsRate, 100_000),
                'tax_tps_cents' => $order->tax_tps_cents,
                'tax_tvq_cents' => $order->tax_tvq_cents,
                'total_cents' => $order->total_cents,
                'user_id' => $user?->id,
            ]);

            foreach ($order->items as $i => $item) {
                $invoice->lines()->create([
                    'order_item_id' => $item->id,
                    'product_name' => $item->product_name,
                    'format_label' => $item->format_label,
                    'channel' => $item->channel->value,
                    'is_frozen' => (bool) $item->product?->is_frozen,
                    'qty' => $item->qty,
                    'qty_unit' => $item->qty_unit,
                    'unit_price_cents' => $item->unit_price_cents,
                    'line_total_cents' => $item->line_total_cents,
                    'is_taxable' => $item->is_taxable,
                    'lot_numbers' => $item->lots->map(fn ($l) => $l->lot->lot_number)->unique()->join(', ') ?: null,
                    'sort_order' => $i,
                ]);
            }

            Payment::where('order_id', $order->id)->whereNull('invoice_id')->update(['invoice_id' => $invoice->id]);

            return $invoice;
        });

        if ($email && $order->customer->email) {
            $this->email($invoice);
        }

        return $invoice;
    }

    /** Envoi (ou renvoi) de la facture PDF par courriel, par la file. */
    public function email(Invoice $invoice): void
    {
        $email = $invoice->customer_snapshot['email'] ?? null;
        if (! $email) {
            return;
        }

        Mail::to($email)->queue(new InvoiceMail($invoice));
        $invoice->update(['emailed_at' => now()]);
    }
}
