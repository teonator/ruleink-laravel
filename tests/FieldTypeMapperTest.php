<?php

declare(strict_types=1);

namespace RuleInk\Laravel\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuleInk\FieldType;
use RuleInk\Laravel\FieldTypeMapper;

final class FieldTypeMapperTest extends TestCase
{
    /**
     * Column types are the type_name and type that Schema::getColumns() reports on each connection driver.
     */
    #[DataProvider('columns')]
    public function testMapsAColumnType(string $driver, string $typeName, string $type, ?FieldType $expected): void
    {
        $column = ['type_name' => $typeName, 'type' => $type];

        $this->assertSame($expected, (new FieldTypeMapper())->forColumn($driver, $column));
    }

    /**
     * @return array<string, array{string, string, string, ?FieldType}>
     */
    public static function columns(): array
    {
        return [
            'mysql varchar' => ['mysql', 'varchar', 'varchar(255)', FieldType::String],
            'mysql char' => ['mysql', 'char', 'char(36)', FieldType::String],
            'mysql longtext' => ['mysql', 'longtext', 'longtext', FieldType::String],
            'mysql enum' => ['mysql', 'enum', "enum('a','b')", FieldType::String],
            'pgsql bpchar' => ['pgsql', 'bpchar', 'character(36)', FieldType::String],
            'pgsql uuid' => ['pgsql', 'uuid', 'uuid', FieldType::String],
            'sqlsrv nvarchar' => ['sqlsrv', 'nvarchar', 'nvarchar(255)', FieldType::String],
            'sqlite text' => ['sqlite', 'text', 'text', FieldType::String],
            'mysql int' => ['mysql', 'int', 'int unsigned', FieldType::Number],
            'mysql bigint' => ['mysql', 'bigint', 'bigint unsigned', FieldType::Number],
            'mysql tinyint' => ['mysql', 'tinyint', 'tinyint unsigned', FieldType::Number],
            'mysql decimal' => ['mysql', 'decimal', 'decimal(10,2)', FieldType::Number],
            'mysql double' => ['mysql', 'double', 'double', FieldType::Number],
            'pgsql int8' => ['pgsql', 'int8', 'bigint', FieldType::Number],
            'pgsql numeric' => ['pgsql', 'numeric', 'numeric(10,2)', FieldType::Number],
            'pgsql float8' => ['pgsql', 'float8', 'double precision', FieldType::Number],
            'sqlite integer' => ['sqlite', 'integer', 'integer', FieldType::Number],
            'sqlite numeric' => ['sqlite', 'numeric', 'numeric', FieldType::Number],
            'sqlite real' => ['sqlite', 'real', 'real', FieldType::Number],
            'mysql boolean' => ['mysql', 'tinyint', 'tinyint(1)', FieldType::Boolean],
            'sqlite boolean' => ['sqlite', 'tinyint', 'tinyint(1)', FieldType::Boolean],
            'pgsql bool' => ['pgsql', 'bool', 'boolean', FieldType::Boolean],
            'mysql date' => ['mysql', 'date', 'date', FieldType::Date],
            'mysql datetime' => ['mysql', 'datetime', 'datetime', FieldType::DateTime],
            'mysql timestamp' => ['mysql', 'timestamp', 'timestamp', FieldType::DateTime],
            'pgsql timestamptz' => ['pgsql', 'timestamptz', 'timestamp(0) with time zone', FieldType::DateTime],
            'sqlsrv datetime2' => ['sqlsrv', 'datetime2', 'datetime2(0)', FieldType::DateTime],
            'sqlsrv datetimeoffset' => ['sqlsrv', 'datetimeoffset', 'datetimeoffset(0)', FieldType::DateTime],
            'mysql time' => ['mysql', 'time', 'time', null],
            'mysql json' => ['mysql', 'json', 'json', null],
            'pgsql jsonb' => ['pgsql', 'jsonb', 'jsonb', null],
            'sqlite blob' => ['sqlite', 'blob', 'blob', null],
            'pgsql bytea' => ['pgsql', 'bytea', 'bytea', null],
            'pgsql money' => ['pgsql', 'money', 'money', null],
            'mysql bit is a bit field' => ['mysql', 'bit', 'bit(1)', null],
            'sqlsrv bit is a boolean' => ['sqlsrv', 'bit', 'bit', FieldType::Boolean],
            'sqlsrv uniqueidentifier' => ['sqlsrv', 'uniqueidentifier', 'uniqueidentifier', FieldType::String],
            'sqlsrv timestamp is a rowversion' => ['sqlsrv', 'timestamp', 'timestamp', null],
            'mysql uppercase' => ['mysql', 'VARCHAR', 'VARCHAR(255)', FieldType::String],
        ];
    }

    #[DataProvider('casts')]
    public function testACastDecidesOverTheColumnType(string $cast, ?FieldType $expected): void
    {
        $column = ['type_name' => 'varchar', 'type' => 'varchar(255)'];

        $this->assertSame($expected, (new FieldTypeMapper())->forColumn('mysql', $column, $cast));
    }

    /**
     * @return array<string, array{string, ?FieldType}>
     */
    public static function casts(): array
    {
        return [
            'string' => ['string', FieldType::String],
            'int' => ['int', FieldType::Number],
            'integer' => ['integer', FieldType::Number],
            'float' => ['float', FieldType::Number],
            'double' => ['double', FieldType::Number],
            'real' => ['real', FieldType::Number],
            'decimal with places' => ['decimal:2', FieldType::Number],
            'bool' => ['bool', FieldType::Boolean],
            'boolean' => ['boolean', FieldType::Boolean],
            'date' => ['date', FieldType::Date],
            'date with format' => ['date:Y-m-d', FieldType::Date],
            'immutable_date' => ['immutable_date', FieldType::Date],
            'datetime' => ['datetime', FieldType::DateTime],
            'datetime with format' => ['datetime:Y-m-d H:i', FieldType::DateTime],
            'immutable_datetime' => ['immutable_datetime', FieldType::DateTime],
            'custom_datetime' => ['custom_datetime', FieldType::DateTime],
            'timestamp is an integer' => ['timestamp', null],
            'array' => ['array', null],
            'json' => ['json', null],
            'collection' => ['collection', null],
            'encrypted' => ['encrypted', null],
            'hashed' => ['hashed', null],
            'cast class' => ['App\Casts\Money', null],
        ];
    }
}
