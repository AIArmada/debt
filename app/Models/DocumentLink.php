<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentLink extends Model
{
    use HasUuids;

    protected $fillable = ['document_id', 'record_id', 'obligation_id', 'financial_transaction_id', 'obligation_event_id', 'purpose'];

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<Record, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }

    /** @return BelongsTo<ObligationEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(ObligationEvent::class, 'obligation_event_id');
    }
}
