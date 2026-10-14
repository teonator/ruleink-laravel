<?php

declare(strict_types=1);

namespace RuleInk\Laravel;

use RuleInk\FieldType;

final class FieldTypeMapper
{
    /**
     * Null means the column is left out of the schema. Queries compare the stored value, so the column decides
     * which field types fit and a cast can only choose among them: a varchar cast to integer is left out, because
     * the database still compares it as text.
     *
     * @param string $driver the connection driver name, such as mysql or sqlsrv
     * @param array{type_name: string, type: string} $column as reported by Schema::getColumns()
     * @param ?string $cast the model's cast for the column, if any
     */
    public function forColumn(string $driver, array $column, ?string $cast = null): ?FieldType
    {
        $types = $this->storedTypes($driver, $column);

        if ($cast === null) {
            return $types[0] ?? null;
        }

        $type = $this->forCast($cast);

        return in_array($type, $types, true) ? $type : null;
    }

    /**
     * @param array{type_name: string, type: string} $column
     * @return list<FieldType> the field types the column can store, the one it has without a cast first
     */
    private function storedTypes(string $driver, array $column): array
    {
        $typeName = strtolower($column['type_name']);

        // On SQL Server, Laravel stores booleans as bit, and timestamp is a binary rowversion, not a date.
        if ($driver === 'sqlsrv' && $typeName === 'bit') {
            return [FieldType::Boolean];
        }

        if ($driver === 'sqlsrv' && $typeName === 'timestamp') {
            return [];
        }

        // MySQL and SQLite store Laravel booleans as tinyint(1).
        if ($typeName === 'tinyint' && str_starts_with(strtolower($column['type']), 'tinyint(1)')) {
            return [FieldType::Boolean, FieldType::Number];
        }

        // A date column holds no time, so it is not a datetime field: SQLite would compare 2026-10-08 as text,
        // before 2026-10-08 00:00:00.
        return match ($typeName) {
            'char', 'varchar', 'bpchar', 'nchar', 'nvarchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'ntext',
            'citext', 'enum', 'uuid', 'uniqueidentifier' => [FieldType::String],
            'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'int2', 'int4',
            'int8' => [FieldType::Number, FieldType::Boolean],
            'decimal', 'numeric', 'float', 'float4', 'float8', 'double', 'real' => [FieldType::Number],
            'bool', 'boolean' => [FieldType::Boolean],
            'date' => [FieldType::Date],
            'datetime', 'datetime2', 'smalldatetime', 'datetimeoffset', 'timestamp',
            'timestamptz' => [FieldType::DateTime, FieldType::Date],
            default => [],
        };
    }

    /**
     * The timestamp cast is left out: it returns a Unix integer, which date expressions do not read.
     */
    private function forCast(string $cast): ?FieldType
    {
        return match (strtolower(explode(':', $cast, 2)[0])) {
            'string' => FieldType::String,
            'int', 'integer', 'float', 'double', 'real', 'decimal' => FieldType::Number,
            'bool', 'boolean' => FieldType::Boolean,
            'date', 'immutable_date' => FieldType::Date,
            'datetime', 'immutable_datetime', 'custom_datetime' => FieldType::DateTime,
            default => null,
        };
    }
}
