<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use Database\Factories\TenantInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An invitation to register a new tenant. A platform user chooses its token,
 * such as OCT_RH, and gives it to the person invited, who enters it on the
 * registration page. It works once and only until it expires or is revoked.
 */
#[Fillable(['token', 'note', 'expires_at', 'accepted_at', 'revoked_at', 'business_id', 'created_by'])]
class TenantInvitation extends Model
{
    /** @use HasFactory<TenantInvitationFactory> */
    use HasFactory;

    public const DEFAULT_DAYS_VALID = 7;

    public const TOKEN_MIN_LENGTH = 4;

    public const TOKEN_MAX_LENGTH = 32;

    /**
     * Letters, digits, hyphens and underscores.
     */
    public const TOKEN_PATTERN = '/^[A-Za-z0-9_-]+$/';

    /**
     * Letters and digits that are hard to confuse when read aloud or typed:
     * no 0 or O, no 1, I or L.
     */
    private const SUGGESTION_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * A token nobody could guess, offered when an invitation is made. The
     * platform user may replace it with one of their own.
     */
    public static function suggestToken(): string
    {
        $code = '';

        for ($i = 0; $i < 8; $i++) {
            $code .= self::SUGGESTION_ALPHABET[random_int(0, strlen(self::SUGGESTION_ALPHABET) - 1)];
        }

        return 'RH-'.implode('-', str_split($code, 4));
    }

    public static function findByToken(string $token): ?self
    {
        return self::where('token', self::normalise($token))->first();
    }

    /**
     * Tokens are stored and compared in capitals, so it does not matter how
     * someone types one.
     */
    public static function normalise(string $token): string
    {
        return strtoupper(trim($token));
    }

    /**
     * What the audit log records about the invitation.
     *
     * @return array<string, mixed>
     */
    public function auditProperties(): array
    {
        return array_filter(['invitation_id' => $this->getKey(), 'token' => $this->token, 'note' => $this->note]);
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
     * @return Attribute<string, string>
     */
    protected function token(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => self::normalise($value));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
