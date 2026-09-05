<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** @property Carbon|null $next_due_on */
class CollectionSchedule extends Model
{
    use HasUuids;

    protected $fillable = [
        'obligation_id', 'created_by_user_id', 'collection_account_id', 'mode', 'status', 'amount', 'currency', 'frequency',
        'starts_on', 'ends_on', 'next_due_on', 'collection_method', 'grace_days', 'note', 'paused_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'grace_days' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'next_due_on' => 'date',
            'paused_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<CollectionAccount, $this> */
    public function collectionAccount(): BelongsTo
    {
        return $this->belongsTo(CollectionAccount::class);
    }

    /** @return HasMany<FinancialTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'collection_schedule_id');
    }
}
