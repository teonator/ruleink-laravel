<?php

declare(strict_types=1);

namespace RuleInk\Laravel;

/**
 * The SQL that differs between databases. A dialect must make its database compare values the way evaluate() does,
 * which the parity tests check for each driver.
 */
interface QueryDialect
{
    /**
     * Wraps an SQL expression so the database compares it byte by byte, ignoring collations.
     */
    public function bytes(string $sql): string;

    /**
     * A condition that holds only when the SQL expression stores a number, or null when the column type already
     * guarantees it.
     */
    public function isNumber(string $sql): ?string;
}
