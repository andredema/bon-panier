<?php

namespace App\Models;

use App\Support\Quantity;
use Database\Factories\LotFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lot extends Model
{
    /** @use HasFactory<LotFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id', 'stock_receipt_id', 'sale_format_id', 'received_label', 'received_qty',
        'content_qty', 'converted_qty', 'remaining_qty', 'lot_number', 'best_before',
        'unit_cost_cents', 'cost_cents',
    ];

    protected function casts(): array
    {
        return [
            'received_qty' => 'decimal:3',
            'content_qty' => 'decimal:3',
            'converted_qty' => 'decimal:3',
            'remaining_qty' => 'decimal:3',
            'best_before' => 'date',
            'unit_cost_cents' => 'integer',
            'cost_cents' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockReceipt::class, 'stock_receipt_id');
    }

    public function saleFormat(): BelongsTo
    {
        return $this->belongsTo(SaleFormat::class)->withTrashed();
    }

    public function scopeInStock(Builder $q): void
    {
        $q->where('remaining_qty', '>', 0);
    }

    /**
     * Ordre de sortie FEFO : date limite la plus proche d'abord (sans date en dernier),
     * puis le lot le plus ancien (FIFO).
     */
    public function scopeFefo(Builder $q): void
    {
        $q->orderByRaw('best_before is null')->orderBy('best_before')->orderBy('id');
    }

    /** Valeur du reste du lot, au coût (cents), proportionnelle à ce qui reste. */
    public function remainingValueCents(): ?int
    {
        if ($this->cost_cents === null) {
            return null;
        }

        $converted = Quantity::toMilli($this->converted_qty);

        return $converted > 0 ? Quantity::divRound($this->cost_cents * Quantity::toMilli($this->remaining_qty), $converted) : 0;
    }
}
