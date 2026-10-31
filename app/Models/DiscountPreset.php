<?php

namespace App\Models;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A discount the restaurant offers, such as "Staff 20%" or "Birthday RM 10".
 */
#[Fillable(['name', 'type', 'value', 'scope', 'sort_order', 'is_active'])]
class DiscountPreset extends Model
{
    use HasUlids, SoftDeletes, UsesTenantDatabase;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * "20%" or "RM 10.00".
     */
    public function valueLabel(string $currency): string
    {
        return $this->type === DiscountType::Percent
            ? rtrim(rtrim($this->value, '0'), '.').'%'
            : $currency.' '.$this->value;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'scope' => DiscountScope::class,
            'value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
