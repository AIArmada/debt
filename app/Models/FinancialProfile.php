<?php

namespace App\Models;

use App\Services\ProfileAccess;
use Database\Factories\FinancialProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class FinancialProfile extends Model
{
    /** @use HasFactory<FinancialProfileFactory> */
    use HasFactory, HasUuids;

    /**
     * @param  Builder<FinancialProfile>  $query
     * @param  mixed  $value
     * @param  string|null  $field
     * @return Builder<FinancialProfile>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        return app(ProfileAccess::class)->accessibleProfiles($user)->whereKey($value);
    }

    protected $fillable = ['name', 'base_currency', 'timezone', 'is_archived'];

    protected function casts(): array
    {
        return ['is_archived' => 'boolean'];
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

    /** @return HasMany<ActivityEntry, $this> */
    public function activityEntries(): HasMany
    {
        return $this->hasMany(ActivityEntry::class, 'profile_id');
    }

    /** @return HasMany<Party, $this> */
    public function parties(): HasMany
    {
        return $this->hasMany(Party::class, 'profile_id');
    }

    /** @return HasMany<ProfileMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(ProfileMember::class, 'profile_id');
    }

    /** @return HasMany<ProfileInvite, $this> */
    public function invites(): HasMany
    {
        return $this->hasMany(ProfileInvite::class, 'profile_id');
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'profile_id');
    }

    /** @return HasMany<BudgetPeriod, $this> */
    public function budgetPeriods(): HasMany
    {
        return $this->hasMany(BudgetPeriod::class, 'profile_id');
    }

    /** @return HasMany<ImportBatch, $this> */
    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class, 'profile_id');
    }

    /** @return HasMany<ApiToken, $this> */
    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class, 'profile_id');
    }
}
