<?php

namespace App\Enums;

enum UserRole: string
{
    /** Tous les droits ; seul à gérer les comptes de gérant et de super administrateur. */
    case Admin = 'admin';
    case Manager = 'gerant';
    case Employee = 'employe';
    /** Lecture de la comptabilité : factures, paiements, rapports. */
    case Accountant = 'comptable';

    public function label(): string
    {
        return __('app.roles.'.$this->value);
    }

    /** Rôles qu'un compte de ce rôle peut attribuer (et donc gérer). */
    public function assignable(): array
    {
        return match ($this) {
            self::Admin => self::cases(),
            self::Manager => [self::Employee, self::Accountant],
            default => [],
        };
    }
}
