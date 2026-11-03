<?php

namespace App\Services\Reports;

use App\Support\Csv;

/**
 * Contenu des exports CSV (téléchargement et pièces jointes au comptable).
 * Chaque méthode rend [nom de fichier, en-têtes, lignes].
 */
final class ReportExports
{
    public const TYPES = ['ventes', 'taxes', 'inventaire', 'entrees', 'ajustements', 'comptes-clients'];

    public function __construct(private Reports $reports) {}

    /** @return array{0: string, 1: list<string>, 2: iterable} */
    public function build(string $type, Period $p): array
    {
        return match ($type) {
            'ventes' => $this->sales($p),
            'taxes' => $this->taxes($p),
            'inventaire' => $this->inventory(),
            'entrees' => $this->receipts($p),
            'ajustements' => $this->adjustments($p),
            'comptes-clients' => $this->receivables(),
        };
    }

    /** Journal des ventes : factures puis notes de crédit (montants négatifs). */
    private function sales(Period $p): array
    {
        $rows = [];
        foreach ($this->reports->invoices($p) as $i) {
            $channels = $i->lines->pluck('channel')->unique();
            $rows[] = [
                $i->issued_at->toDateString(), $i->number, $i->order->number, $i->customer_snapshot['name'],
                $channels->count() > 1 ? 'mixte' : $channels->first(),
                Csv::money($i->subtotal_cents + $i->delivery_fee_cents), Csv::money($i->tax_tps_cents), Csv::money($i->tax_tvq_cents),
                Csv::money($i->total_cents), Csv::money((int) $i->payments->sum('amount_cents')),
                $i->payments->pluck('method')->unique()->join(', '),
            ];
        }
        foreach ($this->reports->creditNotes($p) as $n) {
            $rows[] = [
                $n->issued_at->toDateString(), $n->number, $n->invoice->number, $n->invoice->customer_snapshot['name'], 'note de crédit',
                Csv::money(-$n->subtotal_cents), Csv::money(-$n->tax_tps_cents), Csv::money(-$n->tax_tvq_cents), Csv::money(-$n->total_cents), '', '',
            ];
        }

        return ['journal-des-ventes_'.$p->slug().'.csv',
            ['Date', 'N°', 'Référence', 'Client', 'Canal', 'Avant taxes', 'TPS', 'TVQ', 'Total', 'Payé', 'Modes de paiement'], $rows];
    }

    private function taxes(Period $p): array
    {
        $t = $this->reports->taxes($p);

        return ['rapport-de-taxes_'.$p->slug().'.csv', ['Poste', 'Montant'], [
            ['Période', $p->from->toDateString().' au '.$p->to->toDateString()],
            [__('reports.taxes.zero_rated'), Csv::money($t['zero_rated'])],
            [__('reports.taxes.taxable'), Csv::money($t['taxable'])],
            [__('reports.taxes.credits'), Csv::money(-$t['credits'])],
            ['TPS des notes de crédit', Csv::money(-$t['credits_tps'])],
            ['TVQ des notes de crédit', Csv::money(-$t['credits_tvq'])],
            [__('reports.taxes.tps'), Csv::money($t['tps'])],
            [__('reports.taxes.tvq'), Csv::money($t['tvq'])],
            [__('reports.taxes.total'), Csv::money($t['total'])],
        ]];
    }

    private function inventory(): array
    {
        $inv = $this->reports->inventory();
        $rows = $inv['lots']->map(fn ($l) => [
            $l->product->name, $l->product->category?->name, $l->lot_number, Csv::qty($l->remaining_qty), $l->product->stockUnit->short_label,
            $l->best_before?->toDateString(), $l->receipt->received_on->toDateString(), $l->receipt->supplier?->name,
            $l->cost_cents !== null ? Csv::money($l->cost_cents) : '', $l->value !== null ? Csv::money($l->value) : '',
        ]);

        return ['inventaire-valorise_'.today()->toDateString().'.csv',
            ['Produit', 'Catégorie', 'Lot', 'Quantité restante', 'Unité', 'Date limite', 'Reçu le', 'Fournisseur', 'Coût du lot', 'Valeur restante'], $rows];
    }

    private function receipts(Period $p): array
    {
        $rows = $this->reports->receipts($p)->map(fn ($l) => [
            $l->receipt->received_on->toDateString(), $l->receipt->number(), $l->receipt->supplier?->name, $l->product->name, $l->received_label,
            Csv::qty($l->received_qty), Csv::qty($l->converted_qty), $l->product->stockUnit->short_label, $l->lot_number,
            $l->best_before?->toDateString(), $l->cost_cents !== null ? Csv::money($l->cost_cents) : '', $l->receipt->user?->name,
        ]);

        return ['journal-des-entrees_'.$p->slug().'.csv',
            ['Date', 'Entrée', 'Fournisseur', 'Produit', 'Format reçu', 'Qté reçue', 'En stock', 'Unité', 'Lot', 'Date limite', 'Coût', 'Employé'], $rows];
    }

    private function adjustments(Period $p): array
    {
        $rows = $this->reports->adjustments($p)->map(fn ($a) => [
            $a->created_at->toDateString(), $a->product->name, $a->lot->lot_number, Csv::qty($a->qty_before), Csv::qty($a->qty_after),
            Csv::qty($a->delta), $a->product->stockUnit->short_label, $a->value_cents !== null ? Csv::money($a->value_cents) : '',
            __('reports.adjust.reasons.'.$a->reason), $a->note, $a->user?->name,
        ]);

        return ['ajustements-de-stock_'.$p->slug().'.csv',
            ['Date', 'Produit', 'Lot', 'Avant', 'Après', 'Écart', 'Unité', 'Valeur', 'Motif', 'Précision', 'Employé'], $rows];
    }

    private function receivables(): array
    {
        $r = $this->reports->receivables();
        $rows = array_map(fn ($row) => [
            $row['invoice']->number, $row['invoice']->issued_at->toDateString(), $row['invoice']->customer_snapshot['name'],
            Csv::money($row['invoice']->total_cents), Csv::money($row['balance']), $row['days'], $row['bucket'],
        ], $r['rows']);

        return ['comptes-clients_'.today()->toDateString().'.csv', ['Facture', 'Date', 'Client', 'Total', 'Solde', 'Jours', 'Tranche'], $rows];
    }
}
