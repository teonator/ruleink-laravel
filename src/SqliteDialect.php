<?php

declare(strict_types=1);

namespace RuleInk\Laravel;

final class SqliteDialect implements QueryDialect
{
    /**
     * A column can declare NOCASE, and text instr() matches whole characters, not bytes inside them.
     */
    public function bytes(string $sql): string
    {
        return "CAST({$sql} AS BLOB)";
    }

    /**
     * A numeric column can hold text, which SQLite orders after every number.
     */
    public function isNumber(string $sql): ?string
    {
        return "typeof({$sql}) IN ('integer', 'real')";
    }
}
