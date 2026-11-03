<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\URL;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const PICKUP = 'ramassage';

    public const DELIVERY = 'livraison';

    public const PAY_INTERAC = 'interac';

    public const PAY_ON_HANDOVER = 'remise';

    public const SOURCE_ONLINE = 'en_ligne';

    public const SOURCE_COUNTER = 'comptoir';

    protected $fillable = [
        'number', 'customer_id', 'channel', 'status', 'fulfilment', 'slot_start', 'slot_end',
        'delivery_address', 'payment_method', 'subtotal_cents', 'taxable_cents', 'delivery_fee_cents',
        'tax_tps_cents', 'tax_tvq_cents', 'total_cents', 'has_preorder', 'notes', 'consent_at', 'cancel_reason',
        'source', 'created_by', 'handed_over_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'status' => OrderStatus::class,
            'slot_start' => 'datetime',
            'slot_end' => 'datetime',
            'consent_at' => 'datetime',
            'handed_over_at' => 'datetime',
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

    /** Ajoute items_pending_count : lignes en précommande pas encore servies. */
    public function scopeWithPendingPreorders(Builder $q): void
    {
        $q->withCount(['items as items_pending_count' => fn ($i) => $i->where('is_preorder', true)->whereNull('preorder_served_at')]);
    }

    /** Membre de l'équipe qui a saisi la commande (comptoir, téléphone). */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('is_preorder')->orderBy('id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_at');
    }

    /** Solde dû : facture (ou commande, avant facture) − notes de crédit − paiements. */
    public function balanceCents(): int
    {
        $invoice = $this->invoice;

        return $invoice ? $invoice->balanceCents() : $this->total_cents - (int) $this->payments()->sum('amount_cents');
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    /**
     * Règle 5.2 : une commande qui contient une précommande non servie reste
     * « Reçue » ou « Confirmée » ; elle ne peut pas passer « Prête ».
     */
    public function hasPendingPreorder(): bool
    {
        return $this->items()->where('is_preorder', true)->whereNull('preorder_served_at')->exists();
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
