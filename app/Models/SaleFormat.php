<?php

namespace App\Models;

use App\Enums\Channel;
use App\Models\Concerns\Auditable;
use App\Support\Quantity;
use Database\Factories\SaleFormatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleFormat extends Model
{
    /** @use HasFactory<SaleFormatFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id', 'channel', 'unit_id', 'label', 'is_by_weight', 'content_qty',
        'net_qty', 'net_unit_id', 'price_cents', 'min_qty', 'step', 'is_visible', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'is_by_weight' => 'boolean',
            'content_qty' => 'decimal:3',
            'net_qty' => 'decimal:3',
            'price_cents' => 'integer',
            'min_qty' => 'decimal:3',
            'step' => 'decimal:3',
            'is_visible' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function netUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'net_unit_id');
    }

    /** Ce que retire du stock une quantité commandée (en millièmes de l'unité de stock). */
    public function stockMilliFor(string $qty): int
    {
        return Quantity::divRound(Quantity::toMilli($qty) * Quantity::toMilli($this->content_qty), 1000);
    }

    /** Prix par unité de stock, en cents (sert à repérer les erreurs de prix). */
    public function pricePerStockUnitCents(): int
    {
        return Quantity::divRound($this->price_cents * 1000, Quantity::toMilli($this->content_qty));
    }

    /**
     * Prix comparatif au kg ou au L : ['cents' => 1398, 'unit' => 'kg'], ou nul.
     * Sans objet pour la vente au poids (le prix affiché est déjà au kg).
     */
    public function comparisonPrice(Unit $stockUnit): ?array
    {
        if ($this->is_by_weight) {
            return null;
        }

        [$milli, $unit] = $stockUnit->isMeasure()
            ? [Quantity::toMilli($this->content_qty), $stockUnit]
            : [$this->net_qty !== null ? Quantity::toMilli($this->net_qty) : 0, $this->netUnit];

        if (! $unit || ! $unit->isMeasure() || $milli <= 0) {
            return null;
        }

        // millièmes × facteur = micro-unités de base ; 1 kg ou 1 L = 1 000 000
        $cents = Quantity::divRound($this->price_cents * 1_000_000_000, $milli * $unit->base_factor);

        return ['cents' => $cents, 'unit' => $unit->kind->comparisonLabel()];
    }
}
