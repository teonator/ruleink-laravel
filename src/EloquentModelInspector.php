<?php

declare(strict_types=1);

namespace RuleInk\Laravel;

use Illuminate\Database\Eloquent\Model;
use RuleInk\Field;
use RuleInk\Schema;

final class EloquentModelInspector
{
    public function __construct(
        private FieldTypeMapper $mapper = new FieldTypeMapper(),
    ) {
    }

    /**
     * @param class-string<Model> $model
     */
    public function schemaFor(string $model): Schema
    {
        $instance = new $model();
        $connection = $instance->getConnection();
        $columns = $connection->getSchemaBuilder()->getColumns($instance->getTable());
        $casts = $instance->getCasts();
        $hidden = $instance->getHidden();
        $fields = [];

        foreach ($columns as $column) {
            // Authors see every field, so attributes the model hides stay out.
            if (in_array($column['name'], $hidden, true)) {
                continue;
            }

            $type = $this->mapper->forColumn($connection->getDriverName(), $column, $casts[$column['name']] ?? null);

            if ($type !== null) {
                $fields[] = new Field($column['name'], $type);
            }
        }

        return new Schema($model, $fields);
    }
}
