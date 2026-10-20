<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Enums\MemberRole;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $locale
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property string|null $app_authentication_secret
 * @property array<string>|null $app_authentication_recovery_codes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** @var list<string> */
    public const STAFF_ROLES = ['support_agent', 'support_lead', 'support_auditor', 'security_admin'];

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && $this->hasVerifiedEmail()
            && $this->hasAnyRole(self::STAFF_ROLES, 'web');
    }

    public function getAppAuthenticationSecret(): ?string
    {
        if (! array_key_exists('app_authentication_secret', $this->attributes)) {
            return null;
        }

        return $this->app_authentication_secret;
    }

    /** @return array<string>|null */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        if (! array_key_exists('app_authentication_recovery_codes', $this->attributes)) {
            return null;
        }

        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationSecret(#[\SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();

        if (request()->hasSession()) {
            if ($secret === null) {
                session()->forget(['staff_mfa_verified_user_id', 'staff_mfa_verified_at']);
            } else {
                session()->put([
                    'staff_mfa_verified_user_id' => (string) $this->getAuthIdentifier(),
                    'staff_mfa_verified_at' => now()->timestamp,
                ]);
            }
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'locale' => 'string',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $user): void {
            $profile = $user->financialProfiles()->create([
                'name' => 'Personal',
                'base_currency' => 'MYR',
                'timezone' => 'Asia/Kuala_Lumpur',
            ]);
            $profile->members()->forceCreate([
                'user_id' => $user->getKey(),
                'role' => MemberRole::Owner,
                'accepted_at' => now(),
            ]);
        });
    }

    /** @return HasMany<FinancialProfile, $this> */
    public function financialProfiles(): HasMany
    {
        return $this->hasMany(FinancialProfile::class, 'owner_user_id');
    }

    /** @return HasMany<NotificationPreference, $this> */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
