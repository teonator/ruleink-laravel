<?php

declare(strict_types=1);

namespace RuleInk\Laravel;

use RuleInk\FieldType;

final class FieldTypeMapper
{
    /**
     * Null means the column is left out of the schema. A cast decides alone because it changes the value the model
     * returns: a varchar cast to array is not a string field.
     *
     * @param string $driver the connection driver name, such as mysql or sqlsrv
     * @param array{type_name: string, type: string} $column as reported by Schema::getColumns()
     * @param ?string $cast the model's cast for the column, if any
     */
    public function forColumn(string $driver, array $column, ?string $cast = null): ?FieldType
    {
        if ($cast !== null) {
            return $this->forCast($cast);
        }

        $typeName = strtolower($column['type_name']);

        // On SQL Server, Laravel stores booleans as bit, and timestamp is a binary rowversion, not a date.
        if ($driver === 'sqlsrv' && $typeName === 'bit') {
            return FieldType::Boolean;
        }

        if ($driver === 'sqlsrv' && $typeName === 'timestamp') {
            return null;
        }

        // MySQL and SQLite store Laravel booleans as tinyint(1).
        if ($typeName === 'tinyint' && str_starts_with(strtolower($column['type']), 'tinyint(1)')) {
            return FieldType::Boolean;
        }

        return match ($typeName) {
            'char', 'varchar', 'bpchar', 'nchar', 'nvarchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'ntext',
            'citext', 'enum', 'uuid', 'uniqueidentifier' => FieldType::String,
            'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'int2', 'int4', 'int8', 'decimal',
            'numeric', 'float', 'float4', 'float8', 'double', 'real' => FieldType::Number,
            'bool', 'boolean' => FieldType::Boolean,
            'date' => FieldType::Date,
            'datetime', 'datetime2', 'smalldatetime', 'datetimeoffset', 'timestamp',
            'timestamptz' => FieldType::DateTime,
            default => null,
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
