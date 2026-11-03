<?php

namespace App\Services\Orders;

use App\Enums\Channel;
use App\Models\ExpectedArrival;
use App\Models\SaleFormat;
use App\Services\Stock\StockLevels;
use App\Support\Format;
use App\Support\Quantity;
use App\Support\Settings;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Panier conservé côté serveur (session), pour survivre au rechargement (cahier 3.4).
 *
 * Chaque ligne est un format de vente et une quantité. À l'affichage comme à l'envoi,
 * le stock disponible est réparti entre les lignes dans leur ordre d'ajout ; ce qui
 * dépasse passe en précommande si le produit l'accepte et qu'un arrivage est annoncé
 * (toute la ligne, ou seulement la partie manquante, selon le réglage).
 */
final class Cart
{
    private const KEY = 'cart.lines';

    private const MAX_QTY = 9999_000;

    public function __construct(
        private Session $session,
        private StockLevels $levels,
        private TaxCalculator $taxes,
    ) {}

    /** @return array<int, string> quantité par id de format, dans l'ordre d'ajout */
    public function raw(): array
    {
        return $this->session->get(self::KEY, []);
    }

    public function isEmpty(): bool
    {
        return $this->raw() === [];
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    /**
     * Ajoute une quantité d'un format (s'additionne à la ligne existante).
     *
     * @throws CartException
     */
    public function add(SaleFormat $format, string $qty): void
    {
        $lines = $this->raw();
        $milli = $this->validQty($format, $qty);
        $current = isset($lines[$format->id]) ? Quantity::toMilli($lines[$format->id]) : 0;

        $this->store($format, $lines, $current + $milli);
    }

    /**
     * Fixe la quantité d'une ligne ; 0 retire la ligne.
     *
     * @throws CartException
     */
    public function set(SaleFormat $format, string $qty): void
    {
        $lines = $this->raw();

        if (Quantity::isValid($qty) && Quantity::toMilli($qty) === 0) {
            unset($lines[$format->id]);
            $this->session->put(self::KEY, $lines);

            return;
        }

        $this->store($format, $lines, $this->validQty($format, $qty));
    }

    public function remove(int $formatId): void
    {
        $lines = $this->raw();
        unset($lines[$formatId]);
        $this->session->put(self::KEY, $lines);
    }

    /**
     * État complet du panier : lignes, parties en stock / en précommande, totaux.
     *
     * @return array{lines: list<array>, count: int, has_preorder: bool, has_unavailable: bool,
     *               latest_arrival: ?string, totals: array, labels: array, signature: string}
     */
    public function summary(): array
    {
        return $this->summaryFor($this->raw());
    }

    /**
     * Même évaluation pour une liste de lignes quelconque (commande saisie au comptoir).
     *
     * @param  array<int, string>  $raw  quantité par id de format, dans l'ordre
     */
    public function summaryFor(array $raw): array
    {
        $formats = SaleFormat::with(['product.stockUnit', 'unit'])->whereIn('id', array_keys($raw))->get()->keyBy('id');

        // Lignes dont le format ou le produit a disparu du catalogue : retirées en silence
        $raw = array_filter($raw, fn ($q, $id) => ($f = $formats->get($id)) && $f->is_visible && $f->product && ! $f->product->trashed() && $f->product->is_visible, ARRAY_FILTER_USE_BOTH);

        $productIds = collect($raw)->keys()->map(fn ($id) => $formats[$id]->product_id)->unique();
        $left = $this->levels->availableByProduct($productIds)->map(fn ($m) => max(0, $m))->all();
        $arrivals = $this->arrivals($productIds);
        $scope = Settings::get('preorder.scope');

        $lines = [];
        $subtotal = 0;
        $taxable = 0;

        foreach ($raw as $formatId => $qty) {
            $f = $formats[$formatId];
            $p = $f->product;
            $qtyMilli = Quantity::toMilli($qty);
            $needed = $f->stockMilliFor($qty);
            $available = $left[$p->id] ?? 0;
            $arrival = $arrivals->get($p->id);
            $canPreorder = $p->allow_preorder && $arrival !== null;

            // Parties en stock et en précommande (en quantité de la ligne)
            if ($needed <= $available) {
                $parts = [['qty' => $qtyMilli, 'preorder' => false]];
                $left[$p->id] = $available - $needed;
            } elseif (! $canPreorder) {
                $parts = [['qty' => $qtyMilli, 'preorder' => false, 'unavailable' => true]];
            } elseif ($scope === 'missing_part' && ($inStock = $this->fitQty($f, $available)) > 0) {
                $parts = [['qty' => $inStock, 'preorder' => false], ['qty' => $qtyMilli - $inStock, 'preorder' => true]];
                $left[$p->id] = $available - $f->stockMilliFor(Quantity::fromMilli($inStock));
            } else {
                $parts = [['qty' => $qtyMilli, 'preorder' => true]];
            }

            $lineTotal = TaxCalculator::lineTotal($f->price_cents, $qty);
            $subtotal += $lineTotal;
            if ($p->is_taxable) {
                $taxable += $lineTotal;
            }

            $lines[] = [
                'format_id' => $f->id,
                'product_id' => $p->id,
                'name' => $p->name,
                'brand' => $p->brand,
                'label' => $f->label,
                'channel' => $f->channel->value,
                'is_by_weight' => $f->is_by_weight,
                'is_frozen' => $p->is_frozen,
                'is_taxable' => $p->is_taxable,
                'unit' => $f->is_by_weight ? $f->unit->short_label : null,
                'qty' => Quantity::fromMilli($qtyMilli),
                'qty_label' => $this->qtyLabel($f, $qtyMilli),
                'step' => $f->step,
                'min' => $f->min_qty,
                'unit_price_cents' => $f->price_cents,
                'line_total_cents' => $lineTotal,
                'line_total_label' => Format::money($lineTotal),
                'parts' => array_map(fn ($part) => $part + ['qty_label' => $this->qtyLabel($f, $part['qty'])], $parts),
                'preorder' => collect($parts)->every('preorder'),
                'partial_preorder' => count($parts) > 1,
                'unavailable' => (bool) ($parts[0]['unavailable'] ?? false),
                'expected_on' => $canPreorder && collect($parts)->contains('preorder', true) ? $arrival : null,
                'expected_label' => $canPreorder && collect($parts)->contains('preorder', true) ? Format::shortDate($arrival) : null,
            ];
        }

        $totals = $this->taxes->totals($subtotal, $taxable);
        $taxesTotal = $totals['tps'] + $totals['tvq'];
        $latest = collect($lines)->pluck('expected_on')->filter()->max();

        return [
            'lines' => $lines,
            'count' => count($lines),
            'has_preorder' => collect($lines)->contains(fn ($l) => $l['expected_on'] !== null),
            'has_unavailable' => collect($lines)->contains('unavailable', true),
            'has_frozen' => collect($lines)->contains('is_frozen', true),
            'latest_arrival' => $latest,
            'totals' => $totals + ['taxes' => $taxesTotal],
            'labels' => [
                'subtotal' => Format::money($totals['subtotal']),
                'taxes' => Format::money($taxesTotal),
                'total' => Format::money($totals['total']),
                'count' => trans_choice('shop.cart.items', count($lines)),
            ],
            // Empreinte de ce que le client a vu : si la disponibilité change avant l'envoi, on le prévient
            'signature' => sha1(json_encode(array_map(fn ($l) => [$l['format_id'], $l['qty'], $l['parts'], $l['unit_price_cents']], $lines))),
        ];
    }

    /**
     * Prochain arrivage attendu (date) par produit.
     *
     * @return Collection<int, string>
     */
    private function arrivals(Collection $productIds): Collection
    {
        return ExpectedArrival::open()
            ->whereIn('product_id', $productIds->all())
            ->whereDate('expected_on', '>=', today())
            ->orderBy('expected_on')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($g) => $g->first()->expected_on->toDateString());
    }

