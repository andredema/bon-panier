<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UnitKind;
use App\Http\Controllers\Controller;
use App\Models\SaleFormat;
use App\Models\Unit;
use App\Support\Quantity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Unités de vente ; réservé au gérant.
 * Les nouvelles unités sont des unités à la pièce (carton, botte, douzaine…) :
 * les unités de poids et de volume (kg, g, L) sont fournies et se convertissent entre elles.
 */
class UnitController extends Controller
{
    public function index(): View
    {
        $usage = SaleFormat::selectRaw('unit_id, count(*) as n')->groupBy('unit_id')->pluck('n', 'unit_id');

        return view('admin.settings.units', [
            'units' => Unit::ordered()->get(),
            'usage' => $usage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $code = Str::slug($data['label']) ?: 'unite';
        $base = $code;
        $i = 2;
        while (Unit::where('code', $code)->exists()) {
            $code = $base.'-'.$i++;
        }

        Unit::create($data + [
            'code' => $code,
            'kind' => UnitKind::Count,
            'base_factor' => null,
            'is_active' => true,
            'is_system' => false,
            'sort_order' => (int) Unit::max('sort_order') + 1,
        ]);

        return back()->with('status', __('catalog.units.created'));
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        // Une unité de poids ou de volume garde sa règle de décimales (conversion kg ↔ g)
        $data = $this->validated($request, $unit->isMeasure() ? $unit->allows_decimals : null);
        $data['is_active'] = $request->boolean('is_active');

        $unit->update($data);

        return back()->with('status', __('catalog.units.updated'));
    }

    /** @param  bool|null  $fixedDecimals  règle imposée (unités de mesure), sinon lue du formulaire */
    private function validated(Request $request, ?bool $fixedDecimals = null): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'short_label' => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
            'default_step' => ['required', 'string'],
        ]);

        $allowsDecimals = $fixedDecimals ?? $request->boolean('allows_decimals');
        $step = Quantity::isValid($data['default_step']) ? Quantity::toMilli($data['default_step']) : 0;

        if ($step <= 0 || (! $allowsDecimals && $step % 1000 !== 0)) {
            throw ValidationException::withMessages(['default_step' => __('catalog.rules.step')]);
        }

        return [
            'label' => $data['label'],
            'short_label' => $data['short_label'],
            'description' => $data['description'] ?? null,
            'allows_decimals' => $allowsDecimals,
            'default_step' => Quantity::fromMilli($step),
        ];
    }
}
