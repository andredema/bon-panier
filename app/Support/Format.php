<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Affichage fr-CA : « 1 284,50 $ », « 2 novembre 2026 », « 1,5 ».
 *
 * L'espace des milliers et celle qui précède « $ » sont insécables (U+00A0),
 * pour qu'un montant ne soit jamais coupé en fin de ligne.
 */
final class Format
{
    public const NBSP = "\u{00A0}";

    /** Montant en cents (entier) vers « 1 284,50 $ ». */
    public static function money(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);
        $dollars = intdiv($abs, 100);
        $rest = $abs % 100;

        return $sign.number_format($dollars, 0, ',', self::NBSP).','.str_pad((string) $rest, 2, '0', STR_PAD_LEFT).self::NBSP.'$';
    }

    /**
     * Saisie fr-CA (« 1 284,50 », « 12.5 », « 3 $ ») vers des cents, sans passer par un float.
     */
    public static function parseMoney(string $input): int
    {
        $clean = str_replace([self::NBSP, "\u{202F}", ' ', '$'], '', trim($input));
        $clean = str_replace(',', '.', $clean);

        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $clean, $m)) {
            throw new InvalidArgumentException("Montant invalide : {$input}");
        }

        $cents = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return $m[1] === '-' ? -$cents : $cents;
    }

    /** Quantité DECIMAL(12,3) (« 1.500 ») vers « 1,5 » ; les zéros inutiles disparaissent. */
    public static function quantity(string|int $qty): string
    {
        $qty = (string) $qty;
        [$int, $dec] = array_pad(explode('.', ltrim($qty, '-')), 2, '');
        $dec = rtrim($dec, '0');
        $out = number_format((int) $int, 0, ',', self::NBSP).($dec !== '' ? ','.$dec : '');

        return (str_starts_with($qty, '-') ? '-' : '').$out;
    }

    /** « 2 novembre 2026 » ; le premier du mois s'écrit « 1er novembre 2026 ». */
    public static function date(CarbonInterface|string $date): string
    {
        $d = self::carbon($date);

        return self::day($d).' '.$d->translatedFormat('F Y');
    }

    /** « lundi 2 novembre 2026 » */
    public static function longDate(CarbonInterface|string $date): string
    {
        $d = self::carbon($date);

        return $d->translatedFormat('l').' '.self::date($d);
    }

    /** « 6 nov. » (mai, juin et juillet restent entiers, comme l'usage québécois). */
    public static function shortDate(CarbonInterface|string $date): string
    {
        $d = self::carbon($date);
        $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juill.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

        return self::day($d).' '.$months[$d->month - 1];
    }

    /** « 14 h 05 », « 10 h » */
    public static function time(CarbonInterface|string $date): string
    {
        $d = self::carbon($date);

        return $d->hour.' h'.($d->minute ? ' '.str_pad((string) $d->minute, 2, '0', STR_PAD_LEFT) : '');
    }

    private static function day(CarbonInterface $d): string
    {
        return $d->day === 1 ? '1er' : (string) $d->day;
    }

    private static function carbon(CarbonInterface|string $date): CarbonInterface
    {
        return ($date instanceof CarbonInterface ? $date : Carbon::parse($date))->locale('fr_CA');
    }
}
