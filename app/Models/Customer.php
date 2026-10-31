<?php

namespace App\Models;

use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A customer of the restaurant, known by their phone number.
 */
#[Fillable(['phone', 'name', 'email', 'birthday', 'notes', 'marketing_consent', 'consented_at'])]
class Customer extends Model
{
    use HasUlids, SoftDeletes, UsesTenantDatabase;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'marketing_consent' => false,
    ];

    protected static function booted(): void
    {
        // When consent was given is kept, as the PDPA expects.
        static::saving(function (self $customer): void {
            if ($customer->isDirty('marketing_consent')) {
                $customer->consented_at = $customer->marketing_consent ? now() : null;
            }
        });
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * Digits only, so "012-345 6789" and "0123456789" are the same customer.
     */
    public static function normalisePhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }

    public static function findByPhone(string $phone): ?self
    {
        return self::where('phone', self::normalisePhone($phone))->first();
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function displayName(): string
    {
        return $this->name ?: $this->phone;
    }

    /**
     * @return Attribute<string, string>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => self::normalisePhone($value));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'marketing_consent' => 'boolean',
            'consented_at' => 'datetime',
        ];
    }
}
