<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\OrderWorkflow;
use App\Services\Orders\WorkflowException;
use App\Services\Stock\FefoAllocator;
use App\Services\Stock\InsufficientStock;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Gestion des commandes (cahier 4.2) : toute l'équipe traite les commandes. */
class OrderController extends Controller
{
    public function __construct(private OrderWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $segment = $request->query('statut', 'actives');
        $filters = [
            'canal' => array_intersect((array) $request->query('canal', []), ['detail', 'gros']),
            'mode' => array_intersect((array) $request->query('mode', []), [Order::PICKUP, Order::DELIVERY]),
            'jour' => array_intersect((array) $request->query('jour', []), ['auj', 'demain', 'semaine']),
            'preco' => $request->boolean('preco'),
            'q' => trim((string) $request->query('q')),
        ];

        $base = Order::query()->tap(fn ($q) => $this->applyFilters($q, $filters));

        $counts = (clone $base)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $active = array_map(fn (OrderStatus $s) => $s->value, OrderStatus::active());
        $counts = $counts->all() + ['actives' => collect($counts)->only($active)->sum(), 'toutes' => collect($counts)->sum()];

        $orders = $base->with('customer')->withPendingPreorders()
            ->when($segment === 'actives', fn ($q) => $q->whereIn('status', $active))
            ->when(OrderStatus::tryFrom($segment), fn ($q, $s) => $q->where('status', $s->value))
            ->when(in_array($segment, ['remise', 'annulee', 'toutes'], true), fn ($q) => $q->orderByDesc('slot_start'), fn ($q) => $q->orderBy('slot_start'))
            ->paginate(25)->withQueryString();

        return view('admin.orders.index', compact('orders', 'segment', 'filters', 'counts'));
    }

    public function show(Order $order): View
    {
        $order->load(['customer', 'creator', 'items.lots.lot', 'items.product.stockUnit', 'history.user', 'invoice.creditNotes', 'payments.user']);

        return view('admin.orders.show', compact('order'));
    }

    public function advance(Request $request, Order $order): RedirectResponse
    {
        try {
            $this->workflow->advance($order, $request->user());
        } catch (WorkflowException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        return back()->with('status', __('orders.done.'.$order->status->value, ['number' => $order->number]));
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        try {
            $this->workflow->transition($order, OrderStatus::Cancelled, $request->user(), (string) $request->input('reason'));
        } catch (WorkflowException $e) {
            return back()->withErrors(['reason' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.orders.show', $order)->with('status', __('orders.done.annulee', ['number' => $order->number]));
    }

    /** Bon de préparation imprimable, avec les lots suggérés en FEFO (sans rien sortir). */
    public function picking(Order $order, FefoAllocator $fefo): View
    {
        $order->load(['customer', 'items.product.stockUnit']);
        $plans = $order->items->mapWithKeys(function ($item) use ($fefo) {
            if ($item->isPendingPreorder()) {
                return [$item->id => null];
            }
            try {
                return [$item->id => $fefo->plan($item->product, Quantity::toMilli($item->stock_qty))];
            } catch (InsufficientStock) {
                return [$item->id => false];
            }
        });

        return view('admin.orders.picking', compact('order', 'plans'));
    }

    /** @param  array<string, mixed>  $f */
    private function applyFilters(Builder $q, array $f): void
    {
        $q->when($f['canal'], fn ($q, $v) => $q->whereIn('channel', $v))
            ->when($f['mode'], fn ($q, $v) => $q->whereIn('fulfilment', $v))
            ->when($f['preco'], fn ($q) => $q->whereHas('items', fn ($i) => $i->where('is_preorder', true)->whereNull('preorder_served_at')))
            ->when($f['jour'], function ($q, $days) {
                $q->where(function ($w) use ($days) {
                    foreach ($days as $d) {
                        [$from, $to] = match ($d) {
                            'auj' => [today(), today()->endOfDay()],
                            'demain' => [today()->addDay(), today()->addDay()->endOfDay()],
                            default => [today(), today()->addDays(7)->endOfDay()],
                        };
                        $w->orWhereBetween('slot_start', [$from, $to]);
                    }
                });
            })
            ->when($f['q'] !== '', function ($q) use ($f) {
                $term = $f['q'];
                $q->where(fn ($w) => $w->where('number', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")));
            });
    }
}
