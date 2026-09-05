<?php

namespace App\Models;

use Database\Factories\FinancialProfileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialProfile extends Model
{
    /** @use HasFactory<FinancialProfileFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name', 'type', 'base_currency', 'timezone', 'locale',
        'is_islamic_mode_enabled', 'is_archived',
    ];

    protected function casts(): array
    {
        return ['is_islamic_mode_enabled' => 'boolean', 'is_archived' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return HasMany<Record, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(Record::class, 'profile_id');
    }

    /** @return HasMany<Party, $this> */
    public function parties(): HasMany
    {
        return $this->hasMany(Party::class, 'profile_id');
    }

    /** @return HasMany<BudgetPeriod, $this> */
    public function budgetPeriods(): HasMany
    {
        return $this->hasMany(BudgetPeriod::class, 'profile_id');
    }

    /** @return HasMany<RepaymentPlan, $this> */
    public function repaymentPlans(): HasMany
    {
        return $this->hasMany(RepaymentPlan::class, 'profile_id');
    }

    /** @return HasMany<ProfileMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(ProfileMember::class, 'profile_id');
    }

    /** @return HasMany<ProfileInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(ProfileInvitation::class, 'profile_id');
    }

    /** @return HasMany<ExchangeRate, $this> */
    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'profile_id');
    }

    /** @return HasMany<EmergencyAccessRequest, $this> */
    public function emergencyAccessRequests(): HasMany
    {
        return $this->hasMany(EmergencyAccessRequest::class, 'profile_id');
    }

    /** @return HasMany<BankImport, $this> */
    public function bankImports(): HasMany
    {
        return $this->hasMany(BankImport::class, 'profile_id');
    }

    /** @return HasMany<CollectionAccount, $this> */
    public function collectionAccounts(): HasMany
    {
        return $this->hasMany(CollectionAccount::class, 'profile_id');
    }

    /** @return HasMany<Integration, $this> */
    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class, 'profile_id');
    }
}
