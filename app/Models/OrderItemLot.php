<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sortie de stock d'une ligne vendue : de quel lot, combien (traçabilité). */
class OrderItemLot extends Model
{
    protected $fillable = ['order_item_id', 'lot_id', 'qty'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
