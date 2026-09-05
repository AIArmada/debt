<section class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
        <flux:heading size="lg">Record {{ $obligation->direction === 'payable' ? 'payment' : 'collection' }} or another movement</flux:heading>
        <flux:text class="mt-1 text-sm">{{ $obligation->tracking_mode === 'snapshot' ? 'This known balance becomes the starting point. After you save this first movement, the record will use detailed ledger tracking.' : 'Payments and collections reduce the balance; advances, interest, and fees can increase it.' }} Only confirmed entries change the current balance.</flux:text>
        @if ($repaymentPlanAllocation)
            <div class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm leading-5 text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-100">This payment is for <strong>{{ $repaymentPlanAllocation->plan->name }}</strong>. It will be linked to the plan automatically so its Paid and Remaining figures stay current.</div>
        @endif
        @if ($obligation->isPositionReversed())
            <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">The current position is reversed. This form is ready for a collection that reduces what the other party now owes you. The original direction remains visible as history.</div>
        @endif
    </div>
    <form wire:submit="save" class="space-y-5 px-5 py-5">
        <flux:select wire:model.live="entryType" label="What happened?">
            <flux:select.option value="{{ $obligation->currencyPosition($currency)['direction'] === 'receivable' ? 'collection' : 'payment' }}">{{ $obligation->currencyPosition($currency)['direction'] === 'receivable' ? 'Collection — reduce what they owe you' : 'Payment — reduce what you owe them' }}</flux:select.option>
            <flux:select.option value="advance">New advance — increase the principal</flux:select.option>
            <flux:select.option value="interest">Interest / profit charge — increase the balance</flux:select.option>
            <flux:select.option value="fee">Fee / storage charge — increase the balance</flux:select.option>
            <flux:select.option value="adjustment">Adjustment — correct the balance</flux:select.option>
            <flux:select.option value="write_off">Write-off / waiver — reduce the balance</flux:select.option>
            <flux:select.option value="opening_balance">Opening balance — establish the starting amount</flux:select.option>
        </flux:select>
        @if ($entryType === 'collection' && $collectionSchedules->isNotEmpty())
            <flux:select wire:model="collectionScheduleId" label="Expected collection (optional)">
                <flux:select.option value="">One-off collection</flux:select.option>
                @foreach ($collectionSchedules as $schedule)
                    <flux:select.option value="{{ $schedule->id }}">{{ \App\Domain\Money\MoneyAmount::format($schedule->amount, $schedule->currency) }} · {{ ucfirst($schedule->frequency) }} · next {{ $schedule->next_due_on?->format('d M Y') }}</flux:select.option>
                @endforeach
            </flux:select>
            <p class="-mt-3 text-xs text-zinc-500">Link this received payment to the expected collection schedule for reconciliation.</p>
        @endif
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
        <p class="-mt-3 text-xs text-zinc-500">This movement is recorded in {{ $currency }}. Different currencies remain separate and are never silently converted.</p>
        @if (strtoupper($currency) !== strtoupper((string) $obligation->currency) && (($obligation->currencyBalances()[strtoupper($currency)] ?? 0) === 0))
            <div class="-mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                There is no opening balance in {{ $currency }} for this obligation. A decrease in this currency starts from zero and can create a separate reversed position. Use this only when that is intentional; no exchange rate will be applied.
            </div>
        @endif
        <flux:select wire:model="status" label="Status">
            <flux:select.option value="confirmed">Confirmed</flux:select.option>
            <flux:select.option value="submitted">Submitted</flux:select.option>
            <flux:select.option value="planned">Planned</flux:select.option>
            <flux:select.option value="failed">Failed</flux:select.option>
            <flux:select.option value="cancelled">Cancelled</flux:select.option>
        </flux:select>
        <flux:input wire:model="occurredOn" type="date" label="Date" required />
        <flux:input wire:model="externalReference" label="Reference" placeholder="Optional" />
        <flux:textarea wire:model="note" label="Note or reason" placeholder="What is this movement for? Add context while it is fresh." rows="3" />
        <flux:button type="submit" variant="primary" class="w-full">Save transaction</flux:button>
    </form>
</section>
