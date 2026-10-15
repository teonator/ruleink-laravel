<?php

declare(strict_types=1);

namespace RuleInk\Laravel;

use Illuminate\Database\Eloquent\Model;
use RuleInk\ApplyRefused;
use RuleInk\AuthorError;
use RuleInk\ExpressionDefinition;
use RuleInk\RuleInk;

/**
 * RuleInk that also accepts the class name of a model using HasRuleInk as a schema name, registering the model's
 * schema the first time it is used.
 */
final class EloquentRuleInk
{
    /** @var array<class-string<Model>, true> */
    private array $registered = [];

    public function __construct(
        private RuleInk $ruleink,
        private EloquentModelInspector $inspector,
    ) {
    }

    /**
     * @return list<ExpressionDefinition>
     */
    public function expressionsFor(string $schema): array
    {
        $this->registerModel($schema);

        return $this->ruleink->expressionsFor($schema);
    }

    /**
     * @param callable(mixed $subject, array<string, mixed> $values): bool $evaluator
     */
    public function define(string $schema, ExpressionDefinition $definition, callable $evaluator): void
    {
        $this->registerModel($schema);
        $this->ruleink->define($schema, $definition, $evaluator);
    }

    /**
     * @param array<array-key, mixed> $values
     * @return list<AuthorError>
     */
    public function validate(string $schema, string $key, array $values = []): array
    {
        $this->registerModel($schema);

        return $this->ruleink->validate($schema, $key, $values);
    }

    /**
     * @param array<array-key, mixed> $values
     */
    public function evaluate(string $schema, mixed $subject, string $key, array $values = []): bool
    {
        $this->registerModel($schema);

        return $this->ruleink->evaluate($schema, $subject, $key, $values);
    }

    /**
     * @param array<array-key, mixed> $values
     * @throws ApplyRefused when the adapter cannot apply this configured expression to this datasource
     */
    public function apply(string $schema, mixed $datasource, string $key, array $values = []): mixed
    {
        $this->registerModel($schema);

        return $this->ruleink->apply($schema, $datasource, $key, $values);
    }

    private function registerModel(string $schema): void
    {
        if (
            isset($this->registered[$schema])
            || !class_exists($schema)
            || !in_array(HasRuleInk::class, class_uses_recursive($schema), true)
        ) {
            return;
        }

        $this->ruleink->register($this->inspector->schemaFor($schema));
        $this->registered[$schema] = true;
    }
}
