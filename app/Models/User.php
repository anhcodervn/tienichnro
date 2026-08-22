<?php

namespace App\Models;

use App\Notifications\QueuedResetPasswordNotification;
use App\Notifications\QueuedVerifyEmailNotification;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements CanResetPassword, JWTSubject, MustVerifyEmailContract
{
    use CanResetPasswordTrait, HasApiTokens, HasFactory, MustVerifyEmail, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'full_name',
        'avatar',
        'google_id',
        'password',
        'role',
        'status',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
        'referral_code',
        'referred_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'name',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (blank($user->username)) {
                $user->username = $user->generateUniqueUsername();
            }
        });

        static::created(function (self $user): void {
            $user->wallets()->firstOrCreate([
                'type' => Wallet::TYPE_MAIN,
            ], [
                'balance' => 0,
                'hold_balance' => 0,
                'total_recharge' => 0,
                'total_spent' => 0,
            ]);

        });
    }

    public function getNameAttribute(): string
    {
        return $this->full_name ?: $this->username;
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['full_name'] = $value;
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(self::class, 'referred_by');
    }

    public function userSessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class)->where('type', Wallet::TYPE_MAIN);
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function adminAuditLogs(): HasMany
    {
        return $this->hasMany(AdminAuditLog::class, 'admin_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function supportConversation(): HasOne
    {
        return $this->hasOne(SupportConversation::class);
    }

    public function supportMessages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'sender_id');
    }

    public function notificationReads(): HasMany
    {
        return $this->hasMany(NotificationRead::class);
    }

    public function userLogs(): HasMany
    {
        return $this->hasMany(UserLog::class);
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    protected function generateUniqueUsername(): string
    {
        $seed = $this->username
            ?: $this->email
            ?: $this->full_name
            ?: Str::random(8);

        $username = Str::of($seed)
            ->before('@')
            ->lower()
            ->slug('')
            ->value();

        $username = $username !== '' ? Str::limit($username, 32, '') : Str::lower(Str::random(8));
        $original = $username;
        $counter = 1;

        while (static::withTrashed()->where('username', $username)->exists()) {
            $suffix = (string) $counter;
            $username = Str::limit($original, max(1, 32 - strlen($suffix)), '').$suffix;
            $counter++;
        }

        return $username;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new QueuedResetPasswordNotification((string) $token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmailNotification);
    }
}
