<?php

namespace App\Services\Orders;

use App\Enums\Channel;
use App\Enums\OrderStatus;
use App\Mail\OrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Support\Quantity;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Envoi de la commande (cahier des charges 3.4 et 3.5).
 *
 * Tout est revérifié au moment de l'envoi, produits verrouillés : disponibilités,
 * minimums, créneau. Si la disponibilité a changé depuis l'affichage du panier
 * (un autre client a commandé entre-temps), le client est prévenu et rien n'est créé.
 */
final class OrderPlacement
{
    public function __construct(private Cart $cart, private PickupSlots $slots) {}

    /**
     * @param  array<string, mixed>  $input  champs du formulaire de finalisation
     *
     * @throws ValidationException
     */
    public function place(array $input): Order
    {
        if ($this->cart->isEmpty()) {
            throw ValidationException::withMessages(['cart' => __('shop.checkout.empty')]);
        }

        $data = $this->validate($input);

        $order = DB::transaction(function () use ($data, $input) {
            // Verrou sur les produits du panier : deux commandes simultanées ne vendent pas le même stock
            $summary = $this->cart->summary();
            Product::whereIn('id', collect($summary['lines'])->pluck('product_id'))->lockForUpdate()->get();
            $summary = $this->cart->summary();

            if ($summary['lines'] === []) {
                throw ValidationException::withMessages(['cart' => __('shop.checkout.empty')]);
            }
            if ($summary['has_unavailable']) {
                throw ValidationException::withMessages(['cart' => __('shop.checkout.unavailable')]);
            }
            if (($input['cart_signature'] ?? null) !== $summary['signature']) {
                throw ValidationException::withMessages(['cart' => __('shop.checkout.changed')]);
            }

            $delivery = $data['fulfilment'] === Order::DELIVERY;
            if ($delivery && $summary['has_frozen'] && ! Settings::get('delivery.frozen')) {
                throw ValidationException::withMessages(['fulfilment' => __('shop.checkout.frozen_pickup_only')]);
            }

            $notBefore = $summary['latest_arrival'] ? Carbon::parse($summary['latest_arrival']) : null;
            $slot = $this->slots->find((string) $data['slot'], now(), $notBefore);
            if (! $slot) {
                throw ValidationException::withMessages(['slot' => __('shop.checkout.slot_gone')]);
            }

            $customer = Customer::firstOrNew(['email' => mb_strtolower($data['email'])]);
            $customer->fill(['name' => $data['name'], 'phone' => $data['phone'], 'consent_at' => now()]);
            if ($delivery) {
                $customer->address = $data['delivery_address'];
            }
            // Le type « gros » reste attribué par l'équipe (comptes de gros approuvés)
            $customer->type ??= 'particulier';
            $customer->save();

            $fee = $delivery ? (int) Settings::get('delivery.fee') : 0;
            $t = app(TaxCalculator::class)->totals($summary['totals']['subtotal'], $summary['totals']['taxable'], $fee, (bool) Settings::get('delivery.taxable'));

            $order = Order::create([
                'customer_id' => $customer->id,
                'channel' => collect($summary['lines'])->contains('channel', Channel::Wholesale->value) ? Channel::Wholesale : Channel::Retail,
                'status' => OrderStatus::Received,
                'fulfilment' => $data['fulfilment'],
                'slot_start' => $slot['start'],
                'slot_end' => $slot['end'],
                'delivery_address' => $delivery ? $data['delivery_address'] : null,
                'payment_method' => $data['payment_method'],
                'subtotal_cents' => $t['subtotal'],
                'taxable_cents' => $t['taxable'],
                'delivery_fee_cents' => $t['delivery'],
                'tax_tps_cents' => $t['tps'],
                'tax_tvq_cents' => $t['tvq'],
                'total_cents' => $t['total'],
                'has_preorder' => $summary['has_preorder'],
                'notes' => ($data['notes'] ?? null) ?: null,
                'consent_at' => now(),
            ]);
            $order->update(['number' => 'C-'.now()->format('Y').'-'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT)]);

            $formats = SaleFormat::with('product')->whereIn('id', collect($summary['lines'])->pluck('format_id'))->get()->keyBy('id');

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

            $order->history()->create(['from' => null, 'to' => OrderStatus::Received]);

            return $order;
        });

        $this->cart->clear();
        Mail::to($order->customer->email)->queue(new OrderConfirmation($order));

        return $order;
    }

    /** @return array<string, mixed> */
    private function validate(array $input): array
    {
        $fulfilments = [Order::PICKUP];
        if (Settings::get('delivery.enabled')) {
            $fulfilments[] = Order::DELIVERY;
        }
        $payments = [Order::PAY_INTERAC];
        if (Settings::get('payment.on_handover')) {
            $payments[] = Order::PAY_ON_HANDOVER;
        }

        return Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[0-9 ()+.\-]{10,}$/'],
            'email' => ['required', 'email', 'max:255'],
            'fulfilment' => ['required', 'in:'.implode(',', $fulfilments)],
            'delivery_address' => ['nullable', 'required_if:fulfilment,'.Order::DELIVERY, 'string', 'max:255'],
            'slot' => ['required', 'string'],
            'payment_method' => ['required', 'in:'.implode(',', $payments)],
            'notes' => ['nullable', 'string', 'max:500'],
            'consent' => ['accepted'],
        ], [], __('shop.checkout.attributes'))->validate();
    }
}
