<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use App\Enums\Plan;
use Database\Factories\TenantInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An invitation for one email address to register a new tenant. The token
 * works once and only until it expires or is revoked.
 */
#[Fillable(['email', 'plan', 'token_hash', 'expires_at', 'accepted_at', 'revoked_at', 'business_id', 'created_by'])]
#[Hidden(['token_hash'])]
class TenantInvitation extends Model
{
    /** @use HasFactory<TenantInvitationFactory> */
    use HasFactory;

    public const DEFAULT_DAYS_VALID = 7;

    /**
     * Give the invitation a fresh token and return it. Only its hash is
     * stored, so this return value is the one chance to read the token; any
     * earlier token stops working.
     */
    public function issueToken(): string
    {
        $token = Str::random(48);

        $this->token_hash = self::hash($token);

        return $token;
    }

    public static function findByToken(string $token): ?self
    {
        return self::where('token_hash', self::hash($token))->first();
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function registrationUrl(string $token): string
    {
        return route('register.show', ['token' => $token]);
    }

    public function status(): InvitationStatus
    {
        return match (true) {
            $this->accepted_at !== null => InvitationStatus::Accepted,
            $this->revoked_at !== null => InvitationStatus::Revoked,
            $this->expires_at->isPast() => InvitationStatus::Expired,
            default => InvitationStatus::Pending,
        };
    }

    public function isUsable(): bool
    {
        return $this->status() === InvitationStatus::Pending;
    }

    /**
     * The tenant that registered with this invitation.
     *
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->acrossBusinesses();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => Plan::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
