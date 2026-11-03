<?php

namespace App\Services\Stock;

use App\Enums\OrderStatus;
use App\Models\Lot;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Quantity;
use Illuminate\Support\Collection;

/**
 * Niveaux de stock, en millièmes de l'unité de stock (cahier des charges 5.1) :
 * stock disponible = Σ lots.quantité restante − quantités réservées.
 */
final class StockLevels
{
    public function onHandMilli(Product $product): int
    {
        return $this->onHandByProduct([$product->id])->get($product->id, 0);
    }

    /**
     * Quantités réservées : lignes des commandes ni remises ni annulées, sauf les
     * précommandes pas encore servies (elles attendent l'arrivage, elles ne retiennent rien).
     * Les lots ne sortent qu'à la remise (FEFO) : d'ici là, le stock est seulement retenu.
     */
    public function reservedMilli(Product $product): int
    {
        return $this->reservedByProduct([$product->id])->get($product->id, 0);
    }

    public function availableMilli(Product $product): int
    {
        return $this->onHandMilli($product) - $this->reservedMilli($product);
    }

    /**
     * Stock disponible de plusieurs produits en deux requêtes.
     *
     * @param  iterable<int>  $productIds
     * @return Collection<int, int>
     */
    public function availableByProduct(iterable $productIds): Collection
    {
        $ids = collect($productIds)->values();
        $onHand = $this->onHandByProduct($ids);
        $reserved = $this->reservedByProduct($ids);

        return $ids->mapWithKeys(fn ($id) => [$id => $onHand->get($id, 0) - $reserved->get($id, 0)]);
    }

    /**
     * Stock en main de plusieurs produits en une requête.
     *
     * @param  iterable<int>  $productIds
     * @return Collection<int, int> millièmes, indexés par id de produit
     */
    public function onHandByProduct(iterable $productIds): Collection
    {
        // ROUND(SUM × 1000) : entier exact en MySQL (DECIMAL) comme en SQLite (REAL arrondi)
        return Lot::query()
            ->whereIn('product_id', collect($productIds)->all())
            ->groupBy('product_id')
            ->selectRaw('product_id, ROUND(SUM(remaining_qty) * 1000) as milli')
            ->pluck('milli', 'product_id')
            ->map(fn ($v) => (int) $v);
    }

    /**
     * @param  iterable<int>  $productIds
     * @return Collection<int, int>
     */
    public function reservedByProduct(iterable $productIds): Collection
    {
        return OrderItem::query()
            ->whereIn('order_items.product_id', collect($productIds)->all())
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', array_map(fn (OrderStatus $s) => $s->value, OrderStatus::active()))
            ->where(fn ($q) => $q->where('order_items.is_preorder', false)->orWhereNotNull('order_items.preorder_served_at'))
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, ROUND(SUM(order_items.stock_qty) * 1000) as milli')
            ->pluck('milli', 'product_id')
            ->map(fn ($v) => (int) $v);
    }

    /** Vrai si le stock disponible est sous le seuil d'alerte du produit. */
    public function isBelowThreshold(Product $product, int $availableMilli): bool
    {
        return $availableMilli <= Quantity::toMilli($product->alert_threshold);
    }
}
