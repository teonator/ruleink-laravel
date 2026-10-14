<?php

declare(strict_types=1);

namespace RuleInk\Laravel;

final class MySqlDialect implements QueryDialect
{
    /**
     * MySQL's usual collations ignore case and, with PAD SPACE, trailing spaces.
     */
    public function bytes(string $sql): string
    {
        return "CAST({$sql} AS BINARY)";
    }

    public function isNumber(string $sql): ?string
    {
        return null;
    }
}
