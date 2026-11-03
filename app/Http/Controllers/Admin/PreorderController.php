<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpectedArrival;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Orders\PreorderServing;
use App\Services\Stock\StockLevels;
use App\Support\Quantity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Précommandes à servir, par produit, dans l'ordre de service (toute l'équipe). */
class PreorderController extends Controller
{
    public function index(StockLevels $levels): View
    {
        $pending = OrderItem::pendingPreorder()
            ->with(['order.customer', 'product.stockUnit'])
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->orderBy('orders.created_at')->orderBy('orders.id')->orderBy('order_items.id')
            ->select('order_items.*')
            ->get()
            ->groupBy('product_id');

        $available = $levels->availableByProduct($pending->keys());
        $arrivals = ExpectedArrival::open()->whereIn('product_id', $pending->keys())
            ->whereDate('expected_on', '>=', today())->orderBy('expected_on')->get()
            ->groupBy('product_id')->map(fn ($g) => $g->first()->expected_on);

        $groups = $pending->map(function ($items, $productId) use ($available, $arrivals) {
            $first = Quantity::toMilli($items->first()->stock_qty);

            return [
                'product' => $items->first()->product,
                'items' => $items,
                'pending_milli' => $items->sum(fn ($i) => Quantity::toMilli($i->stock_qty)),
                'available_milli' => max(0, $available->get($productId, 0)),
                'servable' => $available->get($productId, 0) >= $first,
                'next_arrival' => $arrivals->get($productId),
            ];
        })->sortBy(fn ($g) => $g['next_arrival']?->timestamp ?? PHP_INT_MAX)->values();

        $recent = OrderItem::where('is_preorder', true)->whereNotNull('preorder_served_at')
            ->with(['order.customer', 'product.stockUnit', 'servedReceipt'])
            ->latest('preorder_served_at')->limit(15)->get();

        return view('admin.preorders.index', compact('groups', 'recent'));
    }

    public function serve(Request $request, Product $product, PreorderServing $serving): RedirectResponse
    {
        $served = $serving->serve([$product->id], null, $request->boolean('notify'));

        return back()->with('status', $served->isEmpty()
            ? __('preorders.served_none')
            : trans_choice('preorders.served_now', $served->count()));
    }
}
