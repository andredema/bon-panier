<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'sale_format_id', 'product_name', 'format_label', 'channel',
        'is_by_weight', 'qty_unit', 'qty', 'stock_qty', 'unit_price_cents', 'line_total_cents',
        'is_taxable', 'is_preorder', 'expected_on', 'preorder_served_at', 'served_receipt_id', 'preorder_notified_at',
    ];

    /** Précommandes en attente : pas encore servies, commande ni remise ni annulée. */
    public function scopePendingPreorder(Builder $q): void
    {
        $q->where('order_items.is_preorder', true)
            ->whereNull('order_items.preorder_served_at')
            ->whereHas('order', fn ($o) => $o->whereIn('status', array_map(fn (OrderStatus $s) => $s->value, OrderStatus::active())));
    }

    public function isPendingPreorder(): bool
    {
        return $this->is_preorder && $this->preorder_served_at === null;
    }

    public function lots(): HasMany
    {
        return $this->hasMany(OrderItemLot::class);
    }

    public function servedReceipt(): BelongsTo
    {
        return $this->belongsTo(StockReceipt::class, 'served_receipt_id');
    }

    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'is_by_weight' => 'boolean',
            'qty' => 'decimal:3',
            'stock_qty' => 'decimal:3',
            'unit_price_cents' => 'integer',
            'line_total_cents' => 'integer',
            'is_taxable' => 'boolean',
            'is_preorder' => 'boolean',
            'expected_on' => 'date',
            'preorder_served_at' => 'datetime',
            'preorder_notified_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function saleFormat(): BelongsTo
    {
        return $this->belongsTo(SaleFormat::class)->withTrashed();
    }
}
