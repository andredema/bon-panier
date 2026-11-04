<?php

namespace App\Models;

use App\Enums\CategoryPalette;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /** Icônes disponibles (symboles SVG repris des maquettes). */
    public const ICONS = ['feuille', 'sac', 'poisson', 'bouteille', 'piment', 'plantain'];

    protected $fillable = ['name', 'slug', 'icon', 'palette', 'sort_order'];

    protected function casts(): array
    {
        return ['palette' => CategoryPalette::class];
    }

    protected static function booted(): void
    {
        static::saving(function (Category $c) {
            if (blank($c->slug) || $c->isDirty('name')) {
                $c->slug = static::uniqueSlug($c->name, $c->id);
            }
        });
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeOrdered(Builder $q): void
    {
        $q->orderBy('sort_order')->orderBy('name');
    }

    private static function uniqueSlug(string $name, ?int $ignoreId): string
    {
        $base = Str::slug($name) ?: 'categorie';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
