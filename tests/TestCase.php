<?php

declare(strict_types=1);

namespace RuleInk\Laravel\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use RuleInk\Laravel\RuleInkServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [RuleInkServiceProvider::class];
    }

    /**
     * Runs on SQLite in memory unless RULEINK_DB=mysql, which reads the RULEINK_MYSQL_* variables.
     */
    protected function defineEnvironment($app): void
    {
        if (getenv('RULEINK_DB') !== 'mysql') {
            return;
        }

        $app['config']->set('database.default', 'mysql');
        $mysql = $app['config']->get('database.connections.mysql');

        $app['config']->set('database.connections.mysql', array_merge($mysql, [
            'host' => getenv('RULEINK_MYSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('RULEINK_MYSQL_PORT') ?: '3306',
            'database' => getenv('RULEINK_MYSQL_DATABASE') ?: 'ruleink_test',
            'username' => getenv('RULEINK_MYSQL_USERNAME') ?: 'root',
            'password' => getenv('RULEINK_MYSQL_PASSWORD') ?: '',
        ]));
    }

    protected function defineDatabaseMigrations(): void
    {
        // A MySQL database keeps tables between tests; SQLite in memory starts empty.
        Schema::dropIfExists('products');
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->decimal('price', 10, 2);
            $table->integer('active');
            $table->text('options');
            $table->string('secret');
        });
    }
}
