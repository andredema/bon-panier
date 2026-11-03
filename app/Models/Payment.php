<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use Auditable;

    public const INTERAC = 'interac';

    public const CASH = 'comptant';

    public const DEBIT = 'debit';

    public const CARD = 'carte';

    public const METHODS = [self::INTERAC, self::CASH, self::DEBIT, self::CARD];

    protected $fillable = ['order_id', 'invoice_id', 'method', 'amount_cents', 'paid_at', 'reference', 'user_id', 'note'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'paid_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
