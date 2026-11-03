<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Customers\CustomerPrivacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $type = in_array($request->query('type'), Customer::TYPES, true) ? $request->query('type') : null;

        $customers = Customer::query()
            ->whereNull('erased_at')
            ->with('invoices')
            ->withCount('orders')
            ->withMax('orders', 'created_at')
            ->when($q !== '', fn ($w) => $w->search($q))
            ->when($type, fn ($w) => $w->where('type', $type))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.customers.index', compact('customers', 'q', 'type'));
    }

    public function show(Customer $customer, CustomerPrivacy $privacy): View
    {
        $customer->load(['orders' => fn ($o) => $o->latest()->with('invoice'), 'invoices']);

        return view('admin.customers.show', [
            'customer' => $customer,
            'balance' => $customer->balanceCents(),
            'blocker' => $privacy->blocker($customer),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        abort_if($customer->isErased(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('customers')->ignore($customer)],
            'address' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(Customer::TYPES)],
        ]);
        $data['email'] = isset($data['email']) ? mb_strtolower($data['email']) : null;

        // Seul le gérant approuve l'accès aux prix de gros
        if ($request->user()->isManager()) {
            $data['approved_wholesale'] = $request->boolean('approved_wholesale');
        }

        $customer->update($data);

        return back()->with('status', __('customers.updated'));
    }

    /** Loi 25, droit d'accès : tout ce qu'on détient sur ce client, en JSON lisible. */
    public function export(Customer $customer, CustomerPrivacy $privacy): JsonResponse
    {
        return response()->json($privacy->export($customer), 200, [
            'Content-Disposition' => 'attachment; filename=client-'.$customer->id.'-renseignements.json',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Loi 25, droit à l'effacement : coordonnées supprimées, factures conservées. */
    public function erase(Request $request, Customer $customer, CustomerPrivacy $privacy): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => __('customers.erase.confirm_required')]);

        if ($reason = $privacy->blocker($customer)) {
            return back()->withErrors(['erase' => $reason]);
        }

        $privacy->erase($customer, $request->user());

        return redirect()->route('admin.customers.index')->with('status', __('customers.erase.done'));
    }
}
