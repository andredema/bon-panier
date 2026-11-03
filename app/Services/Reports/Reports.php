<?php

namespace App\Services\Reports;

use App\Models\Category;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Lot;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\StockAdjustment;
use Illuminate\Support\Collection;

/**
 * Rapports et documents comptables (cahier des charges 6). Tous les montants en cents.
 *
 * Les ventes se comptent sur les factures émises dans la période, moins les notes de
 * crédit émises dans la même période ; les encaissements sur les paiements reçus.
 */
final class Reports
{
    /** @var array<string, Collection> factures déjà chargées, par période */
    private array $cache = [];

    /** @return Collection<int, Invoice> */
    public function invoices(Period $p): Collection
    {
        return $this->cache[$p->slug()] ??= Invoice::with(['order', 'lines', 'payments'])
            ->whereBetween('issued_at', [$p->from, $p->to])
            ->orderBy('issued_at')->orderBy('id')
            ->get();
    }

    /** @return Collection<int, CreditNote> */
    public function creditNotes(Period $p): Collection
    {
        return CreditNote::with(['invoice', 'lines'])
            ->whereBetween('issued_at', [$p->from, $p->to])
            ->orderBy('issued_at')->orderBy('id')
            ->get();
    }

    /**
     * Rapport de taxes : à reporter dans les déclarations TPS/TVQ de la période.
     *
     * @return array<string, int>
     */
    public function taxes(Period $p): array
    {
        $invoices = $this->invoices($p);
        $credits = $this->creditNotes($p);

        // Livraison : taxable ou détaxée selon la facture
        $taxable = $invoices->sum(fn ($i) => $i->taxable_cents + ($i->delivery_taxable ? $i->delivery_fee_cents : 0));
        $zeroRated = $invoices->sum(fn ($i) => $i->subtotal_cents - $i->taxable_cents + ($i->delivery_taxable ? 0 : $i->delivery_fee_cents));

        return [
            'zero_rated' => (int) $zeroRated,
            'taxable' => (int) $taxable,
            'credits' => (int) $credits->sum('subtotal_cents'),
            'credits_tps' => (int) $credits->sum('tax_tps_cents'),
            'credits_tvq' => (int) $credits->sum('tax_tvq_cents'),
            'tps' => (int) ($invoices->sum('tax_tps_cents') - $credits->sum('tax_tps_cents')),
            'tvq' => (int) ($invoices->sum('tax_tvq_cents') - $credits->sum('tax_tvq_cents')),
            'total' => (int) ($invoices->sum('total_cents') - $credits->sum('total_cents')),
        ];
    }

    /**
     * Synthèse du mois (maquette rapport-mensuel.html).
     *
     * @return array<string, int>
     */
    public function summary(Period $p): array
    {
        $invoices = $this->invoices($p);
        $credits = $this->creditNotes($p);
        $sales = (int) ($invoices->sum(fn ($i) => $i->subtotal_cents + $i->delivery_fee_cents) - $credits->sum('subtotal_cents'));
        $taxes = $this->taxes($p);

        return [
            'sales' => $sales,
            'count' => $invoices->count(),
            'average' => $invoices->count() ? intdiv($sales + intdiv($invoices->count(), 2), $invoices->count()) : 0,
            'tps' => $taxes['tps'],
            'tvq' => $taxes['tvq'],
            'collected' => (int) Payment::whereBetween('paid_at', [$p->from, $p->to])->sum('amount_cents'),
        ];
    }

    /** @return array<string, int> encaissements par mode, du plus grand au plus petit */
    public function paymentsByMethod(Period $p): array
    {
        return Payment::whereBetween('paid_at', [$p->from, $p->to])
            ->get(['method', 'amount_cents'])
            ->groupBy('method')
            ->map(fn ($g) => (int) $g->sum('amount_cents'))
            ->sortDesc()
            ->all();
    }

    /** @return array{detail: int, gros: int} ventes avant taxes par canal (lignes de facture) */
    public function byChannel(Period $p): array
    {
        $lines = $this->invoices($p)->flatMap->lines;

        return [
            'detail' => (int) $lines->where('channel', 'detail')->sum('line_total_cents'),
            'gros' => (int) $lines->where('channel', 'gros')->sum('line_total_cents'),
        ];
    }

