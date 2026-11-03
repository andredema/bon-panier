<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpectedArrival;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\Quantity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExpectedArrivalController extends Controller
{
    public function index(): View
    {
        return view('admin.stock.arrivals', [
            'open' => ExpectedArrival::open()->with(['product.stockUnit', 'supplier'])->orderBy('expected_on')->get(),
            'recent' => ExpectedArrival::where('status', '!=', ExpectedArrival::OPEN)
                ->with(['product', 'supplier'])->latest('updated_at')->limit(10)->get(),
            'products' => Product::with('stockUnit')->orderBy('name')->get(),
            'suppliers' => Supplier::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')],
            'expected_on' => ['required', 'date_format:Y-m-d'],
            'qty' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['expected_on'] < today()->toDateString()) {
            throw ValidationException::withMessages(['expected_on' => __('stock.arrivals.past_date')]);
        }

        $qty = null;
        if (filled($data['qty'] ?? null)) {
            if (! Quantity::isValid($data['qty']) || Quantity::toMilli($data['qty']) <= 0) {
                throw ValidationException::withMessages(['qty' => __('stock.rules.qty')]);
            }
            $qty = Quantity::fromMilli(Quantity::toMilli($data['qty']));
        }

        ExpectedArrival::create(['qty' => $qty, 'status' => ExpectedArrival::OPEN] + $data);

        return back()->with('status', __('stock.arrivals.created'));
    }

    public function cancel(ExpectedArrival $arrival): RedirectResponse
    {
        abort_unless($arrival->status === ExpectedArrival::OPEN, 404);
        $arrival->update(['status' => ExpectedArrival::CANCELLED]);

        return back()->with('status', __('stock.arrivals.cancelled'));
    }
}
