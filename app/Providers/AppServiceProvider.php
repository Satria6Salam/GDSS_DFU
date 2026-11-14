<?php

namespace App\Providers;

use App\Models\DecisionSession;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Sesi aktif untuk menu navbar: hanya ada satu sesi yang belum FINAL (S0); bila tidak ada, sesi terakhir.
        View::composer('components.layouts.app', function ($view) {
            $view->with('currentSession', DecisionSession::latest('id')->first());
        });
    }
}
