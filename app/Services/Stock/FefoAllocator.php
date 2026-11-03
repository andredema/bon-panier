<?php

namespace App\Services\Stock;

use App\Models\Lot;
use App\Models\Product;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;

/**
 * Sortie de stock FEFO (cahier des charges 5.1) : le lot dont la date limite arrive
 * en premier sort en premier ; à date égale (ou sans date), le plus ancien (FIFO).
 * Chaque sortie dit de quel lot vient la marchandise (traçabilité).
 */
final class FefoAllocator
{
    /**
     * Plan de sortie sans rien modifier.
     *
     * @return list<array{lot: Lot, milli: int}>
     *
     * @throws InsufficientStock
     */
    public function plan(Product $product, int $milli): array
    {
        return $this->allocate(Lot::where('product_id', $product->id)->inStock()->fefo()->get(), $product, $milli);
    }

    /**
     * Retire $milli du stock, lots verrouillés pendant la transaction.
     *
     * @return list<array{lot: Lot, milli: int}>
     *
     * @throws InsufficientStock
     */
    public function take(Product $product, int $milli): array
    {
        return DB::transaction(function () use ($product, $milli) {
            $lots = Lot::where('product_id', $product->id)->inStock()->fefo()->lockForUpdate()->get();
            $plan = $this->allocate($lots, $product, $milli);

            foreach ($plan as ['lot' => $lot, 'milli' => $part]) {
                $lot->remaining_qty = Quantity::fromMilli(Quantity::toMilli($lot->remaining_qty) - $part);
                $lot->save();
            }

            return $plan;
        });
    }

    /** @return list<array{lot: Lot, milli: int}> */
    private function allocate($lots, Product $product, int $milli): array
    {
        if ($milli <= 0) {
            return [];
        }

        $plan = [];
        $left = $milli;

        foreach ($lots as $lot) {
            $part = min($left, Quantity::toMilli($lot->remaining_qty));
            if ($part > 0) {
                $plan[] = ['lot' => $lot, 'milli' => $part];
                $left -= $part;
            }
            if ($left === 0) {
                return $plan;
            }
        }

        throw new InsufficientStock($product, $milli, $milli - $left);
    }
}
