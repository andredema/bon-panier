<?php

namespace App\Services\Billing;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Paiements (cahier 5.4) : Interac, comptant, débit (carte plus tard).
 * Rattachés à la commande, et à sa facture quand elle existe ; jamais plus que le solde dû.
 * Rapprochement Interac manuel : l'équipe saisit le virement reçu et sa référence.
 */
final class PaymentRecorder
{
    public function record(Order $order, string $method, int $cents, ?Carbon $paidAt, ?string $reference, ?User $user, ?string $note = null): Payment
    {
        $errors = [];
        $balance = $order->balanceCents();

        if ($order->status === OrderStatus::Cancelled && ! $order->invoice()->exists()) {
            $errors['amount'] = __('billing.errors.cancelled');
        }
        if (! in_array($method, Payment::METHODS, true)) {
            $errors['method'] = __('billing.errors.method');
        }
        if ($cents <= 0) {
            $errors['amount'] = __('billing.errors.amount');
        } elseif ($cents > $balance) {
            $errors['amount'] = __('billing.errors.over', ['balance' => Format::money(max(0, $balance))]);
        }
        if ($paidAt && $paidAt->isFuture()) {
            $errors['paid_at'] = __('billing.errors.future');
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return Payment::create([
            'order_id' => $order->id,
            'invoice_id' => $order->invoice()->value('id'),
            'method' => $method,
            'amount_cents' => $cents,
            'paid_at' => $paidAt ?? now(),
            'reference' => $reference ? mb_substr(trim($reference), 0, 120) : null,
            'user_id' => $user?->id,
            'note' => $note ? mb_substr(trim($note), 0, 255) : null,
        ]);
    }
}
