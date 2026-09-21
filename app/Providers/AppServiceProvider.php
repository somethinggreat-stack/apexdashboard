<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * JARVIS is one assistant on one machine: 60 requests a minute is plenty
         * for it and useless for anything trying to walk the client list. Keyed by
         * IP because there is no user session behind this API.
         */
        RateLimiter::for('jarvis', fn (Request $request) => Limit::perMinute(
            (int) config('jarvis.rate_limit', 60)
        )->by($request->ip()));

        // Mutations get their own, much lower ceiling than reads.
        RateLimiter::for('jarvis-write', fn (Request $request) => Limit::perMinute(
            (int) config('jarvis.write_rate_limit', 10)
        )->by($request->ip()));

        /**
         * OwnerSnapshot needs to know whose console it is describing. The dashboard
         * passes the signed-in admin; the JARVIS API has no session at all, so an
         * injected one resolves to the owner (the super admin). Controllers that
         * mean a specific admin must keep calling OwnerSnapshot::forAdmin($admin).
         */
        $this->app->bind(
            \App\Services\OwnerSnapshot::class,
            fn () => \App\Services\OwnerSnapshot::forSuperAdmin()
        );

        /**
         * The pro console is used by the super admin AND VAs, so their pages
         * look identical. Leads agents (sales pipeline only) keep the original
         * layout. Pages without a dedicated pro template still pick up the pro
         * chrome by extending $adminLayout.
         */
        View::composer('admin.*', function ($view) {
            $me = Auth::guard('admin')->user();

            $view->with('adminLayout', $me && ! $me->isLeads()
                ? 'layouts.admin-pro'
                : 'layouts.admin');
        });
    }
}
