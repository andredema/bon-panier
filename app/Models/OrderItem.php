<?php

namespace App\Models;

use App\Enums\Channel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'sale_format_id', 'product_name', 'format_label', 'channel',
        'is_by_weight', 'qty_unit', 'qty', 'stock_qty', 'unit_price_cents', 'line_total_cents',
        'is_taxable', 'is_preorder', 'expected_on', 'preorder_served_at',
    ];

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
