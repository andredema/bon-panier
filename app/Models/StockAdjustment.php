<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    public const UPDATED_AT = null;

    public const REASONS = ['casse', 'perte', 'peremption', 'inventaire', 'autre'];

    protected $fillable = ['lot_id', 'product_id', 'qty_before', 'qty_after', 'delta', 'value_cents', 'reason', 'note', 'user_id'];

    protected function casts(): array
    {
        return [
            'qty_before' => 'decimal:3',
            'qty_after' => 'decimal:3',
            'delta' => 'decimal:3',
            'value_cents' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
