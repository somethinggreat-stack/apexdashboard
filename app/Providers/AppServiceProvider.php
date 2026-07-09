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
         * Admin pages that don't have a dedicated pro template (Messages,
         * Payments) still pick up the super-admin console chrome by extending
         * $adminLayout. Everyone else keeps the original layout.
         */
        View::composer('admin.*', function ($view) {
            $me = Auth::guard('admin')->user();

            $view->with('adminLayout', $me && $me->isSuper()
                ? 'layouts.admin-pro'
                : 'layouts.admin');
        });
    }
}
