<section class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
        <flux:heading size="lg">Collection schedule</flux:heading>
        <flux:text class="mt-1 text-sm">Use this for an expected incoming pattern, such as RM5 every day. It does not mark money as received until a confirmed collection is recorded.</flux:text>
    </div>
    @if (! $this->canScheduleCollections())
        <div class="mx-5 mt-5 rounded-xl border border-amber-200 bg-amber-50/70 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">This obligation currently shows money to pay or is settled, so a collection schedule cannot be created or resumed.</div>
    @endif
    @if (session('collection-schedule-created'))<div class="mx-5 mt-5 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('collection-schedule-created') }}</div>@endif
    @if (session('collection-schedule-paused'))<div class="mx-5 mt-5 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('collection-schedule-paused') }}</div>@endif
    @if (session('collection-schedule-resumed'))<div class="mx-5 mt-5 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('collection-schedule-resumed') }}</div>@endif
    <form wire:submit="save" class="space-y-5 px-5 py-5">
        <flux:select wire:model="mode" label="How will you track it?" :disabled="! $this->canScheduleCollections()"><flux:select.option value="manual_follow_up">Manual follow-up</flux:select.option><flux:select.option value="bank_reconciliation">Match incoming bank rows</flux:select.option></flux:select>
        <div class="grid gap-5 sm:grid-cols-3"><flux:select wire:model.live="currency" label="Currency exposure" :disabled="$currencies === []"><flux:select.option value="">Choose currency</flux:select.option>@foreach ($currencies as $currencyCode)<flux:select.option value="{{ $currencyCode }}">{{ $currencyCode }} — {{ \App\Domain\Money\Currency::label($currencyCode) }}</flux:select.option>@endforeach</flux:select><flux:input wire:model="amount" type="number" step="any" min="0.0001" label="Expected amount ({{ $currency }})" :disabled="! $this->canScheduleCollections()" required /><flux:select wire:model="collectionMethod" label="Expected method"><flux:select.option value="bank_transfer">Bank transfer</flux:select.option><flux:select.option value="cash">Cash handover</flux:select.option><flux:select.option value="payment_provider">Payment provider</flux:select.option><flux:select.option value="other">Other</flux:select.option></flux:select></div>
        <flux:select wire:model="collectionAccountId" label="Where should it arrive? (optional)"><flux:select.option value="">No receiving account selected</flux:select.option>@foreach ($collectionAccounts as $account)<flux:select.option value="{{ $account->id }}">{{ $account->label }} · {{ $account->maskedIdentifier() }}</flux:select.option>@endforeach</flux:select>
        <flux:select wire:model="frequency" label="Frequency"><flux:select.option value="daily">Every day</flux:select.option><flux:select.option value="weekly">Every week</flux:select.option><flux:select.option value="monthly">Every month</flux:select.option><flux:select.option value="quarterly">Every quarter</flux:select.option></flux:select>
        <div class="grid gap-5 sm:grid-cols-3"><flux:input wire:model="startsOn" type="date" label="Starts" required /><flux:input wire:model="nextDueOn" type="date" label="Next expected" required /><flux:input wire:model="endsOn" type="date" label="Ends (optional)" /></div>
        <flux:input wire:model="graceDays" type="number" min="0" max="60" label="Grace days" /><flux:textarea wire:model="note" label="Collection note" placeholder="Explain the agreed amount, timing, or any helpful context." rows="3" />
        <flux:button type="submit" variant="primary" class="w-full" :disabled="! $this->canScheduleCollections()">Save collection schedule</flux:button>
    </form>
    <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">
        @forelse ($schedules as $schedule)
            @php($scheduleTone = match ($schedule->status) { 'active' => 'success', 'failed' => 'danger', 'paused' => 'neutral', default => 'info' })
            <div class="flex items-start justify-between gap-3 border-b border-zinc-100 py-3 last:border-0 dark:border-zinc-800"><div><div class="font-medium">{{ \App\Domain\Money\MoneyAmount::format($schedule->amount, $schedule->currency) }} · {{ ucfirst($schedule->frequency) }}</div><div class="mt-2 flex flex-wrap items-center gap-2"><span class="text-sm text-zinc-500">{{ ucfirst(str_replace('_', ' ', $schedule->collection_method)) }}</span><x-status-badge :tone="$scheduleTone" :label="\Illuminate\Support\Str::headline($schedule->status)" /><span class="text-sm text-zinc-500">Next {{ $schedule->next_due_on?->format('d M Y') }}</span></div>@if ($schedule->note)<div class="mt-1 text-xs text-zinc-500">{{ $schedule->note }}</div>@endif</div><div class="flex gap-2">@if ($schedule->status === 'active')<flux:button size="sm" variant="ghost" wire:click="pause('{{ $schedule->id }}')">Pause</flux:button>@elseif ($schedule->status === 'paused')<flux:button size="sm" variant="ghost" wire:click="resume('{{ $schedule->id }}')" :disabled="! $this->canScheduleCollections()">Resume</flux:button>@endif</div></div>
        @empty
            <div class="text-sm text-zinc-500">No collection schedule yet.</div>
        @endforelse
    </div>
</section>
