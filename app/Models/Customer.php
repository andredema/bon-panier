<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    public const TYPES = ['particulier', 'gros'];

    protected $fillable = ['name', 'phone', 'email', 'address', 'type', 'approved_wholesale', 'consent_at', 'marketing_consent_at', 'marketing_unsubscribed_at', 'erased_at'];

    protected function casts(): array
    {
        return ['approved_wholesale' => 'boolean', 'consent_at' => 'datetime', 'marketing_consent_at' => 'datetime',
            'marketing_unsubscribed_at' => 'datetime', 'erased_at' => 'datetime'];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** Abonné aux soldes et nouveautés par courriel (consentement exprès, pas de désabonnement depuis). */
    public function isSubscribed(): bool
    {
        return $this->marketing_consent_at !== null && $this->email !== null;
    }

    public function scopeSubscribed(Builder $q): void
    {
        $q->whereNotNull('marketing_consent_at')->whereNotNull('email')->whereNull('erased_at');
    }

    public function subscribe(): void
    {
        $this->forceFill(['marketing_consent_at' => now(), 'marketing_unsubscribed_at' => null])->save();
    }

    public function unsubscribe(): void
    {
        if ($this->marketing_consent_at) {
            $this->forceFill(['marketing_consent_at' => null, 'marketing_unsubscribed_at' => now()])->save();
        }
    }

    /** Lien de désabonnement en un clic, signé et sans date d'expiration. */
    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('shop.unsubscribe', $this);
    }

    public function scopeSearch(Builder $q, string $term): void
    {
        $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%"));
    }

    public function isErased(): bool
    {
        return $this->erased_at !== null;
    }

    /** Solde dû sur ses factures (total − notes de crédit − paiements). */
    public function balanceCents(): int
    {
        return (int) $this->invoices->sum(fn (Invoice $i) => max(0, $i->balanceCents()));
    }

    /** Commandes encore en cours (ni remises ni annulées). */
    public function hasActiveOrders(): bool
    {
        return $this->orders()->whereIn('status', OrderStatus::active())->exists();
    }
}
