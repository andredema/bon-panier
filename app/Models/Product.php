<?php

namespace App\Models;

use App\Enums\Channel;
use App\Models\Concerns\Auditable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'brand', 'barcode', 'photo_path', 'stock_unit_id',
        'is_frozen', 'is_taxable', 'is_visible', 'allow_preorder', 'alert_threshold', 'popularity',
    ];

    protected function casts(): array
    {
        return [
            'is_frozen' => 'boolean',
            'is_taxable' => 'boolean',
            'is_visible' => 'boolean',
            'allow_preorder' => 'boolean',
            'alert_threshold' => 'decimal:3',
            'popularity' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'stock_unit_id');
    }

    public function formats(): HasMany
    {
        return $this->hasMany(SaleFormat::class)->orderBy('channel')->orderBy('sort_order')->orderBy('id');
    }

    /** Formats visibles d'un canal, dans l'ordre d'affichage. */
    public function formatsFor(Channel $channel): HasMany
    {
        return $this->hasMany(SaleFormat::class)
            ->where('channel', $channel->value)
            ->where('is_visible', true)
            ->orderBy('sort_order')->orderBy('id');
    }

    public function scopeVisible(Builder $q): void
    {
        $q->where('is_visible', true);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }
}
