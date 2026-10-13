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

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->decimal('price', 10, 2);
            $table->integer('active');
            $table->text('options');
            $table->string('secret');
        });
    }
}
