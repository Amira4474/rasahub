<?php

namespace App\Models;

use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The restaurant's tax, rounding and receipt settings: one row in each
 * tenant's database. New tenants start with SST on at 6%, menu prices
 * including SST, and totals rounded to 5 sen.
 */
#[Fillable(['sst_enabled', 'sst_rate', 'prices_include_sst', 'sst_registration_number', 'round_to_five_sen', 'refund_days', 'receipt_logo_path', 'receipt_header', 'receipt_footer', 'qr_logo_path', 'qr_banner_path', 'qr_heading', 'qr_welcome_text', 'qr_primary_color', 'qr_background_color', 'qr_footer_text', 'qr_show_descriptions', 'qr_show_photos'])]
class RestaurantSetting extends Model
{
    use UsesTenantDatabase;

    public const DEFAULTS = [
        'sst_enabled' => true,
        'sst_rate' => '6.00',
        'prices_include_sst' => true,
        'round_to_five_sen' => true,
        'refund_days' => 30,
        'qr_primary_color' => '#DC4A2D',
        'qr_background_color' => '#FBF3E7',
        'qr_show_descriptions' => true,
        'qr_show_photos' => true,
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = self::DEFAULTS;

    /**
     * The settings of the business being acted in, created with the
     * defaults the first time they are needed.
     */
    public static function current(): self
    {
        return self::query()->oldest('id')->first() ?? self::create();
    }

    /**
     * Whether SST is charged but there is no registration number to print
     * on receipts yet.
     */
    public function lacksSstNumber(): bool
    {
        return $this->sst_enabled && blank($this->sst_registration_number);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sst_enabled' => 'boolean',
            'sst_rate' => 'decimal:2',
            'prices_include_sst' => 'boolean',
            'round_to_five_sen' => 'boolean',
            'refund_days' => 'integer',
            'qr_show_descriptions' => 'boolean',
            'qr_show_photos' => 'boolean',
        ];
    }
}
