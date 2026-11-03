<?php

namespace App\Enums;

/**
 * Couleurs des cartes de catégorie, reprises de maquettes/commande.html (objet « teintes »).
 * Palettes fermées : chaque couple fond/encre respecte un contraste d'au moins 4,5:1.
 */
enum CategoryPalette: string
{
    case Green = 'vert';
    case Wicker = 'osier';
    case Blue = 'bleu';
    case Earth = 'terre';
    case Purple = 'violet';

    public function background(): string
    {
        return match ($this) {
            self::Green => '#E4EDE6',
            self::Wicker => '#F6E8CF',
            self::Blue => '#E1ECF5',
            self::Earth => '#F5E1DA',
            self::Purple => '#EEE6F7',
        };
    }

    public function ink(): string
    {
        return match ($this) {
            self::Green => '#1F4D2E',
            self::Wicker => '#7A4F0E',
            self::Blue => '#1F5A8A',
            self::Earth => '#8A3420',
            self::Purple => '#5B3A8A',
        };
    }

    public function label(): string
    {
        return __('catalog.palettes.'.$this->value);
    }
}
