<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('fr_CA');

        // Erreurs visibles en développement (attributs inconnus, chargements paresseux)
        Model::shouldBeStrict(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceHttps();
        }

        // Limites d'envoi (sécurité, cahier 9) : panier et commandes
        RateLimiter::for('cart', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));
        RateLimiter::for('orders', fn (Request $r) => Limit::perMinutes(10, 5)->by($r->ip()));

        // Modifier les produits, formats et prix : le gérant, et les employés si le réglage le permet
        Gate::define('edit-catalog', fn (User $user) => $user->isManager()
            || ($user->is_active && Settings::get('catalog.employees_can_edit')));

        // @money($cents) → 1 284,50 $ · @date($d) → 2 novembre 2026 · @qty('1.500') → 1,5
        Blade::directive('money', fn ($e) => "<?php echo e(\\App\\Support\\Format::money($e)); ?>");
        Blade::directive('date', fn ($e) => "<?php echo e(\\App\\Support\\Format::date($e)); ?>");
        Blade::directive('longdate', fn ($e) => "<?php echo e(\\App\\Support\\Format::longDate($e)); ?>");
        Blade::directive('qty', fn ($e) => "<?php echo e(\\App\\Support\\Format::quantity($e)); ?>");
    }
}
