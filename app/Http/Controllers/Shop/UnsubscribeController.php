<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\View\View;

/** Désabonnement de l'infolettre en un clic, depuis le lien signé des courriels. */
class UnsubscribeController extends Controller
{
    public function __invoke(Customer $customer): View
    {
        $customer->unsubscribe();

        return view('shop.unsubscribed');
    }
}
