<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Services\Orders\CounterOrder;
use App\Services\Orders\WorkflowException;
use App\Services\Stock\StockLevels;
use App\Support\Format;
use App\Support\Quantity;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Commande au comptoir ou au téléphone, saisie par l'équipe. */
class CounterOrderController extends Controller
{
    public function create(StockLevels $levels): View
    {
        // Après une erreur : les lignes saisies reviennent avec leur produit et leurs formats
        $lines = collect(old('lines', []))->map(function ($l) use ($levels) {
            $format = SaleFormat::find((int) ($l['format_id'] ?? 0));
            $product = $format ? Product::with(['stockUnit', 'formats' => fn ($f) => $f->where('is_visible', true)->with('unit')])->find($format->product_id) : null;

            return $product ? ['key' => (string) str()->uuid(), 'format_id' => $format->id, 'qty' => (string) ($l['qty'] ?? '1')]
                + $this->productPayload($product, $levels->availableMilli($product)) : null;
        })->filter()->values();

        return view('admin.orders.create', [
            'delivery' => (bool) Settings::get('delivery.enabled'),
            'now' => now()->addMinutes(5 - now()->minute % 5)->format('Y-m-d\TH:i'),
            'lines' => $lines,
        ]);
    }

    /** Recherche de produits et de leurs formats (détail et gros) pour la saisie. */
    public function products(Request $request, StockLevels $levels): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        if ($q === '') {
            return response()->json([]);
        }

        $products = Product::visible()
            ->where(fn ($w) => $w->where('barcode', $q)->orWhere('name', 'like', "%{$q}%")->orWhere('brand', 'like', "%{$q}%"))
            ->with(['stockUnit', 'formats' => fn ($f) => $f->where('is_visible', true)->with('unit')])
            ->orderBy('name')->limit(8)->get()
            ->filter(fn (Product $p) => $p->formats->isNotEmpty());
        $available = $levels->availableByProduct($products->pluck('id'));

        return response()->json($products->map(fn (Product $p) => ['id' => $p->id] + $this->productPayload($p, $available->get($p->id, 0)))->values());
    }

    private function productPayload(Product $p, int $availableMilli): array
    {
        return [
            'name' => $p->name,
            'brand' => $p->brand,
            'available' => __('orders.counter.available', [
                'qty' => Format::quantity(Quantity::fromMilli(max(0, $availableMilli))),
                'unit' => $p->stockUnit->short_label,
            ]),
            'formats' => $p->formats->map(fn (SaleFormat $f) => [
                'id' => $f->id,
                'label' => $f->label.' · '.($f->channel->value === 'gros' ? __('orders.counter.wholesale') : __('orders.counter.retail')).' · '.Format::money($f->price_cents).($f->is_by_weight ? '/'.$f->unit->short_label : ''),
                'step' => $f->step,
                'min' => $f->min_qty,
            ])->values(),
        ];
    }

    /** Aperçu des lignes et des totaux, avec les mêmes règles qu'à l'enregistrement. */
    public function preview(Request $request, CounterOrder $counter): JsonResponse
    {
        try {
            $summary = $counter->preview((array) $request->input('lines', []));
        } catch (ValidationException $e) {
            return response()->json(['errors' => array_values(array_map(fn ($m) => $m[0], $e->errors()))], 422);
        }

        return response()->json(['cart' => $summary, 'errors' => []]);
    }

    public function store(Request $request, CounterOrder $counter): RedirectResponse
    {
        try {
            $order = $counter->place($request->all(), $request->user());
        } catch (WorkflowException $e) {
            return back()->withErrors(['order' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.orders.show', $order)->with('status', __('orders.counter.created', ['number' => $order->number]));
    }
}
