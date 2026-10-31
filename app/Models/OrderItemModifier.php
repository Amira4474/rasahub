<?php

namespace App\Models;

use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A modifier chosen for an order item, copied at the moment of sale.
 */
#[Fillable(['order_item_id', 'modifier_id', 'group_name', 'name', 'price_delta'])]
class OrderItemModifier extends Model
{
    use UsesTenantDatabase;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_delta' => 'decimal:2',
        ];
    }
}
