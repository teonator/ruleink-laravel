<?php

declare(strict_types=1);

namespace RuleInk\Laravel;

use Illuminate\Database\Eloquent\Model;
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
