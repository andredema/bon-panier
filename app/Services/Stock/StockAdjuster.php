<?php

namespace App\Services\Stock;

use App\Models\Lot;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Support\Format;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ajustement de stock d'un lot : on saisit la quantité réellement restante
 * (après casse, perte, péremption ou comptage d'inventaire). L'écart et sa valeur
 * au coût du lot sont inscrits au journal des ajustements.
 */
final class StockAdjuster
{
    public function __construct(private StockLevels $levels) {}

    /** @return array{0: StockAdjustment, 1: ?string} l'ajustement et un éventuel avertissement */
    public function adjust(Lot $lot, string $newQty, string $reason, ?string $note, ?User $user): array
    {
        $errors = [];
        if (! in_array($reason, StockAdjustment::REASONS, true)) {
            $errors['reason'] = __('reports.adjust.errors.reason');
        }
        if (! Quantity::isValid($newQty) || Quantity::toMilli($newQty) < 0) {
            $errors['qty'] = __('reports.adjust.errors.qty');
        } elseif (Quantity::toMilli($newQty) === Quantity::toMilli($lot->remaining_qty)) {
            $errors['qty'] = __('reports.adjust.errors.same');
        }
        if ($reason === 'autre' && blank($note)) {
            $errors['note'] = __('reports.adjust.errors.note');
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $adjustment = DB::transaction(function () use ($lot, $newQty, $reason, $note, $user) {
            $lot = Lot::whereKey($lot->id)->lockForUpdate()->firstOrFail();
            $before = Quantity::toMilli($lot->remaining_qty);
            $after = Quantity::toMilli($newQty);
            $delta = $after - $before;

            $converted = Quantity::toMilli($lot->converted_qty);
            $value = $lot->cost_cents !== null && $converted > 0
                ? ($delta < 0 ? -1 : 1) * Quantity::divRound($lot->cost_cents * abs($delta), $converted)
                : null;

            $lot->remaining_qty = Quantity::fromMilli($after);
            $lot->save();

            return StockAdjustment::create([
                'lot_id' => $lot->id,
                'product_id' => $lot->product_id,
                'qty_before' => Quantity::fromMilli($before),
                'qty_after' => Quantity::fromMilli($after),
                'delta' => Quantity::fromMilli($delta),
                'value_cents' => $value,
                'reason' => $reason,
                'note' => $note ? mb_substr(trim($note), 0, 255) : null,
                'user_id' => $user?->id,
            ]);
        });

        // Le stock retenu par les commandes dépasse peut-être maintenant le stock en main
        $available = $this->levels->availableMilli($lot->product);

        return [$adjustment, $available < 0 ? __('reports.adjust.oversold', ['qty' => Format::quantity(Quantity::fromMilli(-$available)), 'unit' => $lot->product->stockUnit->short_label]) : null];
    }
}
