<?php

declare(strict_types=1);

namespace RuleInk\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use RuleInk\ExpressionDefinition;
use RuleInk\Laravel\EloquentRuleInk;

/**
 * @method static list<ExpressionDefinition> expressionsFor(string $schema)
 *
 * @see EloquentRuleInk
 */
final class RuleInk extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return EloquentRuleInk::class;
    }
}
