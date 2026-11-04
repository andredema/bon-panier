<?php

use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\TrackingController;
use App\Http\Controllers\Shop\UnsubscribeController;
use Illuminate\Support\Facades\Route;

// Page de commande publique
Route::get('/', HomeController::class)->name('shop.home');

// Panier (JSON) : limité pour éviter les abus
Route::middleware('throttle:cart')->prefix('panier')->name('shop.cart.')->group(function () {
    Route::get('/', [CartController::class, 'show'])->name('show');
    Route::post('lignes', [CartController::class, 'add'])->name('add');
    Route::patch('lignes/{format}', [CartController::class, 'update'])->whereNumber('format')->name('update');
    Route::delete('lignes/{format}', [CartController::class, 'destroy'])->whereNumber('format')->name('destroy');
});

Route::post('commande', [CheckoutController::class, 'store'])->middleware('throttle:orders')->name('shop.checkout');
Route::get('commande/{order}/merci', [CheckoutController::class, 'confirmation'])->name('shop.confirmation');
Route::get('suivi/{order}', TrackingController::class)->middleware('signed')->name('shop.track');

// Infolettre : désabonnement en un clic depuis le lien signé des courriels
Route::get('desabonnement/{customer}', UnsubscribeController::class)->middleware('signed')->name('shop.unsubscribe');

Route::view('confidentialite', 'shop.privacy')->name('shop.privacy');
