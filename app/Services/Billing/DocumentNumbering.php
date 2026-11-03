<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Numérotation continue et sans trou des documents comptables (cahier 5.4) :
 * « F-2026-0001 », « NC-2026-0001 ». Le compteur de l'année est verrouillé
 * pendant la transaction qui crée le document : si la transaction échoue,
 * le numéro n'est pas consommé.
 */
final class DocumentNumbering
{
    public const INVOICE = 'F';

    public const CREDIT_NOTE = 'NC';

    /** À appeler dans la transaction qui crée le document. */
    public function next(string $type, int $year): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('La numérotation doit se faire dans une transaction.');
        }

        DB::table('document_sequences')->insertOrIgnore(['type' => $type, 'year' => $year, 'last_number' => 0]);

        $row = DB::table('document_sequences')->where('type', $type)->where('year', $year)->lockForUpdate()->first();
        $number = $row->last_number + 1;
        DB::table('document_sequences')->where('id', $row->id)->update(['last_number' => $number]);

        return sprintf('%s-%d-%04d', $type, $year, $number);
    }
}
