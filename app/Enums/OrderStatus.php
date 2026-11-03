<?php

namespace App\Enums;

/** Statuts de commande (règle 7) : recue → confirmee → en_preparation → prete → remise, ou annulee. */
enum OrderStatus: string
{
    case Received = 'recue';
    case Confirmed = 'confirmee';
    case Preparing = 'en_preparation';
    case Ready = 'prete';
    case HandedOver = 'remise';
    case Cancelled = 'annulee';

    public function label(): string
    {
        return __('shop.status.'.$this->value);
    }

    /** Classe de pastille, reprise des maquettes (e-recue, e-prete…). */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Preparing => 'e-preparation',
            default => 'e-'.$this->value,
        };
    }

    /** La commande retient du stock tant qu'elle n'est ni remise ni annulée. */
    public function reservesStock(): bool
    {
        return ! in_array($this, [self::HandedOver, self::Cancelled], true);
    }

    /** Étape suivante du parcours normal, ou nul (remise, annulée). */
    public function next(): ?self
    {
        return match ($this) {
            self::Received => self::Confirmed,
            self::Confirmed => self::Preparing,
            self::Preparing => self::Ready,
            self::Ready => self::HandedOver,
            default => null,
        };
    }

    /** Libellé du bouton d'étape suivante (« Confirmer », « Préparer »…). */
    public function nextActionLabel(): ?string
    {
        return $this->next() ? __('orders.actions.'.$this->next()->value) : null;
    }

    /** Statuts qui exigent que toutes les précommandes soient servies (règle 5.2). */
    public function needsPreordersServed(): bool
    {
        return in_array($this, [self::Preparing, self::Ready, self::HandedOver], true);
    }

    /** @return list<self> */
    public static function active(): array
    {
        return array_values(array_filter(self::cases(), fn (self $s) => $s->reservesStock()));
    }
}
