<?php

namespace App\Http\Controllers\Shop;

use App\Enums\Channel;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ExpectedArrival;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Services\Orders\Cart;
use App\Services\Orders\PickupSlots;
use App\Services\Stock\StockLevels;
use App\Support\Format;
use App\Support\Quantity;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Page de commande publique (maquettes/commande.html). */
class HomeController extends Controller
{
    public function __invoke(StockLevels $levels, Cart $cart, PickupSlots $slots): View
    {
        $products = Product::visible()
            ->with(['category', 'stockUnit', 'formats' => fn ($q) => $q->where('is_visible', true)->with(['unit', 'netUnit'])])
            ->get()
            ->filter(fn (Product $p) => $p->formats->isNotEmpty());

        $available = $levels->availableByProduct($products->pluck('id'));
        $arrivals = ExpectedArrival::open()->whereIn('product_id', $products->pluck('id'))
            ->whereDate('expected_on', '>=', today())->orderBy('expected_on')->get()
            ->groupBy('product_id')->map(fn ($g) => $g->first()->expected_on);

        $wholesale = Settings::get('wholesale.prices_visible_to') === 'all';
        $summary = $cart->summary();
        $notBefore = $summary['latest_arrival'] ? Carbon::parse($summary['latest_arrival']) : null;

        return view('shop.home', [
            'catalog' => [
                'categories' => Category::ordered()->get()->map(fn (Category $c) => [
                    'id' => $c->id, 'name' => $c->name, 'icon' => $c->icon,
                    'bg' => $c->palette->background(), 'ink' => $c->palette->ink(),
                ])->values(),
                'products' => $products->map(fn (Product $p) => $this->product($p, $available->get($p->id, 0), $arrivals->get($p->id), $wholesale))->values(),
                'wholesale' => $wholesale,
            ],
            'cart' => $summary,
            'slots' => collect($slots->available(now(), $notBefore))->map(fn ($s) => ['value' => $s['value'], 'label' => $s['label']])->values(),
            'checkout' => [
                'delivery' => (bool) Settings::get('delivery.enabled'),
                'delivery_zone' => Settings::get('delivery.zone'),
                'delivery_fee' => Format::money((int) Settings::get('delivery.fee')),
                'pay_on_handover' => (bool) Settings::get('payment.on_handover'),
                'address' => Settings::get('shop.address'),
            ],
        ]);
    }

    /** Données d'une carte produit : rien d'interne (ni stock exact, ni coût). */
    private function product(Product $p, int $availableMilli, ?Carbon $arrival, bool $wholesale): array
    {
        $status = $availableMilli > 0 ? 'stock' : ($p->allow_preorder && $arrival ? 'preco' : 'none');

        return [
            'id' => $p->id,
            'name' => $p->name,
            'brand' => $p->brand,
            'cat' => $p->category_id,
            'frozen' => $p->is_frozen,
            'photo' => $p->photoUrl(),
            'pop' => $p->popularity ?: 9999,
            'status' => $status,
            'arrival' => $status === 'preco' ? Format::shortDate($arrival) : null,
            'formats' => $p->formats
                ->filter(fn (SaleFormat $f) => $wholesale || $f->channel === Channel::Retail)
                ->map(fn (SaleFormat $f) => [
                    'id' => $f->id,
                    'c' => $f->channel->value,
                    'u' => $f->unit->code,
                    'label' => $f->label,
                    'price' => $f->price_cents,
                    'price_label' => Format::money($f->price_cents),
                    'by_weight' => $f->is_by_weight,
                    'unit' => $f->unit->short_label,
                    'step' => (float) $f->step,
                    'step_milli' => Quantity::toMilli($f->step),
                    'min_milli' => Quantity::toMilli($f->min_qty),
                    'min_label' => Quantity::toMilli($f->min_qty) > Quantity::toMilli($f->step)
                        ? __('shop.card.minimum', ['qty' => Format::quantity($f->min_qty), 'unit' => $f->unit->short_label])
                        : null,
                    'cmp' => ($c = $f->comparisonPrice($p->stockUnit)) ? Format::money($c['cents']).'/'.$c['unit'] : null,
                ])->values(),
        ];
    }
}
