<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Events\OrderHandedOver;
use App\Mail\OrderCancelled;
use App\Mail\OrderReady;
use App\Models\Order;
use App\Models\User;
use App\Services\Stock\FefoAllocator;
use App\Services\Stock\InsufficientStock;
use App\Support\Format;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Statuts de commande (règle 7, cahier 5.5) :
 * recue → confirmee → en_preparation → prete → remise, ou annulee avant la remise.
 *
 * - Une précommande non servie bloque la commande à « Confirmée » (cahier 5.2).
 * - La remise sort le stock des lots en FEFO et garde le lot de chaque ligne (règle 6),
 *   puis annonce la remise (la facture arrive à l'étape 7).
 * - L'annulation exige un motif ; le stock retenu redevient disponible et les
 *   précommandes en attente du produit sont servies si possible.
 * - Chaque changement est journalisé (qui, quand) ; « Prête » et « Annulée » avisent le client.
 */
final class OrderWorkflow
{
    public function __construct(private FefoAllocator $fefo, private PreorderServing $preorders) {}

    /** Passe à l'étape suivante du parcours (bouton « Confirmer », « Préparer »…). */
    public function advance(Order $order, ?User $user): Order
    {
        $next = $order->status->next() ?? throw new WorkflowException(__('orders.errors.final'));

        return $this->transition($order, $next, $user);
    }

    public function transition(Order $order, OrderStatus $to, ?User $user, ?string $note = null, bool $notify = true): Order
    {
        $from = $order->status;
        $order->loadMissing('customer');

        if ($to === OrderStatus::Cancelled) {
            return $this->cancel($order, $user, (string) $note);
        }
        if ($from->next() !== $to) {
            throw new WorkflowException(__('orders.errors.transition', ['from' => $from->label(), 'to' => $to->label()]));
        }
        if ($to->needsPreordersServed() && $order->hasPendingPreorder()) {
            throw new WorkflowException(__('orders.errors.pending_preorder'));
        }

        DB::transaction(function () use ($order, $from, $to, $user, $note) {
            if ($to === OrderStatus::HandedOver) {
                $this->takeStock($order);
                $order->handed_over_at = now();
            }
            $order->status = $to;
            $order->save();
            $order->history()->create(['from' => $from, 'to' => $to, 'user_id' => $user?->id, 'note' => $note]);
        });

        if ($to === OrderStatus::Ready && $notify && $order->customer->email) {
            Mail::to($order->customer->email)->queue(new OrderReady($order));
        }
        if ($to === OrderStatus::HandedOver) {
            OrderHandedOver::dispatch($order);
        }

        return $order;
    }

    /** Pour une vente au comptoir : enchaîne les étapes jusqu'à la remise, sans aviser le client présent. */
    public function handOverNow(Order $order, ?User $user): Order
    {
        while ($order->status->next()) {
            $this->transition($order, $order->status->next(), $user, __('orders.counter.immediate'), notify: false);
        }

        return $order;
    }

    private function cancel(Order $order, ?User $user, string $reason): Order
    {
        if (! $order->status->reservesStock()) {
            throw new WorkflowException(__('orders.errors.cannot_cancel', ['status' => $order->status->label()]));
        }
        if (trim($reason) === '') {
            throw new WorkflowException(__('orders.errors.reason'));
        }

        $from = $order->status;
        DB::transaction(function () use ($order, $from, $user, $reason) {
            $order->update(['status' => OrderStatus::Cancelled, 'cancel_reason' => mb_substr(trim($reason), 0, 255)]);
            $order->history()->create(['from' => $from, 'to' => OrderStatus::Cancelled, 'user_id' => $user?->id, 'note' => mb_substr(trim($reason), 0, 255)]);
        });

        // Le stock retenu par cette commande peut servir les précommandes qui attendent
        $this->preorders->serve($order->items()->pluck('product_id'), null, true);

        if ($order->customer->email && $order->source !== Order::SOURCE_COUNTER) {
            Mail::to($order->customer->email)->queue(new OrderCancelled($order));
        }

        return $order;
    }

    /** Sortie FEFO des lots pour chaque ligne ; le lot sorti reste attaché à la ligne. */
    private function takeStock(Order $order): void
    {
        foreach ($order->items()->with('product')->get() as $item) {
            try {
                $taken = $this->fefo->take($item->product, Quantity::toMilli($item->stock_qty));
            } catch (InsufficientStock $e) {
                throw new WorkflowException(__('orders.errors.stock', [
                    'product' => $item->product_name,
                    'qty' => Format::quantity(Quantity::fromMilli($e->availableMilli)),
                ]));
            }
            foreach ($taken as ['lot' => $lot, 'milli' => $milli]) {
                $item->lots()->create(['lot_id' => $lot->id, 'qty' => Quantity::fromMilli($milli)]);
            }
        }
    }
}
