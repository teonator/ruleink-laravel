<?php

declare(strict_types=1);

namespace RuleInk\Laravel\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuleInk\ApplyRefused;
use RuleInk\ExpressionDefinition;
use RuleInk\Field;
use RuleInk\FieldType;
use RuleInk\Laravel\ConditionCompiler;
use RuleInk\Laravel\EloquentDatasourceAdapter;
use RuleInk\Laravel\Facades\RuleInk;
use RuleInk\Laravel\SqliteDialect;
use RuleInk\Laravel\Tests\Fixtures\Product;

final class ApplyToQueryTest extends TestCase
{
    public function testNarrowsAnEloquentBuilder(): void
    {
        $this->products([50, 150, 300]);

        $builder = RuleInk::apply(Product::class, Product::query(), 'price.gt', ['value' => 100]);

        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertSame([2, 3], $builder->orderBy('id')->pluck('id')->all());
    }

    public function testRunsNoQueryAndLeavesTheGivenBuilderAlone(): void
    {
        $this->products([50, 150]);
        $query = Product::query();
        // Registering the model reads its columns once; only the apply itself must not query.
        RuleInk::expressionsFor(Product::class);

        DB::enableQueryLog();
        RuleInk::apply(Product::class, $query, 'price.gt', ['value' => 100]);

        $this->assertSame([], DB::getQueryLog());
        $this->assertSame([1, 2], $query->orderBy('id')->pluck('id')->all());
    }

    public function testNarrowsABuilderThatAlreadyHasOrConditions(): void
    {
        $this->products([50, 150, 300]);
        $query = Product::where('price', 50)->orWhere('price', 300);

        $builder = RuleInk::apply(Product::class, $query, 'price.gt', ['value' => 100]);

        $this->assertSame([3], $builder->pluck('id')->all());
    }

    public function testNarrowsAPlainQueryBuilder(): void
    {
        $this->products([50, 150]);

        $builder = RuleInk::apply(Product::class, DB::table('products'), 'price.gt', ['value' => 100]);

        $this->assertInstanceOf(QueryBuilder::class, $builder);
        $this->assertSame([2], $builder->pluck('id')->all());
    }

    public function testACustomExpressionCannotBeAppliedToAQuery(): void
    {
        RuleInk::define(
            Product::class,
            ExpressionDefinition::custom('product.is_cheap', 'Product is cheap'),
            fn (mixed $subject, array $values): bool => true,
        );

        $this->assertRefused(
            'product.is_cheap',
            'Datasource adapter [' . EloquentDatasourceAdapter::class . '] cannot apply expression [product.is_cheap].',
            fn () => RuleInk::apply(Product::class, Product::query(), 'product.is_cheap'),
        );
    }

    public function testNarrowsAnEloquentBuilderJoinedToATableWithTheSameColumn(): void
    {
        $this->products([50, 150]);
        $query = Product::query()->select('products.id')->join('products as other', 'other.id', '=', 'products.id');

        $builder = RuleInk::apply(Product::class, $query, 'price.gt', ['value' => 100]);

        $this->assertSame([2], $builder->pluck('id')->all());
    }

    public function testNarrowsAnAliasedQueryBuilderJoinedToATableWithTheSameColumn(): void
    {
        $this->products([50, 150]);
        $query = DB::table('products as p')->select('p.id')->join('products as other', 'other.id', '=', 'p.id');

        $builder = RuleInk::apply(Product::class, $query, 'price.gt', ['value' => 100]);

        $this->assertSame([2], $builder->pluck('id')->all());
    }

    public function testNarrowsASubqueryJoinedToATableWithTheSameColumn(): void
    {
        $this->products([50, 150]);
        $query = DB::query()
            ->fromSub(DB::table('products'), 'p')
            ->select('p.id')
            ->join('products as other', 'other.id', '=', 'p.id');

        $builder = RuleInk::apply(Product::class, $query, 'price.gt', ['value' => 100]);

        $this->assertSame([2], $builder->pluck('id')->all());
    }

    /**
     * The subquery alias in FROM already carries the connection's table prefix.
     */
    public function testNarrowsASubqueryOnAConnectionWithATablePrefix(): void
    {
        DB::connection()->setTablePrefix('p_');
        $this->defineDatabaseMigrations();
        $this->products([50, 150]);
        $query = DB::query()
            ->fromSub(DB::table('products'), 'p')
            ->select('p.id')
            ->join('products as other', 'other.id', '=', 'p.id');

        $builder = RuleInk::apply(Product::class, $query, 'price.gt', ['value' => 100]);

        $this->assertSame([2], $builder->pluck('id')->all());
    }

