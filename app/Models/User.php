<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public const ADMIN_EMAIL = 'sbo.fundtrack@gmail.com';

    public const RESET_EMAIL_ALIAS = 'monggoliciousshake@gmail.com';

    public function routeNotificationForMail($notification): string
    {
        if ($notification instanceof ResetPassword
            && strtolower($this->email) === self::RESET_EMAIL_ALIAS) {
            return self::ADMIN_EMAIL;
        }

        return $this->email;
    }

    public function isAdministrator(): bool
    {
        return $this->role === 'administrator'
            && strtolower($this->email) === self::ADMIN_EMAIL;
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'actor_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'created_by');
    }

    public function recordedContributions(): HasMany
    {
        return $this->hasMany(Contribution::class, 'recorded_by');
    }
}
