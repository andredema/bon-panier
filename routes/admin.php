<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// Espace de gestion : /gestion
Route::prefix('gestion')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('connexion', [LoginController::class, 'create'])->name('login');
        Route::post('connexion', [LoginController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth', 'role'])->name('admin.')->group(function () {
        Route::post('deconnexion', [LoginController::class, 'destroy'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');

        // Toute l'équipe voit le catalogue ; la modification est contrôlée par la permission « edit-catalog »
        Route::resource('produits', ProductController::class)
            ->except(['show'])
            ->parameters(['produits' => 'product'])
            ->names('products');

        Route::middleware('role:gerant')->group(function () {
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
