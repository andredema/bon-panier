<?php

namespace App\Services\Stock;

use App\Models\Lot;
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
     * Quantités réservées par les commandes non encore remises.
     * Les commandes arrivent à l'étape 4 : rien n'est réservé pour l'instant.
     */
    public function reservedMilli(Product $product): int
    {
        return 0;
    }

    public function availableMilli(Product $product): int
    {
        return $this->onHandMilli($product) - $this->reservedMilli($product);
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

    /** Vrai si le stock disponible est sous le seuil d'alerte du produit. */
    public function isBelowThreshold(Product $product, int $availableMilli): bool
    {
        return $availableMilli <= Quantity::toMilli($product->alert_threshold);
    }
}
