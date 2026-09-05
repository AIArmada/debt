<?php

namespace App\Actions\Obligations;

use App\Domain\Money\Currency;
use App\Domain\Money\MoneyAmount;
use App\Domain\Obligations\Quantity;
use App\Domain\Obligations\QuantityMode;
use App\Models\Obligation;
use App\Models\PledgedAsset;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AddPledgedAsset
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array{asset_type: string, description: string, quantity: string|null, quantity_mode: string, quantity_unit: string|null, estimated_value: string|null, currency: string|null, storage_location: string|null, pledged_on: string|null, matures_on: string|null} $data */
    public function handle(Obligation $obligation, array $data): PledgedAsset
    {
        Gate::authorize('manageAssets', $obligation);
        if (filled($data['estimated_value']) && ! Currency::isSupported($data['currency'])) {
            throw ValidationException::withMessages(['currency' => 'Choose a supported currency for the estimated value.']);
        }

        $quantityMode = QuantityMode::tryFrom($data['quantity_mode']);

        if ($quantityMode === null) {
            throw ValidationException::withMessages(['quantity_mode' => 'Choose whether this quantity is countable or measurable.']);
        }

        if (filled($data['quantity']) && ! Quantity::isModeCompatible($quantityMode, $data['quantity_unit'] ?? null)) {
            throw ValidationException::withMessages([
                'quantity_unit' => 'Measurable quantities need a unit such as grams, kilograms, hours, or metres. Use whole units for complete items like cameras.',
            ]);
        }

        $quantity = null;
        if (filled($data['quantity'])) {
            try {
                $quantity = Quantity::normalise($data['quantity'], $quantityMode);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['quantity' => $exception->getMessage()]);
            }
        }

        try {
            $estimatedValue = filled($data['estimated_value'])
                ? MoneyAmount::fromMajor($data['estimated_value'], (string) $data['currency'])
                : null;
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['estimated_value' => $exception->getMessage()]);
        }

        $asset = $obligation->pledgedAssets()->create([
            ...$data,
            'quantity' => $quantity,
            'quantity_mode' => $quantityMode->value,
            'estimated_value' => $estimatedValue,
            'status' => 'pledged',
        ]);

        $this->auditLogger->record(
            $obligation->record->profile,
            null,
            PledgedAsset::class,
            $asset->getKey(),
            'created',
            after: $asset->only(['obligation_id', 'asset_type', 'quantity', 'quantity_mode', 'quantity_unit', 'estimated_value', 'currency', 'pledged_on', 'matures_on']),
        );

        return $asset;
    }
}
