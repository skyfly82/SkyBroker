<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->routes(function () {
            // API routes są automatycznie pod /api
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Web (jeśli potrzebne Blade/Tailwind)
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
