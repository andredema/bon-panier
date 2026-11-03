<?php

namespace App\Services\Orders;

use App\Enums\Channel;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SaleFormat;
use App\Models\User;
use App\Support\Quantity;
use App\Support\Settings;

/**
 * Écrit une commande à partir d'un panier évalué (Cart::summaryFor) : en-tête, totaux,
 * lignes figées (une par partie en stock / en précommande) et premier statut.
 * Commun à la page de commande et à la saisie au comptoir. À appeler dans une transaction.
 */
final class OrderWriter
{
    public function __construct(private TaxCalculator $taxes) {}

    /**
     * @param  array<string, mixed>  $summary  résultat de Cart::summaryFor()
     * @param  array<string, mixed>  $attrs  fulfilment, slot_start, slot_end, payment_method, delivery_address?, notes?, source?, consent_at?
     */
    public function create(array $summary, Customer $customer, array $attrs, ?User $by = null): Order
    {
        $delivery = $attrs['fulfilment'] === Order::DELIVERY;
        $fee = $delivery ? (int) Settings::get('delivery.fee') : 0;
        $t = $this->taxes->totals($summary['totals']['subtotal'], $summary['totals']['taxable'], $fee, (bool) Settings::get('delivery.taxable'));

        $order = Order::create([
            'customer_id' => $customer->id,
            'channel' => collect($summary['lines'])->contains('channel', Channel::Wholesale->value) ? Channel::Wholesale : Channel::Retail,
            'source' => $attrs['source'] ?? Order::SOURCE_ONLINE,
            'created_by' => $by?->id,
            'status' => OrderStatus::Received,
            'fulfilment' => $attrs['fulfilment'],
            'slot_start' => $attrs['slot_start'],
            'slot_end' => $attrs['slot_end'],
            'delivery_address' => $delivery ? ($attrs['delivery_address'] ?? null) : null,
            'payment_method' => $attrs['payment_method'],
            'subtotal_cents' => $t['subtotal'],
            'taxable_cents' => $t['taxable'],
            'delivery_fee_cents' => $t['delivery'],
            'tax_tps_cents' => $t['tps'],
            'tax_tvq_cents' => $t['tvq'],
            'total_cents' => $t['total'],
            'has_preorder' => $summary['has_preorder'],
            'notes' => ($attrs['notes'] ?? null) ?: null,
            'consent_at' => $attrs['consent_at'] ?? now(),
        ]);
        $order->update(['number' => 'C-'.now()->format('Y').'-'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT)]);

        $formats = SaleFormat::whereIn('id', collect($summary['lines'])->pluck('format_id'))->get()->keyBy('id');

        foreach ($summary['lines'] as $line) {
            $format = $formats[$line['format_id']];
            foreach ($line['parts'] as $part) {
                $qty = Quantity::fromMilli($part['qty']);
                $order->items()->create([
                    'product_id' => $line['product_id'],
                    'sale_format_id' => $format->id,
                    'product_name' => $line['name'],
                    'format_label' => $line['label'],
                    'channel' => $line['channel'],
                    'is_by_weight' => $line['is_by_weight'],
                    'qty_unit' => $line['unit'],
                    'qty' => $qty,
                    'stock_qty' => Quantity::fromMilli($format->stockMilliFor($qty)),
                    'unit_price_cents' => $line['unit_price_cents'],
                    'line_total_cents' => TaxCalculator::lineTotal($line['unit_price_cents'], $qty),
                    'is_taxable' => $line['is_taxable'],
                    'is_preorder' => $part['preorder'],
                    'expected_on' => $part['preorder'] ? $line['expected_on'] : null,
                ]);
            }
        }

        $order->history()->create(['from' => null, 'to' => OrderStatus::Received, 'user_id' => $by?->id]);

        return $order;
    }
}
