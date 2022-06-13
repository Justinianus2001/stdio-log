<?php

namespace Justinianus\StdioLog;

use Illuminate\Support\ServiceProvider;

class StdioServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ .  '/../routes/web.php');
    }
}