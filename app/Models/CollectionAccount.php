<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionAccount extends Model
{
    use HasUuids;

    protected $fillable = [
        'profile_id', 'created_by_user_id', 'method', 'label', 'provider', 'country_code', 'currency',
        'account_holder_name', 'account_identifier_encrypted', 'account_identifier_last4',
        'verification_status', 'verified_at', 'superseded_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'account_identifier_encrypted' => 'encrypted',
            'verified_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FinancialProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(FinancialProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<CollectionSchedule, $this> */
    public function collectionSchedules(): HasMany
    {
        return $this->hasMany(CollectionSchedule::class);
    }

    public function maskedIdentifier(): string
    {
        return $this->account_identifier_last4
            ? '•••• '.$this->account_identifier_last4
            : 'Not provided';
    }
}
