<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class FinancialTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'obligation_id', 'repayment_plan_allocation_id', 'repayment_installment_id', 'payment_schedule_id', 'collection_schedule_id',
        'status', 'amount', 'currency',
        'entry_type', 'balance_effect', 'balance_before', 'balance_after',
        'principal_amount', 'interest_amount', 'fee_amount', 'occurred_on',
        'submitted_at', 'confirmed_at', 'external_reference', 'provider_payload',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
            'principal_amount' => 'integer',
            'interest_amount' => 'integer',
            'fee_amount' => 'integer',
            'occurred_on' => 'date',
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return BelongsTo<CollectionSchedule, $this> */
    public function collectionSchedule(): BelongsTo
    {
        return $this->belongsTo(CollectionSchedule::class, 'collection_schedule_id');
    }

    /** @return HasMany<FinancialTransactionParty, $this> */
    public function partyLinks(): HasMany
    {
        return $this->hasMany(FinancialTransactionParty::class, 'financial_transaction_id');
    }

    /** @return BelongsTo<RepaymentPlanAllocation, $this> */
    public function repaymentPlanAllocation(): BelongsTo
    {
        return $this->belongsTo(RepaymentPlanAllocation::class, 'repayment_plan_allocation_id');
    }

    /** @return HasMany<DocumentLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(DocumentLink::class, 'financial_transaction_id');
    }

    /** @return HasManyThrough<Document, DocumentLink, $this> */
    public function documents(): HasManyThrough
    {
        return $this->hasManyThrough(Document::class, DocumentLink::class, 'financial_transaction_id', 'id', 'id', 'document_id');
    }
}
