<?php

namespace App\Services\Orders;

use App\Support\Quantity;
use App\Support\Settings;

/**
 * Taxes du Québec (règle 4, cahier des charges 5.3) : TPS et TVQ sur le seul sous-total
 * taxable, chacune arrondie au cent (demi vers le haut) sur le total, taux en réglages.
 *
 * Exemple de contrôle (facture de la maquette) : sous-total 247,42 $ dont 27,48 $ taxables
 * → TPS 1,37 $, TVQ 2,74 $, total 251,53 $.
 */
final class TaxCalculator
{
    /**
     * @return array{subtotal: int, taxable: int, delivery: int, tps: int, tvq: int, total: int}
     */
    public function totals(int $subtotalCents, int $taxableCents, int $deliveryCents = 0, bool $deliveryTaxable = false): array
    {
        $taxBase = $taxableCents + ($deliveryTaxable ? $deliveryCents : 0);

        // Taux en millièmes de pour cent : 5 % = 5000, donc diviser par 100 000
        $tps = Quantity::divRound($taxBase * Settings::rate('tax.tps_rate'), 100_000);
        $tvq = Quantity::divRound($taxBase * Settings::rate('tax.tvq_rate'), 100_000);

        return [
            'subtotal' => $subtotalCents,
            'taxable' => $taxableCents,
            'delivery' => $deliveryCents,
            'tps' => $tps,
            'tvq' => $tvq,
            'total' => $subtotalCents + $deliveryCents + $tps + $tvq,
        ];
    }

    /** Montant d'une ligne : prix × quantité (1,5 kg × 2,99 $ = 4,49 $), arrondi au cent. */
    public static function lineTotal(int $unitPriceCents, string $qty): int
    {
        return Quantity::divRound($unitPriceCents * Quantity::toMilli($qty), 1000);
    }
}
