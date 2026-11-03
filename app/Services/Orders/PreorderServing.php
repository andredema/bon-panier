<?php

namespace App\Services\Orders;

use App\Mail\PreorderArrived;
use App\Models\ExpectedArrival;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReceipt;
use App\Services\Stock\StockLevels;
use App\Support\Quantity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Service des précommandes (cahier des charges 5.2).
 *
 * À chaque entrée en stock, les lignes en précommande du produit sont servies dans
 * l'ordre de création des commandes, tant que le stock disponible couvre la ligne
 * entière. On s'arrête à la première ligne qui ne tient pas : une précommande plus
 * récente ne passe jamais devant une plus ancienne.
 *
 * Une ligne servie retient ensuite le stock comme une ligne ordinaire (StockLevels).
 * Les lignes restantes prennent la date du prochain arrivage annoncé, s'il y en a un.
 */
final class PreorderServing
{
    public function __construct(private StockLevels $levels) {}

    /**
     * @param  iterable<int>  $productIds
     * @return Collection<int, OrderItem> lignes servies
     */
    public function serve(iterable $productIds, ?StockReceipt $receipt = null, bool $notify = true): Collection
    {
        $served = DB::transaction(function () use ($productIds, $receipt) {
            $served = collect();
            $products = Product::whereIn('id', collect($productIds)->unique()->all())->lockForUpdate()->get();

            foreach ($products as $product) {
                $available = $this->levels->availableMilli($product);

                $pending = OrderItem::pendingPreorder()
                    ->where('order_items.product_id', $product->id)
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->orderBy('orders.created_at')->orderBy('orders.id')->orderBy('order_items.id')
                    ->select('order_items.*')
                    ->lockForUpdate()
                    ->get();

                foreach ($pending as $item) {
                    $need = Quantity::toMilli($item->stock_qty);
                    if ($need > $available) {
                        break;
                    }
                    $item->update(['preorder_served_at' => now(), 'served_receipt_id' => $receipt?->id]);
                    $available -= $need;
                    $served->push($item);
                }

                // Lignes encore en attente : date du prochain arrivage annoncé
                $next = ExpectedArrival::open()->where('product_id', $product->id)
                    ->whereDate('expected_on', '>=', today())->orderBy('expected_on')->value('expected_on');
                if ($next) {
                    $pending->reject(fn (OrderItem $i) => $i->preorder_served_at !== null)
                        ->each(fn (OrderItem $i) => $i->update(['expected_on' => $next]));
                }
            }

            return $served;
        });

        if ($notify) {
            $this->notify($served);
        }

        return $served;
    }

    /** Un courriel par commande, listant ses lignes arrivées. */
    private function notify(Collection $served): void
    {
        $served->groupBy('order_id')->each(function (Collection $items) {
            $order = $items->first()->order()->with('customer')->first();
            Mail::to($order->customer->email)->queue(new PreorderArrived($order, $items->pluck('id')->all()));
            OrderItem::whereIn('id', $items->pluck('id'))->update(['preorder_notified_at' => now()]);
        });
    }
}
