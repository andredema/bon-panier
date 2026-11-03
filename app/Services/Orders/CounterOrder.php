<?php

namespace App\Services\Orders;

use App\Mail\OrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Models\User;
use App\Services\Billing\PaymentRecorder;
use App\Support\Quantity;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Commande saisie par l'équipe, au comptoir ou au téléphone (cahier 4.2).
 * Mêmes prix, pas, minimums, réservations et précommandes que la page en ligne ;
 * le créneau est libre et la vente peut être remise tout de suite.
 */
final class CounterOrder
{
    public function __construct(
        private Cart $cart,
        private OrderWriter $writer,
        private OrderWorkflow $workflow,
        private PaymentRecorder $payments,
    ) {}

    /** Lignes du formulaire → [id de format => quantité], dans l'ordre. */
    public function rawLines(array $lines): array
    {
        $raw = [];
        $errors = [];

        foreach (array_values($lines) as $i => $line) {
            $format = SaleFormat::with(['product.stockUnit', 'unit'])->find((int) ($line['format_id'] ?? 0));
            if (! $format) {
                $errors["lines.{$i}.format_id"] = __('shop.cart.errors.unavailable_format');

                continue;
            }
            try {
                $milli = $this->cart->validateLine($format, (string) ($line['qty'] ?? ''));
                // Deux lignes du même format s'additionnent
                $previous = isset($raw[$format->id]) ? Quantity::toMilli($raw[$format->id]) : 0;
                $raw[$format->id] = Quantity::fromMilli($previous + $milli);
            } catch (CartException $e) {
                $errors["lines.{$i}.qty"] = $format->product->name.' : '.$e->getMessage();
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $raw;
    }

    /** Évaluation pour l'aperçu des totaux (aucune écriture). */
    public function preview(array $lines): array
    {
        return $this->cart->summaryFor($this->rawLines($lines));
    }

    /** @throws ValidationException|WorkflowException */
    public function place(array $input, User $user): Order
    {
        $data = $this->validate($input);
        $raw = $this->rawLines($data['lines']);
        $handOver = (bool) ($data['hand_over'] ?? false);

        $order = DB::transaction(function () use ($data, $raw, $user, $handOver) {
            $summary = $this->cart->summaryFor($raw);
            Product::whereIn('id', collect($summary['lines'])->pluck('product_id'))->lockForUpdate()->get();
            $summary = $this->cart->summaryFor($raw);

            if ($summary['has_unavailable']) {
                throw ValidationException::withMessages(['lines' => __('orders.errors.unavailable')]);
            }
            if ($handOver && $summary['has_preorder']) {
                throw ValidationException::withMessages(['hand_over' => __('orders.errors.hand_over_preorder')]);
            }

            $customer = filled($data['email'] ?? null)
                ? Customer::firstOrNew(['email' => mb_strtolower($data['email'])])
                : (Customer::whereNull('email')->where('phone', $data['phone'])->first() ?? new Customer);
            $customer->fill(['name' => $data['name'], 'phone' => $data['phone'], 'consent_at' => now()]);
            $customer->type ??= 'particulier';
            $customer->save();

            $start = Carbon::createFromFormat('Y-m-d\TH:i', $data['slot']);

            return $this->writer->create($summary, $customer, [
                'fulfilment' => $data['fulfilment'],
                'slot_start' => $start,
                'slot_end' => $start->copy()->addHour(),
                'delivery_address' => $data['delivery_address'] ?? null,
                'payment_method' => $data['payment_method'],
                'notes' => $data['notes'] ?? null,
                'source' => Order::SOURCE_COUNTER,
            ], $user);
        });

        if ($handOver) {
            // La commande existe déjà : en cas d'échec de la remise, on la garde et on le signale
            try {
                $this->workflow->handOverNow($order, $user);
                // Vente au comptoir encaissée sur-le-champ : le paiement règle la facture émise à la remise
                if (! empty($data['collected'])) {
                    $this->payments->record($order->fresh(), $data['collected'], $order->fresh()->balanceCents(), null, null, $user);
                }
            } catch (WorkflowException $e) {
                session()->flash('warnings', [$e->getMessage()]);
            }
        } elseif (($data['send_mail'] ?? false) && $order->customer->email) {
            Mail::to($order->customer->email)->queue(new OrderConfirmation($order));
        }

        return $order;
    }

    private function validate(array $input): array
    {
        $fulfilments = [Order::PICKUP];
        if (Settings::get('delivery.enabled')) {
            $fulfilments[] = Order::DELIVERY;
        }

        return Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[0-9 ()+.\-]{10,}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'consent' => ['accepted'],
            'lines' => ['required', 'array', 'min:1'],
            'fulfilment' => ['required', 'in:'.implode(',', $fulfilments)],
            'delivery_address' => ['nullable', 'required_if:fulfilment,'.Order::DELIVERY, 'string', 'max:255'],
            'slot' => ['required', 'date_format:Y-m-d\TH:i'],
            'payment_method' => ['required', 'in:'.Order::PAY_INTERAC.','.Order::PAY_ON_HANDOVER],
            'notes' => ['nullable', 'string', 'max:500'],
            'hand_over' => ['boolean'],
            'collected' => ['nullable', 'in:'.implode(',', Payment::METHODS)],
            'send_mail' => ['boolean'],
        ], ['lines.required' => __('orders.errors.no_lines'), 'lines.min' => __('orders.errors.no_lines'), 'slot.date_format' => __('orders.errors.slot')],
            __('shop.checkout.attributes'))->validate();
    }
}
