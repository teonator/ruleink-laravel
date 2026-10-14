<?php

declare(strict_types=1);

namespace RuleInk\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use RuleInk\AuthorError;
use RuleInk\ExpressionDefinition;
use RuleInk\Laravel\EloquentRuleInk;

/**
 * @method static list<ExpressionDefinition> expressionsFor(string $schema)
 * @method static void define(string $schema, ExpressionDefinition $definition, callable $evaluator)
 * @method static list<AuthorError> validate(string $schema, string $key, array<array-key, mixed> $values = [])
 * @method static bool evaluate(string $schema, mixed $subject, string $key, array<array-key, mixed> $values = [])
 * @method static mixed apply(string $schema, mixed $datasource, string $key, array<array-key, mixed> $values = [])
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