    /** @return list<array{name: string, cents: int, taxable: bool}> ventes avant taxes par catégorie */
    public function byCategory(Period $p): array
    {
        $lines = $this->invoices($p)->flatMap->lines;
        $itemIds = $lines->pluck('order_item_id')->filter();
        $categoryOf = OrderItem::with('product')->whereIn('id', $itemIds)->get()
            ->mapWithKeys(fn ($i) => [$i->id => $i->product?->category_id]);
        $names = Category::pluck('name', 'id');

        return $lines->groupBy(fn ($l) => $categoryOf->get($l->order_item_id) ?? 0)
            ->map(fn ($g, $catId) => [
                'name' => $names->get($catId, __('reports.no_category')),
                'cents' => (int) $g->sum('line_total_cents'),
                'taxable' => $g->contains('is_taxable', true),
            ])
            ->sortByDesc('cents')->values()->all();
    }

    /** @return list<array{name: string, cents: int}> */
    public function topProducts(Period $p, int $limit = 5): array
    {
        return $this->invoices($p)->flatMap->lines
            ->groupBy('product_name')
            ->map(fn ($g, $name) => ['name' => $name, 'cents' => (int) $g->sum('line_total_cents')])
            ->sortByDesc('cents')->take($limit)->values()->all();
    }

    /**
     * Comptes clients : factures impayées, par âge depuis l'émission (0-30, 31-60, 60+ jours).
     *
     * @return array{rows: list<array>, buckets: array<string, int>, total: int}
     */
    public function receivables(): array
    {
        $rows = Invoice::with(['creditNotes', 'payments'])->orderBy('issued_at')->get()
            ->map(function (Invoice $i) {
                $balance = $i->total_cents - (int) $i->creditNotes->sum('total_cents') - (int) $i->payments->sum('amount_cents');
                $days = (int) $i->issued_at->copy()->startOfDay()->diffInDays(today());

                return [
                    'invoice' => $i,
                    'balance' => $balance,
                    'days' => $days,
                    'bucket' => $days <= 30 ? '0-30' : ($days <= 60 ? '31-60' : '60+'),
                ];
            })
            ->filter(fn ($r) => $r['balance'] > 0)
            ->values();

        return [
            'rows' => $rows->all(),
            'buckets' => collect(['0-30', '31-60', '60+'])->mapWithKeys(fn ($b) => [$b => (int) $rows->where('bucket', $b)->sum('balance')])->all(),
            'total' => (int) $rows->sum('balance'),
        ];
    }

    /**
     * Inventaire valorisé au coût : lots en stock (valeur proportionnelle au reste)
     * et lots dont la date limite approche.
     *
     * @return array{lots: Collection, total: int, without_cost: int, soon: Collection}
     */
    public function inventory(int $soonDays = 30): array
    {
        $lots = Lot::inStock()->with(['product.stockUnit', 'product.category', 'receipt.supplier'])
            ->join('products', 'products.id', '=', 'lots.product_id')
            ->orderBy('products.name')->orderByRaw('lots.best_before is null')->orderBy('lots.best_before')
            ->select('lots.*')->get()
            ->each(fn (Lot $l) => $l->value = $l->remainingValueCents());

        return [
            'lots' => $lots,
            'total' => (int) $lots->sum('value'),
            'without_cost' => $lots->whereNull('value')->count(),
            'soon' => $lots->filter(fn ($l) => $l->best_before && $l->best_before->lte(today()->addDays($soonDays)))->sortBy('best_before')->values(),
        ];
    }

    /** @return Collection<int, Lot> journal des entrées en stock de la période */
    public function receipts(Period $p): Collection
    {
        return Lot::with(['product.stockUnit', 'receipt.supplier', 'receipt.user'])
            ->whereHas('receipt', fn ($r) => $r->whereBetween('received_on', [$p->from->toDateString(), $p->to->toDateString()]))
            ->orderBy('stock_receipt_id')->orderBy('id')
            ->get();
    }

    /** @return Collection<int, StockAdjustment> */
    public function adjustments(Period $p): Collection
    {
        return StockAdjustment::with(['product.stockUnit', 'lot', 'user'])
            ->whereBetween('created_at', [$p->from, $p->to])
            ->orderBy('created_at')->get();
    }
}
