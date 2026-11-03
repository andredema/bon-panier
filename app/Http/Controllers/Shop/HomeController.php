<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Page de commande publique ; le catalogue et le panier arrivent à l'étape 4. */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('shop.home');
    }
}
