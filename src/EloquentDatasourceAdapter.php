<?php

declare(strict_types=1);

namespace RuleInk\Laravel;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use RuleInk\ApplyRefused;
use RuleInk\ConfiguredExpression;
use RuleInk\DatasourceAdapter;
use RuleInk\ExpressionDefinition;

/**
 * Applies generated expressions to Eloquent and query builders as where clauses, without running the query.
 *
 * Results match evaluate() for data Laravel writes itself. Known limits: SQLite datetimes stored in another format
 * (a T separator, trailing zero microseconds, an offset) compare as text; a local time in the hour repeated when
 * clocks go back can sort differently; a global scope that changes the FROM table breaks the column qualifier.
 */
final class EloquentDatasourceAdapter implements DatasourceAdapter
{
    /** @var array<string, QueryDialect> by connection driver name */
    private array $dialects;

    /**
     * @param ?array<string, QueryDialect> $dialects by driver name; only drivers whose SQL the parity tests run
     *                                              against, so others cannot drift from evaluate() unnoticed
     */
    public function __construct(
        private ConditionCompiler $compiler = new ConditionCompiler(),
        ?array $dialects = null,
    ) {
        $this->dialects = $dialects ?? ['sqlite' => new SqliteDialect(), 'mysql' => new MySqlDialect()];
    }

    public function supports(mixed $datasource): bool
    {
        return $datasource instanceof EloquentBuilder || $datasource instanceof QueryBuilder;
    }

    public function canApply(ExpressionDefinition $definition): bool
    {
        return $definition->operator !== null;
    }

    public function apply(
        mixed $datasource,
        ExpressionDefinition $definition,
        ConfiguredExpression $expression,
        callable $matches,
    ): EloquentBuilder|QueryBuilder {
        $builder = clone $datasource;
        $query = $builder instanceof EloquentBuilder ? $builder->getQuery() : $builder;

        if (!empty($query->unions)) {
            throw new ApplyRefused($expression->key, self::class, 'RuleInk cannot apply expressions to a union query.');
        }

        $dialect = $this->dialectFor($query, $expression->key);

        $this->groupExistingConditions($query);
        $this->compiler->compile(
            $query,
            $dialect,
            $this->qualify($query, $definition->field, $expression->key),
            $definition,
            $expression->values['value'],
        );

        return $builder;
    }

    private function dialectFor(QueryBuilder $query, string $key): QueryDialect
    {
        $driver = $query->getConnection()->getDriverName();

        return $this->dialects[$driver]
            ?? throw new ApplyRefused(
                $key,
                self::class,
                "RuleInk cannot apply expressions to [{$driver}] queries yet.",
            );
    }

    /**
     * Qualifies the field with the query's table or alias, so a join to a table with the same column cannot make
     * it ambiguous.
     */
    private function qualify(QueryBuilder $query, string $field, string $key): string
    {
        $table = $this->tableOf($query);

        if ($table === null && !empty($query->joins)) {
            throw new ApplyRefused(
                $key,
                self::class,
                "RuleInk cannot tell which table [{$field}] belongs to in this query.",
            );
        }

        return $table === null ? $field : "{$table}.{$field}";
    }

    /**
     * The table name or alias the query selects from, without the connection's table prefix: "products as p" and
     * fromSub($subquery, 'p') both give p.
     */
    private function tableOf(QueryBuilder $query): ?string
    {
        if (is_string($query->from)) {
            $parts = preg_split('/\s+as\s+/i', trim($query->from));

            return end($parts);
        }

        if (!$query->from instanceof Expression) {
            return null;
        }

        $from = (string) $query->from->getValue($query->getGrammar());

        if (!preg_match('/\)\s+as\s+(\S+)\s*$/i', $from, $matches)) {
            return null;
        }

        $alias = trim($matches[1], '"`[]');
        $prefix = $query->getConnection()->getTablePrefix();

        return $prefix !== '' && str_starts_with($alias, $prefix) ? substr($alias, strlen($prefix)) : $alias;
    }

    /**
     * Wraps the conditions already on the query in parentheses, so an existing orWhere cannot let rows past the
     * condition added after it.
     */
    private function groupExistingConditions(QueryBuilder $query): void
    {
        if ($query->wheres === []) {
            return;
        }

        $existing = $query->forNestedWhere();
        $existing->wheres = $query->wheres;
        $existing->bindings['where'] = $query->bindings['where'];

        $query->wheres = [];
        $query->bindings['where'] = [];
        $query->addNestedWhereQuery($existing);
    }
}
