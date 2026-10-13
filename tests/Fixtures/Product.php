<?php

declare(strict_types=1);

namespace RuleInk\Laravel\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RuleInk\Laravel\HasRuleInk;

class Product extends Model
{
    use HasRuleInk;

    protected $hidden = ['secret'];

    protected $casts = [
        'active' => 'boolean',
        'options' => 'array',
    ];
}
