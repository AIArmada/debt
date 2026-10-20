<div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6">
    <div>
        <a href="{{ route('promises.index', $profile) }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">← Back to promises</a>
        <div class="app-eyebrow mt-6">{{ $profile->name }}</div>
        <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">Currency comparison</flux:heading>
        <flux:text class="mt-2">Saved rates are display-only estimates. Native balances remain untouched.</flux:text>
    </div>

    <section class="app-card rounded-2xl p-5 sm:p-7">
        <div class="grid gap-4 sm:grid-cols-3">
            <div><flux:text>To pay</flux:text>@forelse ($converted['to_pay'] as $currency => $position)<div class="mt-2 text-2xl font-semibold">{{ \App\Domain\Money\Money::display($position['amount_minor'], $currency) }}</div><div class="text-xs text-zinc-500">Rate {{ $position['rate'] }} on {{ $position['rated_on'] }}@if ($position['stale']) · stale @endif</div>@empty<div class="mt-2 text-2xl font-semibold">—</div>@endforelse</div>
            <div><flux:text>To receive</flux:text>@forelse ($converted['to_receive'] as $currency => $position)<div class="mt-2 text-2xl font-semibold text-cyan-800">{{ \App\Domain\Money\Money::display($position['amount_minor'], $currency) }}</div><div class="text-xs text-zinc-500">Rate {{ $position['rate'] }} on {{ $position['rated_on'] }}@if ($position['stale']) · stale @endif</div>@empty<div class="mt-2 text-2xl font-semibold">—</div>@endforelse</div>
            <div><flux:text>Target currency</flux:text><div class="mt-2 text-2xl font-semibold">{{ $converted['target_currency'] }}</div><div class="text-xs text-zinc-500">Currencies are converted only here.</div></div>
        </div>
    </section>

    @can('update', $profile)
        <section class="app-card rounded-2xl p-5 sm:p-7">
            <flux:heading size="lg">Save a rate</flux:heading>
            <form wire:submit="saveRate" class="mt-5 grid gap-3 sm:grid-cols-4 sm:items-end">
                <flux:input wire:model="target" label="To currency" required />
                <flux:input wire:model="rate" label="Rate" inputmode="decimal" required />
                <flux:input wire:model="ratedOn" type="date" label="Date" required />
                <flux:input wire:model="source" label="Source" required />
                <div class="sm:col-span-4 sm:flex sm:justify-end"><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Save rate</flux:button></div>
            </form>
        </section>
    @endcan

    <section class="app-card rounded-2xl p-5 sm:p-7">
        <flux:heading size="lg">Saved exchange rates</flux:heading>
        <div class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($rates as $savedRate)
                <div wire:key="exchange-rate-{{ $savedRate->id }}" class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                    <span class="font-medium">{{ $savedRate->from_currency }} → {{ $savedRate->to_currency }}</span>
                    <span>{{ $savedRate->rate }} · {{ $savedRate->rated_on->format('d M Y') }} · {{ $savedRate->source }}</span>
                </div>
            @empty
                <flux:text class="py-3 text-sm">No rates saved for this profile.</flux:text>
            @endforelse
        </div>
    </section>
</div>
