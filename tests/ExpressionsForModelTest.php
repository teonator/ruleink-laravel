<?php

declare(strict_types=1);

namespace RuleInk\Laravel\Tests;

use InvalidArgumentException;
use RuleInk\Laravel\Facades\RuleInk;
use RuleInk\Laravel\Tests\Fixtures\Order;
use RuleInk\Laravel\Tests\Fixtures\Product;

final class ExpressionsForModelTest extends TestCase
{
    public function testGeneratesExpressionsFromAModelsColumns(): void
    {
        $keys = array_column(RuleInk::expressionsFor(Product::class), 'key');

        $this->assertContains('price.gt', $keys);
    }

    public function testACastDecidesTheFieldType(): void
    {
        $keys = array_column(RuleInk::expressionsFor(Product::class), 'key');

        $this->assertContains('active.is', $keys);
        $this->assertNotContains('active.gt', $keys);
        $this->assertEmpty(preg_grep('/^options\./', $keys));
    }

    public function testLeavesOutHiddenAttributes(): void
    {
        $keys = array_column(RuleInk::expressionsFor(Product::class), 'key');

        $this->assertContains('price.gt', $keys);
        $this->assertEmpty(preg_grep('/^secret\./', $keys));
    }

    public function testANameThatIsNotAModelIsLeftToRuleInk(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown schema [product].');

        RuleInk::expressionsFor('product');
    }

    public function testInspectsAModelOnce(): void
    {
        $this->assertSame(RuleInk::expressionsFor(Product::class), RuleInk::expressionsFor(Product::class));
    }

    public function testAModelWithoutHasRuleInkIsNotInspected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown schema [' . Order::class . '].');

        RuleInk::expressionsFor(Order::class);
    }
}