    public function testRefusesAJoinedQueryWhoseTableItCannotTell(): void
    {
        $query = DB::query()->from(DB::raw('products'))->join('products as other', 'other.id', '=', 'products.id');

        $this->assertRefused(
            'price.gt',
            'RuleInk cannot tell which table [price] belongs to in this query.',
            fn () => RuleInk::apply(Product::class, $query, 'price.gt', ['value' => 100]),
        );
    }

    /**
     * SQLite lets a numeric column hold text, and compares text as greater than any number.
     */
    public function testTextInANumericColumnIsNoNumber(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Only SQLite stores text in a numeric column.');
        }

        $this->products(['abc', 150]);

        $builder = RuleInk::apply(Product::class, Product::query(), 'price.gt', ['value' => 50]);

        $this->assertSame([2], $builder->pluck('id')->all());
    }

    /**
     * SQLite stores decimals as doubles and MySQL compares a decimal with a string as doubles, so more digits than a
     * double holds would be rounded away.
     */
    public function testRefusesANumberWithMoreDigitsThanADoubleHolds(): void
    {
        $this->assertRefused(
            'price.eq',
            'Value [100.000000000000001] has more digits than a query can compare exactly.',
            fn () => RuleInk::apply(Product::class, Product::query(), 'price.eq', ['value' => '100.000000000000001']),
        );
    }

    /**
     * From 2^53 a double skips integers: SQLite compares a stored double with a bound integer exactly, and MySQL
     * compares an integer column with a string as doubles, so either would disagree with evaluate().
     */
    #[DataProvider('numbersFromTwoToTheFiftyThree')]
    public function testRefusesANumberFromTwoToTheFiftyThree(int|float|string $value): void
    {
        $this->assertRefused(
            'price.eq',
            "Value [{$value}] is too large for a query to compare exactly.",
            fn () => RuleInk::apply(Product::class, Product::query(), 'price.eq', ['value' => $value]),
        );
    }

    /**
     * @return array<string, array{int|float|string}>
     */
    public static function numbersFromTwoToTheFiftyThree(): array
    {
        return [
            'two to the 53' => [9007199254740992],
            'negative two to the 53' => [-9007199254740992],
            'a double that is not exact' => ['1000000000000000100'],
        ];
    }

    public function testAppliesTheLargestExactInteger(): void
    {
        $this->products([50]);

        $builder = RuleInk::apply(Product::class, Product::query(), 'price.lt', ['value' => 9007199254740991]);

        $this->assertSame([1], $builder->pluck('id')->all());
    }

    /**
     * A where clause would filter only the first select of the union.
     */
    public function testRefusesAUnion(): void
    {
        $query = Product::where('price', 50)->union(Product::where('price', 300));

        $this->assertRefused(
            'price.gt',
            'RuleInk cannot apply expressions to a union query.',
            fn () => RuleInk::apply(Product::class, $query, 'price.gt', ['value' => 100]),
        );
    }

    /**
     * Their SQL cannot be checked against evaluate() without a database to run it on.
     */
    #[DataProvider('untestedDrivers')]
    public function testRefusesADatabaseItCannotCheckParityOn(string $driver): void
    {
        $this->assertRefused(
            'price.gt',
            "RuleInk cannot apply expressions to [{$driver}] queries yet.",
            fn () => RuleInk::apply(Product::class, DB::connection($driver)->table('products'), 'price.gt', [
                'value' => 1,
            ]),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function untestedDrivers(): array
    {
        return [
            'PostgreSQL' => ['pgsql'],
            'SQL Server' => ['sqlsrv'],
        ];
    }

    /**
     * Every operator in the catalogue compiles today; this covers one added to core before the compiler knows it.
     */
    public function testRefusesAnOperatorItCannotCompile(): void
    {
        $definition = ExpressionDefinition::generated(
            new Field('price', FieldType::Number),
            'between',
            'Price is between',
        );

        $this->assertRefused(
            'price.between',
            'Operator [between] is not supported yet.',
            fn () => (new ConditionCompiler())->compile(
                DB::table('products'),
                new SqliteDialect(),
                'price',
                $definition,
                1,
            ),
        );
    }

    private function assertRefused(string $key, string $message, callable $apply): void
    {
        try {
            $apply();
            $this->fail('Expected ApplyRefused.');
        } catch (ApplyRefused $refused) {
            $this->assertSame($key, $refused->key);
            $this->assertSame(EloquentDatasourceAdapter::class, $refused->adapter);
            $this->assertSame($message, $refused->getMessage());
        }
    }

    /**
     * @param list<int|float|string> $prices
     */
    private function products(array $prices): void
    {
        foreach ($prices as $price) {
            DB::table('products')->insert(['price' => $price, 'active' => 1, 'options' => '[]', 'secret' => '']);
        }
    }
}
