<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
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

        Route::middleware('role:gerant')->group(function () {
            Route::resource('equipe', UserController::class)
                ->except(['show', 'destroy'])
                ->parameters(['equipe' => 'user'])
                ->names('users');
        });
    });
});
