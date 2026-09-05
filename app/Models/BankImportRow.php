<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $occurred_on
 */
class BankImportRow extends Model
{
    use HasUuids;

    protected $fillable = ['bank_import_id', 'obligation_id', 'financial_transaction_id', 'row_number', 'occurred_on', 'description', 'amount', 'currency', 'suggested_direction', 'external_reference', 'status', 'raw_data'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'row_number' => 'integer', 'occurred_on' => 'date', 'raw_data' => 'array'];
    }

    /** @return BelongsTo<BankImport, $this> */
    public function bankImport(): BelongsTo
    {
        return $this->belongsTo(BankImport::class);
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }
}
