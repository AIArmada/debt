<?php

namespace App\Models;

use App\Domain\Obligations\Quantity;
use App\Domain\Obligations\QuantityMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PledgedAsset extends Model
{
    use HasUuids;

    protected $fillable = [
        'obligation_id', 'asset_type', 'description', 'quantity', 'quantity_mode', 'quantity_unit',
        'estimated_value', 'currency', 'storage_location', 'pledged_on',
        'matures_on', 'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'quantity_mode' => QuantityMode::class,
            'estimated_value' => 'integer',
            'pledged_on' => 'date',
            'matures_on' => 'date',
        ];
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    public function quantityMode(): QuantityMode
    {
        /** @var QuantityMode $mode */
        $mode = $this->getAttribute('quantity_mode');

        return $mode;
    }

    public function quantityNeedsReview(): bool
    {
        return filled($this->quantity)
            && (! Quantity::isModeCompatible($this->quantityMode(), $this->quantity_unit)
                || ! Quantity::isValid($this->quantity, $this->quantityMode(), false));
    }
}
