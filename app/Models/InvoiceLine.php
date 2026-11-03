<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceLine extends Model
{
    use Immutable;

    public $timestamps = false;

    protected $fillable = [
        'invoice_id', 'order_item_id', 'product_name', 'format_label', 'channel', 'is_frozen', 'qty', 'qty_unit',
        'unit_price_cents', 'line_total_cents', 'is_taxable', 'lot_numbers', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_frozen' => 'boolean',
            'qty' => 'decimal:3',
            'unit_price_cents' => 'integer',
            'line_total_cents' => 'integer',
            'is_taxable' => 'boolean',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creditLines(): HasMany
    {
        return $this->hasMany(CreditNoteLine::class);
    }

    /** Montant encore créditable sur cette ligne. */
    public function creditableCents(): int
    {
        $credited = $this->relationLoaded('creditLines') ? $this->creditLines->sum('amount_cents') : $this->creditLines()->sum('amount_cents');

        return $this->line_total_cents - (int) $credited;
    }
}
