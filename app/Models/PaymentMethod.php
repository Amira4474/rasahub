<?php

namespace App\Models;

use App\Enums\PaymentMethodType;
use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A way the restaurant takes payment, such as Cash or DuitNow QR. RasaHub
 * records payments; card and QR payments happen on the restaurant's own
 * terminal or QR code.
 */
#[Fillable(['name', 'type', 'qr_image_path', 'requires_reference', 'sort_order', 'is_active'])]
class PaymentMethod extends Model
{
    use HasUlids, SoftDeletes, UsesTenantDatabase;

    /**
     * What a new restaurant starts with.
     */
    public const DEFAULTS = [
        ['name' => 'Cash', 'type' => 'cash', 'requires_reference' => false],
        ['name' => 'Card', 'type' => 'card', 'requires_reference' => true],
        ['name' => 'QR payment', 'type' => 'qr', 'requires_reference' => false],
        ['name' => 'Other', 'type' => 'other', 'requires_reference' => true],
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'requires_reference' => false,
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
     * The active methods in order, creating the defaults the first time a
     * restaurant needs them.
     *
     * @return Collection<int, self>
     */
    public static function available(): Collection
    {
        if (! self::withTrashed()->exists()) {
            foreach (self::DEFAULTS as $order => $method) {
                self::create([...$method, 'sort_order' => $order]);
            }
        }

        return self::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
    }

    public function isCash(): bool
    {
        return $this->type === PaymentMethodType::Cash;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class,
            'requires_reference' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
