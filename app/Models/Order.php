<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const PICKUP = 'ramassage';

    public const DELIVERY = 'livraison';

    public const PAY_INTERAC = 'interac';

    public const PAY_ON_HANDOVER = 'remise';

    protected $fillable = [
        'number', 'customer_id', 'channel', 'status', 'fulfilment', 'slot_start', 'slot_end',
        'delivery_address', 'payment_method', 'subtotal_cents', 'taxable_cents', 'delivery_fee_cents',
        'tax_tps_cents', 'tax_tvq_cents', 'total_cents', 'has_preorder', 'notes', 'consent_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'status' => OrderStatus::class,
            'slot_start' => 'datetime',
            'slot_end' => 'datetime',
            'consent_at' => 'datetime',
            'has_preorder' => 'boolean',
            'subtotal_cents' => 'integer',
            'taxable_cents' => 'integer',
            'delivery_fee_cents' => 'integer',
            'tax_tps_cents' => 'integer',
            'tax_tvq_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('is_preorder')->orderBy('id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    /** Lien de suivi unique et signé, envoyé dans le courriel de confirmation. */
    public function trackingUrl(): string
    {
        return URL::signedRoute('shop.track', ['order' => $this->number]);
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }
}
