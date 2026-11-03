<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditNote extends Model
{
    use Immutable;

    protected $fillable = [
        'number', 'invoice_id', 'issued_at', 'reason', 'delivery_cents', 'subtotal_cents', 'taxable_cents',
        'tax_tps_cents', 'tax_tvq_cents', 'total_cents', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'delivery_cents' => 'integer',
            'subtotal_cents' => 'integer',
            'taxable_cents' => 'integer',
            'tax_tps_cents' => 'integer',
            'tax_tvq_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CreditNoteLine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
