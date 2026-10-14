<?php

declare(strict_types=1);

namespace RuleInk\Laravel;

use Illuminate\Database\Eloquent\Model;
use RuleInk\DefaultValueResolver;
use RuleInk\ValueResolver;

/**
 * Reads a model's stored attribute, not the value its cast returns, so evaluate() sees what a query compares.
 */
final class EloquentValueResolver implements ValueResolver
{
    public function __construct(
        private ValueResolver $fallback = new DefaultValueResolver(),
    ) {
    }

    public function get(mixed $subject, string $path): mixed
    {
        if ($subject instanceof Model) {
            return $subject->getAttributes()[$path] ?? null;
        }

        return $this->fallback->get($subject, $path);
    }
}
