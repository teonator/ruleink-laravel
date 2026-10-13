<?php

declare(strict_types=1);

namespace RuleInk\Laravel;

use Illuminate\Support\ServiceProvider;
use RuleInk\RuleInk;

final class RuleInkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RuleInk::class);
        $this->app->singleton(EloquentRuleInk::class);
    }
}
