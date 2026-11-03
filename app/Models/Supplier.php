<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use Auditable, HasFactory;

    protected $fillable = ['name', 'contact', 'phone', 'email', 'usual_delay_days', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'usual_delay_days' => 'integer'];
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(StockReceipt::class);
    }

    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }
}
