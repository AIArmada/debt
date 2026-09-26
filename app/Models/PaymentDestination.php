<?php

namespace App\Models;

use App\Domain\Enums\PaymentDestinationKind;
use Database\Factories\PaymentDestinationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property PaymentDestinationKind $kind
 * @property string $details_encrypted
 * @property bool $is_verified
 */
class PaymentDestination extends Model
{
    /** @use HasFactory<PaymentDestinationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['party_id', 'kind', 'label', 'details_encrypted', 'is_verified', 'verified_by', 'verified_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'kind' => PaymentDestinationKind::class,
            'details_encrypted' => 'encrypted',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
