<?php

declare(strict_types=1);

namespace RuleInk\Laravel;

use DateTimeImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use RuleInk\ApplyRefused;
use RuleInk\ExpressionDefinition;
use RuleInk\FieldType;
use RuleInk\ValueReader;

/**
 * Turns a generated expression into the where clause that keeps exactly the rows evaluate() matches.
 */
final class ConditionCompiler
{
    private const COMPARISONS = ['eq' => '=', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='];

    /** From here a double cannot hold every integer. */
    private const EXACT_INTEGER_LIMIT = 2 ** 53;

    public function __construct(
        private ValueReader $reader = new ValueReader(),
    ) {
    }

    /**
     * @param string $column the field, qualified with the query's table
     * @throws ApplyRefused for an operator or value the query cannot compare exactly as evaluate() does
     */
    public function compile(
        QueryBuilder $query,
        QueryDialect $dialect,
        string $column,
        ExpressionDefinition $definition,
        mixed $value,
    ): void {
        match ($definition->operator) {
            'eq', 'gt', 'gte', 'lt', 'lte' => $this->number($query, $dialect, $column, $definition, $value),
            'equals', 'contains' => $this->string($query, $dialect, $column, $definition->operator, $value),
            'is' => $query->where($column, '=', $this->reader->boolean($value) ? 1 : 0),
            'before', 'after' => $this->date($query, $column, $definition, $value),
            default => throw new ApplyRefused(
                $definition->key,
                EloquentDatasourceAdapter::class,
                "Operator [{$definition->operator}] is not supported yet.",
            ),
        };
    }

    /**
     * Binds the value as a plain decimal, because Laravel would round a float to about 14 digits. SQLite stores
     * decimals as doubles and MySQL compares a decimal with a string as doubles, so a number a double cannot hold
     * exactly is refused rather than rounded. So is any number from 2^53: SQLite compares a stored double with a bound
     * integer exactly, and MySQL compares an integer column with a string as doubles.
     */
    private function number(
        QueryBuilder $query,
        QueryDialect $dialect,
        string $column,
        ExpressionDefinition $definition,
        int|float|string $value,
    ): void {
        $number = $this->reader->decimal($value);

        if ($number === null || $this->reader->decimal((float) $number) !== $number) {
            throw new ApplyRefused(
                $definition->key,
                EloquentDatasourceAdapter::class,
                "Value [{$value}] has more digits than a query can compare exactly.",
            );
        }

        if (abs((float) $number) >= self::EXACT_INTEGER_LIMIT) {
            throw new ApplyRefused(
                $definition->key,
                EloquentDatasourceAdapter::class,
                "Value [{$value}] is too large for a query to compare exactly.",
            );
        }

        $isNumber = $dialect->isNumber($query->getGrammar()->wrap($column));

        if ($isNumber !== null) {
            $query->whereRaw($isNumber);
        }

        $query->where($column, self::COMPARISONS[$definition->operator], $number);
    }

    /**
     * Compares bytes, as evaluate() does. instr(), not LIKE: SQLite's LIKE ignores ASCII case and treats % and _ as
     * wildcards.
     */
    private function string(
        QueryBuilder $query,
        QueryDialect $dialect,
        string $column,
        string $operator,
        string $value,
    ): void {
        $column = $dialect->bytes($query->getGrammar()->wrap($column));
        $placeholder = $dialect->bytes('?');

        $operator === 'equals'
            ? $query->whereRaw("{$column} = {$placeholder}", [$value])
            : $query->whereRaw("instr({$column}, {$placeholder}) > 0", [$value]);
    }

    /**
     * Datetime fields compare instants, as stored text in the reader's timezone. Date fields compare the calendar
     * day as written.
     */
    private function date(QueryBuilder $query, string $column, ExpressionDefinition $definition, mixed $value): void
    {
        $operator = $definition->operator === 'before' ? '<' : '>';
        $date = $this->reader->dateTime($value);

        if ($definition->fieldType === FieldType::Date) {
            $query->whereDate($column, $operator, $date->format('Y-m-d'));

            return;
        }

        $local = DateTimeImmutable::createFromInterface($date)->setTimezone($this->reader->timezone());
        $format = $local->format('u') === '000000' ? 'Y-m-d H:i:s' : 'Y-m-d H:i:s.u';

        $query->where($column, $operator, $local->format($format));
    }
}
