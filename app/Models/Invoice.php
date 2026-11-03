<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use Immutable;

    public const DUE = 'a_payer';

    public const PARTIAL = 'partielle';

    public const PAID = 'payee';

    /** @var list<string> */
    protected array $mutableAfterIssue = ['emailed_at'];

    protected $fillable = [
        'number', 'order_id', 'customer_id', 'issued_at', 'due_on', 'customer_snapshot', 'shop_snapshot',
        'tps_rate', 'tvq_rate', 'fulfilment', 'slot_start', 'slot_end', 'payment_method',
        'subtotal_cents', 'taxable_cents', 'delivery_fee_cents', 'delivery_taxable', 'tax_tps_cents', 'tax_tvq_cents', 'total_cents',
        'user_id', 'emailed_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'due_on' => 'date',
            'slot_start' => 'datetime',
            'slot_end' => 'datetime',
            'emailed_at' => 'datetime',
            'customer_snapshot' => 'array',
            'shop_snapshot' => 'array',
            'tps_rate' => 'integer',
            'tvq_rate' => 'integer',
            'subtotal_cents' => 'integer',
            'taxable_cents' => 'integer',
            'delivery_fee_cents' => 'integer',
            'delivery_taxable' => 'boolean',
            'tax_tps_cents' => 'integer',
            'tax_tvq_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class)->orderBy('id');
    }

    /** Paiements de la commande (y compris ceux reçus avant l'émission). */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id', 'order_id')->orderBy('paid_at');
    }

    public function paidCents(): int
    {
        return (int) $this->payments()->sum('amount_cents');
    }

    public function creditedCents(): int
    {
        return (int) $this->creditNotes()->sum('total_cents');
    }

    /** Solde dû : total − notes de crédit − paiements (négatif = à rembourser). */
    public function balanceCents(): int
    {
        return $this->total_cents - $this->creditedCents() - $this->paidCents();
    }

    public function status(): string
    {
        $balance = $this->balanceCents();

        return match (true) {
            $balance <= 0 => self::PAID,
            $this->paidCents() > 0 => self::PARTIAL,
            default => self::DUE,
        };
    }
}
