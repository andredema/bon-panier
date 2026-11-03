<?php

namespace App\Services\Orders;

use App\Mail\OrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
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
    public function __construct(private Cart $cart, private PickupSlots $slots, private OrderWriter $writer) {}

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

            return $this->writer->create($summary, $customer, [
                'fulfilment' => $data['fulfilment'],
                'slot_start' => $slot['start'],
                'slot_end' => $slot['end'],
                'delivery_address' => $data['delivery_address'] ?? null,
                'payment_method' => $data['payment_method'],
                'notes' => $data['notes'] ?? null,
                'source' => Order::SOURCE_ONLINE,
            ]);
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
