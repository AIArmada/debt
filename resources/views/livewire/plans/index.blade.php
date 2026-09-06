<div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6">
    <div>
        <a href="{{ route('promises.index', $profile) }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">← Back to promises</a>
        <div class="app-eyebrow mt-6">{{ $profile->name }}</div>
        <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">Budgets and plans</flux:heading>
        <flux:text class="mt-2">Plan suggested payments without changing any promise balance.</flux:text>
    </div>

    @can('createObligation', $profile)
        <section class="app-card rounded-2xl p-5 sm:p-7">
            <flux:heading size="lg">New budget period</flux:heading>
            <form wire:submit="createPeriod" class="mt-5 grid gap-3 sm:grid-cols-3">
                <flux:input wire:model="startsOn" type="date" label="Starts" required />
                <flux:input wire:model="endsOn" type="date" label="Ends" required />
                <flux:input wire:model="currency" label="Currency" required />
                <flux:input wire:model="incomeMinor" type="number" min="0" label="Income (minor units)" required />
                <flux:input wire:model="essentialMinor" type="number" min="0" label="Essentials (minor units)" required />
                <flux:input wire:model="reserveMinor" type="number" min="0" label="Reserve (minor units)" required />
                <div class="sm:col-span-3 sm:flex sm:justify-end"><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Create period</flux:button></div>
            </form>
        </section>
    @endcan

    <section class="app-card overflow-hidden rounded-2xl">
        <div class="border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80"><flux:heading size="lg">Budget periods</flux:heading></div>
        <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
            @forelse ($periods as $period)
                <a href="{{ route('plans.show', [$profile, $period]) }}" wire:navigate class="flex items-center justify-between px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-900/60">
                    <span><span class="font-medium">{{ $period->starts_on->format('d M Y') }} – {{ $period->ends_on->format('d M Y') }}</span><span class="mt-1 block text-sm text-zinc-500">{{ $period->currency }} · Capacity {{ \App\Domain\Money\Money::formatMinor($period->capacity(), $period->currency) }}</span></span>
                    <span class="text-sm text-emerald-700">Open →</span>
                </a>
            @empty
                <div class="px-5 py-10 text-sm text-zinc-500">Create a period to generate a payment plan.</div>
            @endforelse
        </div>
    </section>
</div>
