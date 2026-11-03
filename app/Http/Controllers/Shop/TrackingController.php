<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Settings;
use Illuminate\View\View;

/** Suivi de commande par le lien unique et signé du courriel (middleware « signed »). */
class TrackingController extends Controller
{
    public function __invoke(Order $order): View
    {
        return view('shop.tracking', [
            'order' => $order->load(['items', 'history']),
            'interacEmail' => Settings::get('interac.email'),
        ]);
    }
}