    /**
     * Plus grande quantité de ce format (respectant le pas) qui tient dans $availableMilli.
     */
    private function fitQty(SaleFormat $f, int $availableMilli): int
    {
        $content = Quantity::toMilli($f->content_qty);
        $step = Quantity::toMilli($f->step);
        $maxQty = intdiv($availableMilli * 1000, $content);

        return intdiv($maxQty, $step) * $step;
    }

    private function qtyLabel(SaleFormat $f, int $milli): string
    {
        $q = Format::quantity(Quantity::fromMilli($milli));

        return $f->is_by_weight ? $q.' '.$f->unit->short_label : $q;
    }

    /**
     * Vérifie une ligne saisie par l'équipe (comptoir) : mêmes règles de pas et de minimum.
     * L'équipe vend en gros même quand les prix de gros sont masqués en ligne.
     *
     * @throws CartException
     */
    public function validateLine(SaleFormat $format, string $qty): int
    {
        $milli = $this->validQty($format, $qty, staff: true);
        $this->checkMin($format, $milli);

        return $milli;
    }

    /** @throws CartException */
    private function validQty(SaleFormat $format, string $qty, bool $staff = false): int
    {
        $product = $format->product;

        if (! $format->is_visible || ! $product || $product->trashed() || ! $product->is_visible) {
            throw new CartException(__('shop.cart.errors.unavailable_format'));
        }
        if (! $staff && $format->channel === Channel::Wholesale && Settings::get('wholesale.prices_visible_to') !== 'all') {
            throw new CartException(__('shop.cart.errors.wholesale_closed'));
        }
        if (! Quantity::isValid($qty) || ($milli = Quantity::toMilli($qty)) <= 0 || $milli > self::MAX_QTY) {
            throw new CartException(__('shop.cart.errors.qty'));
        }

        $step = Quantity::toMilli($format->step);
        if ($milli % $step !== 0) {
            throw new CartException(__('shop.cart.errors.step', ['step' => Format::quantity($format->step)]));
        }

        return $milli;
    }

    /** @throws CartException */
    private function checkMin(SaleFormat $format, int $milli): void
    {
        if ($milli < Quantity::toMilli($format->min_qty)) {
            throw new CartException($format->channel === Channel::Wholesale
                ? __('shop.cart.errors.wholesale_min', ['min' => Format::quantity($format->min_qty), 'label' => $format->label])
                : __('shop.cart.errors.min', ['min' => Format::quantity($format->min_qty)]));
        }
        if ($milli > self::MAX_QTY) {
            throw new CartException(__('shop.cart.errors.qty'));
        }
    }

    /** @throws CartException */
    private function store(SaleFormat $format, array $lines, int $milli): void
    {
        $this->checkMin($format, $milli);

        $previous = $lines;
        $lines[$format->id] = Quantity::fromMilli($milli);
        $this->session->put(self::KEY, $lines);

        // Stock insuffisant et précommande impossible : on refuse et on remet l'ancien panier
        $line = collect($this->summary()['lines'])->firstWhere('format_id', $format->id);
        if ($line && $line['unavailable']) {
            $this->session->put(self::KEY, $previous);
            $left = max(0, $this->levels->availableMilli($format->product));
            throw new CartException(__('shop.cart.errors.stock', [
                'qty' => Format::quantity(Quantity::fromMilli($left)),
                'unit' => $format->product->stockUnit->short_label,
            ]));
        }
    }
}
