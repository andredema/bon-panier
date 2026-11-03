<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Accueil de l'espace de gestion ; les indicateurs arrivent à l'étape 6. */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard');
    }
}
