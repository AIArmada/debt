<section class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
        <flux:heading size="lg">Edit movement</flux:heading>
        <flux:text class="mt-1 text-sm">Correct the entry, its context, or its date. Confirmed movements are replayed in order so later balances stay trustworthy.</flux:text>
    </div>
    <form wire:submit="save" class="space-y-5 px-5 py-5">
        @php($decreaseEntryType = in_array($entryType, ['payment', 'collection'], true) ? $entryType : ($obligation->currencyPosition($currency)['direction'] === 'receivable' ? 'collection' : 'payment'))
        <flux:select wire:model.live="entryType" label="What happened?">
            <flux:select.option value="{{ $decreaseEntryType }}">{{ $decreaseEntryType === 'collection' ? 'Collection — reduce what they owe you' : 'Payment — reduce what you owe them' }}</flux:select.option>
            <flux:select.option value="advance">New advance — increase the principal</flux:select.option>
            <flux:select.option value="interest">Interest / profit charge — increase the balance</flux:select.option>
            <flux:select.option value="fee">Fee / storage charge — increase the balance</flux:select.option>
            <flux:select.option value="adjustment">Adjustment — correct the balance</flux:select.option>
            <flux:select.option value="write_off">Write-off / waiver — reduce the balance</flux:select.option>
            <flux:select.option value="opening_balance">Opening balance — establish the starting amount</flux:select.option>
        </flux:select>
        @if ($entryType === 'adjustment')
            <flux:select wire:model="balanceEffect" label="Adjustment effect">
                <flux:select.option value="">Choose an effect</flux:select.option>
                <flux:select.option value="increase">Increase the balance</flux:select.option>
                <flux:select.option value="decrease">Decrease the balance</flux:select.option>
            </flux:select>
        @endif
        <div class="grid gap-5 sm:grid-cols-2">
            <flux:input wire:model="amount" type="number" step="any" min="0.0001" label="Amount ({{ $currency }})" required />
            <flux:select wire:model.live="currency" label="Currency" required>
                @foreach ($currencies as $code => $label)
                    <flux:select.option value="{{ $code }}">{{ $code }} — {{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <p class="-mt-3 text-xs text-zinc-500">The movement keeps its selected currency. Editing it can move the amount into another native currency exposure without applying an exchange rate.</p>
        <flux:select wire:model="status" label="Status">
            <flux:select.option value="confirmed">Confirmed</flux:select.option>
            <flux:select.option value="submitted">Submitted</flux:select.option>
            <flux:select.option value="planned">Planned</flux:select.option>
            <flux:select.option value="failed">Failed</flux:select.option>
            <flux:select.option value="cancelled">Cancelled</flux:select.option>
        </flux:select>
        <flux:input wire:model="occurredOn" type="date" label="Date" required />
        <flux:input wire:model="externalReference" label="Reference" placeholder="Optional" />
        <flux:textarea wire:model="note" label="Note or reason" placeholder="Explain the correction or add context." rows="3" />
        <flux:button type="submit" variant="primary" class="w-full">Save movement</flux:button>
    </form>
</section>
