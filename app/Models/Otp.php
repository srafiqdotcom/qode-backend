<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Otp extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'otp_code',
        'purpose',
        'expires_at',
        'is_used',
        'used_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($otp) {
            if (empty($otp->uuid)) {
                $otp->uuid = Str::uuid();
            }
            if (empty($otp->otp_code)) {
                $otp->otp_code = self::generateOtpCode();
            }
            if (empty($otp->expires_at)) {
                $expiryMinutes = (int) config('auth.otp_expiry_minutes', 10);
                $otp->expires_at = Carbon::now()->addMinutes($expiryMinutes);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeValid($query)
    {
        return $query->where('is_used', false)
                    ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeUsed($query)
    {
        return $query->where('is_used', true);
    }

    public function scopeForPurpose($query, $purpose)
    {
        return $query->where('purpose', $purpose);
    }

    public function isValid(): bool
    {
        return !$this->is_used && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function markAsUsed($ipAddress = null, $userAgent = null)
    {
        $this->update([
            'is_used' => true,
            'used_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public static function generateOtpCode(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function createForUser(User $user, string $purpose = 'login', array $additionalData = []): self
    {
        $user->otps()->where('purpose', $purpose)->where('is_used', false)->delete();

        return self::create(array_merge([
            'user_id' => $user->id,
            'purpose' => $purpose,
        ], $additionalData));
    }

    public static function verifyOtp(User $user, string $otpCode, string $purpose = 'login'): bool
    {
        $otp = self::where('user_id', $user->id)
                  ->where('otp_code', $otpCode)
                  ->where('purpose', $purpose)
                  ->valid()
                  ->first();

        if ($otp) {
            $otp->markAsUsed(request()->ip(), request()->userAgent());
            return true;
        }

        return false;
    }
}
