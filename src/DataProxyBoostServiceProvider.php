<?php

namespace AdroSoftware\DataProxyBoost;

use Illuminate\Support\ServiceProvider;

class DataProxyBoostServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Boost auto-discovers resources/boost/ directory
    }
}
