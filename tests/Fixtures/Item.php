<?php

declare(strict_types=1);

namespace RuleInk\Laravel\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RuleInk\Laravel\HasRuleInk;

/**
 * Its casts change some stored values (a tinyint 2 reads as true, 99.999 reads as 100.00), so parity tests show
 * that queries and evaluate() both use the stored value.
 */
class Item extends Model
{
    use HasRuleInk;

    public $timestamps = false;

    protected $casts = [
        'price' => 'decimal:2',
        'active' => 'boolean',
        'released_on' => 'date',
        'released_at' => 'datetime',
    ];
}
