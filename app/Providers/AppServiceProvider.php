<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
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
