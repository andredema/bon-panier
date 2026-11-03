<?php

namespace App\Models;

use Database\Factories\StockReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bon de réception. Jamais modifié après validation : une erreur se corrige par un ajustement. */
class StockReceipt extends Model
{
    /** @use HasFactory<StockReceiptFactory> */
    use HasFactory;

    protected $fillable = ['supplier_id', 'received_on', 'delivery_note_no', 'user_id', 'notify_preorders', 'notes'];

    protected function casts(): array
    {
        return ['received_on' => 'date', 'notify_preorders' => 'boolean'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class)->orderBy('id');
    }

    /** N° affiché : « E-2026-0042 ». */
    public function number(): string
    {
        return 'E-'.$this->received_on->format('Y').'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }
}
