<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\OrderPlacement;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function store(Request $request, OrderPlacement $placement): RedirectResponse
    {
        try {
            $order = $placement->place($request->all());
        } catch (ValidationException $e) {
            // Le tiroir de finalisation se rouvre avec les erreurs et les champs saisis
            return redirect()->route('shop.home')
                ->withErrors($e->errors())
                ->withInput($request->except('consent'))
                ->with('open_checkout', true);
        }

        $request->session()->put('last_order', $order->number);

        return redirect()->route('shop.confirmation', $order);
    }

    /** Page de confirmation : visible seulement dans la session qui a passé la commande. */
    public function confirmation(Request $request, Order $order): View
    {
        abort_unless($request->session()->get('last_order') === $order->number, 404);

        return view('shop.confirmation', [
            'order' => $order->load(['items', 'customer']),
            'interacEmail' => Settings::get('interac.email'),
        ]);
    }
}
