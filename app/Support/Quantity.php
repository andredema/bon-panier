<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Quantités DECIMAL(12,3) manipulées en millièmes entiers (1,5 kg = 1 500),
 * pour ne jamais faire d'arithmétique en float.
 */
final class Quantity
{
    /** « 1,5 », « 1.5 », « 1 200 », 2 → millièmes (1 500, 1 200 000, 2 000). */
    public static function toMilli(string|int $input): int
    {
        if (is_int($input)) {
            return $input * 1000;
        }

        $clean = str_replace([Format::NBSP, "\u{202F}", ' '], '', trim($input));
        $clean = str_replace(',', '.', $clean);

        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,3}))?$/', $clean, $m)) {
            throw new InvalidArgumentException("Quantité invalide : {$input}");
        }

        $milli = (int) $m[2] * 1000 + (int) str_pad($m[3] ?? '0', 3, '0');

        return $m[1] === '-' ? -$milli : $milli;
    }

    /** Millièmes → chaîne pour une colonne DECIMAL(12,3) : 1 500 → « 1.500 ». */
    public static function fromMilli(int $milli): string
    {
        $sign = $milli < 0 ? '-' : '';
        $abs = abs($milli);

        return $sign.intdiv($abs, 1000).'.'.str_pad((string) ($abs % 1000), 3, '0', STR_PAD_LEFT);
    }

    /** Vrai si la saisie est une quantité valide (au plus trois décimales). */
    public static function isValid(mixed $input): bool
    {
        if (! is_string($input) && ! is_int($input)) {
            return false;
        }

        try {
            self::toMilli($input);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /** Division entière arrondie au plus proche, demi vers le haut (valeurs positives). */
    public static function divRound(int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            throw new InvalidArgumentException('Division par zéro.');
        }

        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }
}
