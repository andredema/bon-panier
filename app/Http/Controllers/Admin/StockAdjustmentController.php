<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lot;
use App\Models\StockAdjustment;
use App\Services\Stock\StockAdjuster;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Ajustements de stock d'un lot (casse, perte, péremption, inventaire) : toute l'équipe, journalisés. */
class StockAdjustmentController extends Controller
{
    public function create(Lot $lot): View
    {
        $lot->load(['product.stockUnit', 'receipt']);

        return view('admin.stock.adjust', [
            'lot' => $lot,
            'recent' => StockAdjustment::with('user')->where('lot_id', $lot->id)->latest('id')->limit(10)->get(),
        ]);
    }

    public function store(Request $request, Lot $lot, StockAdjuster $adjuster): RedirectResponse
    {
        [$adjustment, $warning] = $adjuster->adjust($lot, (string) $request->input('qty'), (string) $request->input('reason'), $request->input('note'), $request->user());

        $delta = $adjustment->delta;
        $redirect = redirect()->route('admin.products.edit', $lot->product_id)->with('status', __('reports.adjust.saved', [
            'number' => $lot->lot_number,
            'delta' => (str_starts_with($delta, '-') ? '' : '+').Format::quantity($delta),
            'unit' => $lot->product->stockUnit->short_label,
        ]));

        return $warning ? $redirect->with('warnings', [$warning]) : $redirect;
    }
}
