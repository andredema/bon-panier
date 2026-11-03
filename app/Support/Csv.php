<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports CSV pour Excel en français (cahier des charges 6) : séparateur « ; »,
 * UTF-8 avec BOM, virgule décimale. Écrits en flux, sans tout charger en mémoire.
 */
final class Csv
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, scalar|null>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            echo self::content($headers, $rows);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Contenu complet (pour une pièce jointe de courriel).
     *
     * @param  list<string>  $headers
     * @param  iterable<array<int, scalar|null>>  $rows
     */
    public static function content(array $headers, iterable $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\u{FEFF}");
        fputcsv($out, $headers, ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, array_map(fn ($v) => $v ?? '', $row), ';', '"', '');
        }
        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);

        return $content;
    }

    /** Cents → « 1234,50 » (sans séparateur de milliers ni « $ », pour les calculs dans Excel). */
    public static function money(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);

        return $sign.intdiv($abs, 100).','.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Quantité DECIMAL(12,3) → « 1,5 ». */
    public static function qty(string|int $qty): string
    {
        return str_replace(Format::NBSP, '', Format::quantity($qty));
    }
}
