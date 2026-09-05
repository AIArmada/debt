<div class="border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <div class="text-sm font-semibold">Obligation roles</div>
            <div class="mt-1 text-xs text-zinc-500">Useful when this one obligation has a different beneficiary, payer, guarantor, or shared responsibility.</div>
        </div>
        @if ($obligation->partyLinks->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                @foreach ($obligation->partyLinks as $link)
                    <x-status-badge tone="info" :label="$link->party->preferred_name.' · '.str_replace('_', ' ', $link->role).($link->share_basis === 'percentage' ? ' · '.\App\Domain\Money\Decimal::display($link->share_percent).'%' : '')" />
                    @can('update', $obligation)<flux:button size="sm" variant="ghost" wire:click="remove('{{ $link->id }}')" wire:confirm="Remove this obligation role?">×</flux:button>@endcan
                @endforeach
            </div>
        @else
            <div class="text-xs text-zinc-500">No specific obligation roles recorded.</div>
        @endif
    </div>
    @can('update', $obligation)
        @if ($parties->isNotEmpty())
            <form wire:submit="add" class="mt-3 grid gap-3 sm:grid-cols-4">
                <flux:select wire:model="partyId" label="Party" placeholder="Choose a party"><flux:select.option value="">Choose a party</flux:select.option>@foreach ($parties as $party)<flux:select.option :value="$party->id">{{ $party->preferred_name }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model="role" label="Role"><flux:select.option value="obligor">Obligor</flux:select.option><flux:select.option value="beneficiary">Beneficiary</flux:select.option><flux:select.option value="payer">Payer</flux:select.option><flux:select.option value="payee">Payee</flux:select.option><flux:select.option value="creditor">Creditor</flux:select.option><flux:select.option value="debtor">Debtor</flux:select.option><flux:select.option value="guarantor">Guarantor</flux:select.option><flux:select.option value="service_recipient">Service recipient</flux:select.option><flux:select.option value="custodian">Custodian</flux:select.option></flux:select>
                <flux:select wire:model="shareBasis" label="Share"><flux:select.option value="full">Full responsibility</flux:select.option><flux:select.option value="percentage">Percentage</flux:select.option><flux:select.option value="unspecified">Unspecified</flux:select.option></flux:select>
                <div class="flex items-end gap-2"><flux:input wire:model="sharePercent" type="number" min="0" max="100" step="0.01" placeholder="%" aria-label="Share percentage" /><flux:button type="submit" variant="outline"><flux:icon name="plus" class="size-4" /> Add</flux:button></div>
            </form>
        @endif
    @endcan
</div>
