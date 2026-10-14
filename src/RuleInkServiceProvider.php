<?php

declare(strict_types=1);

namespace RuleInk\Laravel;

use DateTimeZone;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use RuleInk\Evaluator;
use RuleInk\RuleInk;
use RuleInk\ValueReader;

final class RuleInkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Laravel stores datetimes without an offset, in the app timezone.
        $this->app->singleton(ValueReader::class, fn (Application $app): ValueReader => new ValueReader(
            new DateTimeZone($app['config']->get('app.timezone', 'UTC')),
        ));
        $this->app->singleton(RuleInk::class, fn (Application $app): RuleInk => new RuleInk(
            evaluator: new Evaluator(new EloquentValueResolver(), $app->make(ValueReader::class)),
            adapters: [new EloquentDatasourceAdapter(new ConditionCompiler($app->make(ValueReader::class)))],
        ));
        $this->app->singleton(EloquentRuleInk::class);
    }
}
