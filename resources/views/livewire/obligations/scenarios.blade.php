<section class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
        <flux:heading size="lg">What-if scenarios</flux:heading>
        <flux:text class="mt-1 text-sm">Compare extra payments and charge assumptions without changing the record.</flux:text>
    </div>
    @if (session('scenario-created'))<div class="mx-5 mt-5 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">{{ session('scenario-created') }}</div>@endif
    @if ($this->canProjectPayments())
        <form wire:submit="save" class="space-y-5 px-5 py-5">
            <flux:input wire:model="name" label="Scenario name" required />
            <div class="grid gap-5 sm:grid-cols-2"><flux:input wire:model="extraPayment" type="number" step="any" min="0" label="Extra payment per {{ $paymentFrequency }} ({{ $obligation->currency }})" /><flux:select wire:model.live="paymentFrequency" label="Frequency"><flux:select.option value="weekly">Week</flux:select.option><flux:select.option value="monthly">Month</flux:select.option><flux:select.option value="quarterly">Quarter</flux:select.option></flux:select></div>
            <flux:input wire:model="horizonMonths" type="number" min="1" max="60" label="Projection horizon (months)" required />
            <flux:button type="submit" variant="primary" class="w-full">Run estimate</flux:button>
        </form>
    @else
        <div class="mx-5 my-5 rounded-xl border border-amber-200 bg-amber-50/70 px-4 py-4 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">Payment scenarios are paused because this obligation currently shows money to receive or is settled. Review the current position before planning another payment.</div>
    @endif
    <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">
        <div class="text-sm font-medium">Saved estimates</div>
        @forelse ($scenarios as $scenario)
            <div class="mt-4 rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800/70"><div class="flex items-start justify-between gap-3"><div class="font-medium">{{ $scenario->name }}</div><div class="text-xs text-zinc-500">{{ $scenario->horizon_months }} months</div></div><div class="mt-2 grid gap-2 text-sm text-zinc-600 dark:text-zinc-300"><span>Extra payment: {{ \App\Domain\Money\MoneyAmount::format($scenario->extra_payment, $scenario->obligation->currency) }}</span><span>Projected charges: {{ \App\Domain\Money\MoneyAmount::format(($scenario->result['interest_total'] ?? 0) + ($scenario->result['late_fee_total'] ?? 0) + ($scenario->result['storage_fee_total'] ?? 0), $scenario->obligation->currency) }}</span><span>Projected ending balance: {{ \App\Domain\Money\MoneyAmount::format($scenario->result['ending_balance'] ?? 0, $scenario->obligation->currency) }}</span></div><div class="mt-2 text-xs text-zinc-500">Estimate only · {{ $scenario->result['months_simulated'] ?? 0 }} months simulated</div></div>
        @empty
            <div class="mt-3 text-sm text-zinc-500">No saved scenarios yet.</div>
        @endforelse
    </div>
</section>
