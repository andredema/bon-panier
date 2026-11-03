<?php

namespace App\Models;

use App\Enums\UnitKind;
use App\Support\Quantity;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    protected $fillable = [
        'code', 'label', 'short_label', 'kind', 'base_factor', 'allows_decimals',
        'default_step', 'description', 'is_active', 'is_system', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'kind' => UnitKind::class,
            'base_factor' => 'integer',
            'allows_decimals' => 'boolean',
            'default_step' => 'decimal:3',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): void
    {
        $q->orderBy('sort_order')->orderBy('label');
    }

    public function isMeasure(): bool
    {
        return $this->kind->isMeasure();
    }

    public function isConvertibleTo(Unit $other): bool
    {
        return $this->is($other)
            || ($this->isMeasure() && $this->kind === $other->kind && $this->base_factor && $other->base_factor);
    }

    /**
     * Convertit une quantité (en millièmes) de cette unité vers $to.
     * Nul si la conversion est impossible ou ne tombe pas juste au millième.
     */
    public function convertMilli(int $milli, Unit $to): ?int
    {
        if ($this->is($to)) {
            return $milli;
        }

        if (! $this->isConvertibleTo($to)) {
            return null;
        }

        $num = $milli * $this->base_factor;

        return $num % $to->base_factor === 0 ? intdiv($num, $to->base_factor) : null;
    }

    /** Conversion d'une quantité saisie (« 0,5 ») ; nul si impossible. */
    public function convert(string $qty, Unit $to): ?string
    {
        $milli = $this->convertMilli(Quantity::toMilli($qty), $to);

        return $milli === null ? null : Quantity::fromMilli($milli);
    }
}
