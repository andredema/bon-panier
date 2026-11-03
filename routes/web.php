<?php

use App\Http\Controllers\Shop\HomeController;
use Illuminate\Support\Facades\Route;

// Page de commande publique
Route::get('/', HomeController::class)->name('shop.home');
