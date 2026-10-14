<?php

declare(strict_types=1);

namespace RuleInk\Laravel\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuleInk\Laravel\Facades\RuleInk;
use RuleInk\Laravel\Tests\Fixtures\Item;

/**
 * Applying an expression to a query must keep exactly the rows that evaluate() matches, for the subjects that
 * query returns: models for an Eloquent builder, plain rows for a query builder.
 */
final class ParityTest extends TestCase
{
    private const COLUMNS = ['name', 'price', 'active', 'released_on', 'released_at', 'label'];

    private const ROWS = [
        [null, null, null, null, null, null],
        ['Lamp', 100, 1, '2026-10-08', '2026-10-08 00:00:00', 'Lamp'],
        ['lamp', 99.99, 0, '2026-10-07', '2026-10-07 23:59:59', 'lamp'],
        ['Lamp ', 99.999, 2, '2026-10-09', '2026-10-08 08:00:00', 'Lamp '],
        ['Desk Lamp', 100.001, 1, '2026-10-08', '2026-10-08 07:59:59', 'DESK LAMP'],
        ['', 0, 0, '2026-01-01', '2026-10-08 08:00:01', ''],
        ['a%b_c', -1, 1, '2027-01-01', '2025-12-31 16:00:00', 'a%b_c'],
        ['café', 5, 1, '2026-10-08', '2026-10-08 00:00:00', 'CAFÉ'],
    ];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.timezone', 'Asia/Kuala_Lumpur');
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        Schema::dropIfExists('items');
        Schema::create('items', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->decimal('price', 10, 3)->nullable();
            $table->tinyInteger('active')->nullable();
            $table->date('released_on')->nullable();
            $table->dateTime('released_at')->nullable();
            // A case-insensitive column: comparisons must stay byte-wise anyway.
            $collation = DB::getDriverName() === 'sqlite' ? 'NOCASE' : 'utf8mb4_0900_ai_ci';
            $table->string('label')->nullable()->collation($collation);
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        date_default_timezone_set('Asia/Kuala_Lumpur');
        DB::table('items')->insert(array_map(fn (array $row): array => array_combine(self::COLUMNS, $row), self::ROWS));
    }

    protected function tearDown(): void
    {
        date_default_timezone_set('UTC');

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('expressions')]
    public function testEloquentBuilderAgreesWithEvaluate(string $key, array $values): void
    {
        $expected = Item::query()->orderBy('id')->get()
            ->filter(fn (Item $item): bool => RuleInk::evaluate(Item::class, $item, $key, $values))
            ->pluck('id')->values()->all();

        $actual = RuleInk::apply(Item::class, Item::query(), $key, $values)->orderBy('id')->pluck('id')->all();

        $this->assertSame($expected, $actual);
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('expressions')]
    public function testQueryBuilderAgreesWithEvaluate(string $key, array $values): void
    {
        $expected = DB::table('items')->orderBy('id')->get()
            ->filter(fn (object $row): bool => RuleInk::evaluate(Item::class, $row, $key, $values))
            ->pluck('id')->values()->all();

        $actual = RuleInk::apply(Item::class, DB::table('items'), $key, $values)->orderBy('id')->pluck('id')->all();

        $this->assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function expressions(): array
    {
        return [
            'price.eq int' => ['price.eq', ['value' => 100]],
            'price.eq decimal string' => ['price.eq', ['value' => '100.00']],
            'price.eq exponent' => ['price.eq', ['value' => '1e2']],
            'price.eq float past 14 digits' => ['price.eq', ['value' => 100.00000000000001]],
            'price.eq leading space' => ['price.eq', ['value' => ' 100']],
            'price.lt trailing space' => ['price.lt', ['value' => '100 ']],
            'price.gt' => ['price.gt', ['value' => 99.99]],
            'price.gt stored three places' => ['price.gt', ['value' => '99.998']],
            'price.gte' => ['price.gte', ['value' => '100']],
            'price.lt' => ['price.lt', ['value' => 100]],
            'price.lte zero' => ['price.lte', ['value' => 0]],
            'name.equals' => ['name.equals', ['value' => 'Lamp']],
            'name.equals empty' => ['name.equals', ['value' => '']],
            'name.contains' => ['name.contains', ['value' => 'amp']],
            'name.contains capital' => ['name.contains', ['value' => 'Lamp']],
            'name.contains empty' => ['name.contains', ['value' => '']],
            'name.contains wildcard' => ['name.contains', ['value' => '%']],
            'name.contains underscore' => ['name.contains', ['value' => 'b_']],
            'name.contains a byte inside a character' => ['name.contains', ['value' => "\xA9"]],
            'label.equals ignoring its collation' => ['label.equals', ['value' => 'lamp']],
            'label.contains ignoring its collation' => ['label.contains', ['value' => 'Lamp']],
            'active.is true' => ['active.is', ['value' => true]],
            'active.is false' => ['active.is', ['value' => '0']],
            'released_on.before' => ['released_on.before', ['value' => '2026-10-08']],
            'released_on.after offset' => ['released_on.after', ['value' => '2026-10-08T00:00:00+08:00']],
            'released_at.before' => ['released_at.before', ['value' => '2026-10-08T00:00:00Z']],
            'released_at.after' => ['released_at.after', ['value' => '2026-10-08T08:00:00+08:00']],
            'released_at.before no offset' => ['released_at.before', ['value' => '2026-10-08 00:00:00']],
        ];
    }
}
