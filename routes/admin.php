<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\PasswordResetController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CounterOrderController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExpectedArrivalController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PreorderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StockAdjustmentController;
use App\Http\Controllers\Admin\StockReceiptController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// Espace de gestion : /gestion
Route::prefix('gestion')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('connexion', [LoginController::class, 'create'])->name('login');
        Route::post('connexion', [LoginController::class, 'store'])->name('login.store');

        // Mot de passe oublié
        Route::get('mot-de-passe', [PasswordResetController::class, 'request'])->name('password.request');
        Route::post('mot-de-passe', [PasswordResetController::class, 'email'])->middleware('throttle:6,10')->name('password.email');
        Route::get('mot-de-passe/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
        Route::post('mot-de-passe/nouveau', [PasswordResetController::class, 'update'])->middleware('throttle:6,10')->name('password.update');
    });

    Route::middleware(['auth', 'role'])->name('admin.')->group(function () {
        Route::post('deconnexion', [LoginController::class, 'destroy'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');

        // Toute l'équipe voit le catalogue ; la modification est contrôlée par la permission « edit-catalog »
        Route::resource('produits', ProductController::class)
            ->except(['show'])
            ->parameters(['produits' => 'product'])
            ->names('products');

        // Stock : toute l'équipe reçoit les arrivages
        Route::get('entrees', [StockReceiptController::class, 'index'])->name('receipts.index');
        Route::get('entrees/nouvelle', [StockReceiptController::class, 'create'])->name('receipts.create');
        Route::get('entrees/produits', [StockReceiptController::class, 'products'])->name('receipts.products');
        Route::post('entrees', [StockReceiptController::class, 'store'])->name('receipts.store');
        Route::get('entrees/{receipt}', [StockReceiptController::class, 'show'])->whereNumber('receipt')->name('receipts.show');
        Route::get('entrees/{receipt}/etiquettes', [StockReceiptController::class, 'labels'])->whereNumber('receipt')->name('receipts.labels');

        Route::get('arrivages', [ExpectedArrivalController::class, 'index'])->name('arrivals.index');
        Route::post('arrivages', [ExpectedArrivalController::class, 'store'])->name('arrivals.store');
        Route::patch('arrivages/{arrival}/annuler', [ExpectedArrivalController::class, 'cancel'])->name('arrivals.cancel');

        // Commandes : toute l'équipe les traite
        Route::get('commandes', [OrderController::class, 'index'])->name('orders.index');
        Route::get('commandes/nouvelle', [CounterOrderController::class, 'create'])->name('orders.create');
        Route::get('commandes/produits', [CounterOrderController::class, 'products'])->name('orders.products');
        Route::post('commandes/apercu', [CounterOrderController::class, 'preview'])->name('orders.preview');
        Route::post('commandes', [CounterOrderController::class, 'store'])->name('orders.store');
        Route::get('commandes/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('commandes/{order}/suivant', [OrderController::class, 'advance'])->name('orders.advance');
        Route::post('commandes/{order}/annuler', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::get('commandes/{order}/preparation', [OrderController::class, 'picking'])->name('orders.picking');

        // Facturation : toute l'équipe encaisse ; la suppression d'un paiement est réservée au gérant
        Route::get('factures', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('factures/{invoice:number}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('factures/{invoice:number}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::post('factures/{invoice:number}/courriel', [InvoiceController::class, 'email'])->name('invoices.email');
        Route::get('factures/{invoice:number}/note-de-credit', [InvoiceController::class, 'createCredit'])->name('invoices.credit');
        Route::post('factures/{invoice:number}/note-de-credit', [InvoiceController::class, 'storeCredit'])->name('invoices.credit.store');
        Route::get('notes-de-credit/{creditNote:number}/pdf', [InvoiceController::class, 'creditPdf'])->name('credit-notes.pdf');
        Route::get('paiements/interac', [PaymentController::class, 'reconcile'])->name('payments.reconcile');
        Route::post('commandes/{order}/paiements', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('paiements/{payment}/recu', [PaymentController::class, 'receipt'])->name('payments.receipt');
        Route::delete('paiements/{payment}', [PaymentController::class, 'destroy'])->middleware('role:gerant')->name('payments.destroy');

        Route::get('precommandes', [PreorderController::class, 'index'])->name('preorders.index');
        Route::post('precommandes/{product}/servir', [PreorderController::class, 'serve'])->name('preorders.serve');

        Route::resource('fournisseurs', SupplierController::class)
            ->only(['index', 'store', 'update'])
            ->parameters(['fournisseurs' => 'supplier'])
            ->names('suppliers');

        // Clients : toute l'équipe consulte et corrige ; export et effacement (Loi 25) réservés au gérant
        Route::get('clients', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('clients/{customer}', [CustomerController::class, 'show'])->whereNumber('customer')->name('customers.show');
        Route::put('clients/{customer}', [CustomerController::class, 'update'])->whereNumber('customer')->name('customers.update');
        Route::get('clients/{customer}/export', [CustomerController::class, 'export'])->middleware('role:gerant')->name('customers.export');
        Route::post('clients/{customer}/effacer', [CustomerController::class, 'erase'])->middleware('role:gerant')->name('customers.erase');

        Route::get('lots/{lot}/ajuster', [StockAdjustmentController::class, 'create'])->name('adjustments.create');
        Route::post('lots/{lot}/ajuster', [StockAdjustmentController::class, 'store'])->name('adjustments.store');

        Route::middleware('role:gerant')->group(function () {
            Route::get('rapports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('rapports/csv/{type}', [ReportController::class, 'csv'])->name('reports.csv');
            Route::get('rapports/pdf/{type}', [ReportController::class, 'pdf'])->name('reports.pdf');
            Route::post('rapports/envoyer', [ReportController::class, 'send'])->name('reports.send');

            Route::get('reglages', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('reglages', [SettingsController::class, 'update'])->name('settings.update');

            Route::resource('reglages/categories', CategoryController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->parameters(['categories' => 'category'])
                ->names('settings.categories');

            Route::resource('reglages/unites', UnitController::class)
                ->only(['index', 'store', 'update'])
                ->parameters(['unites' => 'unit'])
                ->names('settings.units');

            Route::resource('equipe', UserController::class)
                ->except(['show', 'destroy'])
                ->parameters(['equipe' => 'user'])
                ->names('users');
        });
    });
});
