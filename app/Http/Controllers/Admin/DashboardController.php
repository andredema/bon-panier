<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Channel;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\ExpectedArrival;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Billing\InteracReconciliation;
use App\Services\Stock\StockLevels;
use App\Support\Quantity;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Accueil de l'espace de gestion (maquettes/tableau-de-bord.html, cahier 4.1). */
class DashboardController extends Controller
{
    public function __invoke(StockLevels $levels, InteracReconciliation $interac): View
    {
        $active = array_map(fn (OrderStatus $s) => $s->value, OrderStatus::active());

        // Ventes du jour : commandes remises aujourd'hui, avant taxes
        $today = Order::where('status', OrderStatus::HandedOver->value)->whereDate('handed_over_at', today())
            ->selectRaw('channel, sum(subtotal_cents + delivery_fee_cents) as total')->groupBy('channel')->pluck('total', 'channel');

        $statusCounts = Order::whereIn('status', $active)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        $pending = OrderItem::pendingPreorder()->with('product.stockUnit')->get();
        $pendingProducts = $pending->pluck('product_id')->unique();
        $arrivals = ExpectedArrival::open()->whereDate('expected_on', '>=', today())->with(['product.stockUnit', 'supplier'])->orderBy('expected_on')->get();

        $interac = $interac->pending();

        return view('admin.dashboard', [
            'kpi' => [
                'sales' => (int) $today->sum(),
                'sales_retail' => (int) ($today[Channel::Retail->value] ?? 0),
                'sales_wholesale' => (int) ($today[Channel::Wholesale->value] ?? 0),
                'received' => (int) ($statusCounts[OrderStatus::Received->value] ?? 0),
                'confirmed' => (int) ($statusCounts[OrderStatus::Confirmed->value] ?? 0),
                'preparing' => (int) ($statusCounts[OrderStatus::Preparing->value] ?? 0),
                'preorders' => $pending->count(),
                'preorder_products' => $pendingProducts->count(),
                'preorder_dates' => $arrivals->whereIn('product_id', $pendingProducts)->pluck('expected_on')->unique(fn ($d) => $d->toDateString())->take(3),
                'interac_total' => (int) $interac->sum('due'),
                'interac_count' => $interac->count(),
            ],
            'orders' => Order::whereIn('status', $active)->with('customer')->withPendingPreorders()->orderBy('slot_start')->limit(50)->get(),
            'stock' => $this->stockLines($levels, $pending, $arrivals),
            'sales' => $this->salesWeek(),
            'arrivals' => $arrivals->groupBy(fn ($a) => $a->expected_on->toDateString().'|'.($a->supplier_id ?? 0))->take(5),
        ]);
    }

    /** Produits à 0 avec précommandes, puis produits sous le seuil d'alerte. */
    private function stockLines(StockLevels $levels, $pending, $arrivals): array
    {
        $products = Product::visible()->with('stockUnit')->get();
        $available = $levels->availableByProduct($products->pluck('id'));
        $lines = [];

        foreach ($products as $p) {
            $avail = $available->get($p->id, 0);
            $items = $pending->where('product_id', $p->id);
            $threshold = Quantity::toMilli($p->alert_threshold);

            if ($items->isNotEmpty() && $avail <= 0) {
                $lines[] = ['type' => 'preorder', 'product' => $p, 'available' => max(0, $avail), 'count' => $items->count(),
                    'qty' => $items->sum(fn ($i) => Quantity::toMilli($i->stock_qty)),
                    'arrival' => $arrivals->firstWhere('product_id', $p->id)?->expected_on, 'ratio' => -1];
            } elseif ($threshold > 0 && $avail <= $threshold) {
                $lines[] = ['type' => 'low', 'product' => $p, 'available' => max(0, $avail), 'threshold' => $threshold,
                    'ratio' => max(0, $avail) / $threshold];
            }
        }

        usort($lines, fn ($a, $b) => $a['ratio'] <=> $b['ratio']);

        return array_slice($lines, 0, 8);
    }

    /** @return list<array{date: Carbon, cents: int}> ventes avant taxes des 7 derniers jours */
    private function salesWeek(): array
    {
        $from = today()->subDays(6);
        $rows = Order::where('status', OrderStatus::HandedOver->value)->where('handed_over_at', '>=', $from)
            ->get(['handed_over_at', 'subtotal_cents', 'delivery_fee_cents'])
            ->groupBy(fn ($o) => $o->handed_over_at->toDateString())
            ->map(fn ($g) => $g->sum(fn ($o) => $o->subtotal_cents + $o->delivery_fee_cents));

        return collect(range(0, 6))->map(fn ($i) => ['date' => $from->copy()->addDays($i), 'cents' => (int) $rows->get($from->copy()->addDays($i)->toDateString(), 0)])->all();
    }
}
