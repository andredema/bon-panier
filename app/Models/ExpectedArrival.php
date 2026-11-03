<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\ExpectedArrivalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Arrivage attendu : donne la date affichée aux précommandes (cahier des charges 5.2). */
class ExpectedArrival extends Model
{
    /** @use HasFactory<ExpectedArrivalFactory> */
    use Auditable, HasFactory;

    public const OPEN = 'open';

    public const RECEIVED = 'received';

    public const CANCELLED = 'cancelled';

    protected $fillable = ['supplier_id', 'product_id', 'expected_on', 'qty', 'notes', 'status', 'stock_receipt_id'];

    protected function casts(): array
    {
        return ['expected_on' => 'date', 'qty' => 'decimal:3'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeOpen(Builder $q): void
    {
        $q->where('status', self::OPEN);
    }
}
