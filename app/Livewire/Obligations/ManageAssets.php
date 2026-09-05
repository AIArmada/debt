<?php

namespace App\Livewire\Obligations;

use App\Actions\Obligations\AddPledgedAsset;
use App\Domain\Obligations\Quantity;
use App\Domain\Obligations\QuantityMode;
use App\Models\Obligation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ManageAssets extends Component
{
    public Obligation $obligation;

    #[Validate('required|string|max:60')]
    public string $assetType = '';

    #[Validate('nullable|string|max:255')]
    public string $description = '';

    #[Validate('nullable|numeric|min:0')]
    public ?string $quantity = null;

    #[Validate('required|in:countable,measurable')]
    public string $quantityMode = 'countable';

    #[Validate('nullable|string|max:30')]
    public string $quantityUnit = '';

    #[Validate('nullable|numeric|min:0')]
    public ?string $estimatedValue = null;

    #[Validate('nullable|string|size:3')]
    public ?string $currency = null;

    #[Validate('nullable|string|max:255')]
    public string $storageLocation = '';

    #[Validate('nullable|date')]
    public ?string $pledgedOn = null;

    #[Validate('nullable|date')]
    public ?string $maturesOn = null;

    public function mount(Obligation $obligation): void
    {
        Gate::authorize('manageAssets', $obligation);
        $this->obligation = $obligation;
        $this->currency = $obligation->currency;
    }

    public function save(AddPledgedAsset $addPledgedAsset): void
    {
        Gate::authorize('manageAssets', $this->obligation);
        $validated = $this->validate();
        $quantityMode = QuantityMode::from($validated['quantityMode']);

        if (filled($validated['quantity']) && ! Quantity::isModeCompatible($quantityMode, $validated['quantityUnit'])) {
            $this->addError('quantityMode', 'Measurable quantities need grams, kilograms, hours, or metres. Use whole units for complete items like cameras.');

            return;
        }

        if (filled($validated['quantity']) && ! Quantity::isValid($validated['quantity'], $quantityMode, false)) {
            $this->addError('quantity', $quantityMode->isCountable()
                ? 'Whole-unit items must use a whole number, such as 1 camera or 2 cameras.'
                : 'Enter a positive quantity with no more than four decimal places.');

            return;
        }

        $addPledgedAsset->handle($this->obligation, [
            'asset_type' => $validated['assetType'],
            'description' => $validated['description'],
            'quantity' => $validated['quantity'],
            'quantity_mode' => $validated['quantityMode'],
            'quantity_unit' => $validated['quantityUnit'] ?: null,
            'estimated_value' => $validated['estimatedValue'],
            'currency' => $validated['currency'],
            'storage_location' => $validated['storageLocation'] ?: null,
            'pledged_on' => $validated['pledgedOn'],
            'matures_on' => $validated['maturesOn'],
        ]);
        $this->reset('assetType', 'description', 'quantity', 'quantityMode', 'quantityUnit', 'estimatedValue', 'storageLocation', 'pledgedOn', 'maturesOn');
        $this->currency = $this->obligation->currency;
        session()->flash('asset-created', 'The pledged asset was added.');
    }

    public function render(): View
    {
        Gate::authorize('manageAssets', $this->obligation);

        return view('livewire.obligations.manage-assets', [
            'assets' => $this->obligation->pledgedAssets()->latest()->get(),
        ]);
    }
}
