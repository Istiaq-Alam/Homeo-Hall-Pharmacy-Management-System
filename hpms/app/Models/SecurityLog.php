<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SecurityLog extends Model
{
    public const SIGN_IN = 'sign_in';

    public const SIGN_IN_FAILED = 'sign_in_failed';

    public const SIGN_OUT = 'sign_out';

    public const ACCOUNT_CREATED = 'account_created';

    public const ACCOUNT_DELETED = 'account_deleted';

    public const PROFILE_UPDATED = 'profile_updated';

    public const PASSWORD_CHANGED = 'password_changed';

    private const LABELS = [
        self::SIGN_IN => 'Signed in',
        self::SIGN_IN_FAILED => 'Failed sign-in',
        self::SIGN_OUT => 'Signed out',
        self::ACCOUNT_CREATED => 'Account created',
        self::ACCOUNT_DELETED => 'Account deleted',
        self::PROFILE_UPDATED => 'Profile updated',
        self::PASSWORD_CHANGED => 'Password changed',
    ];

    private const BADGES = [
        self::SIGN_IN => 'status-good',
        self::SIGN_IN_FAILED => 'status-cancelled',
        self::SIGN_OUT => 'status-inactive',
        self::ACCOUNT_CREATED => 'status-completed',
        self::ACCOUNT_DELETED => 'status-cancelled',
        self::PROFILE_UPDATED => 'status-completed',
        self::PASSWORD_CHANGED => 'status-good',
    ];

    protected $fillable = ['event', 'description', 'user_id', 'actor_name', 'actor_email', 'ip_address', 'user_agent'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record one security-relevant event for the administration log.
     *
     * The actor is snapshotted so the entry stays readable after the account
     * is renamed or deleted.
     */
    public static function record(string $event, string $description, ?User $actor = null): self
    {
        $request = request();
        $userAgent = $request?->userAgent();

        return static::query()->create([
            'event' => $event,
            'description' => Str::limit($description, 250, ''),
            'user_id' => $actor?->getKey(),
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'ip_address' => $request?->ip(),
            'user_agent' => $userAgent ? Str::limit($userAgent, 250, '') : null,
        ]);
    }

    public function label(): string
    {
        return self::LABELS[$this->event] ?? Str::headline($this->event);
    }

    public function badgeClass(): string
    {
        return self::BADGES[$this->event] ?? 'status-inactive';
    }
}
