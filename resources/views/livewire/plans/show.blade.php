<div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6">
    <div>
        <a href="{{ route('plans.index', $profile) }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">← Back to plans</a>
        <div class="app-eyebrow mt-6">{{ $profile->name }}</div>
        <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">Repayment plan</flux:heading>
        <flux:text class="mt-2">{{ $period->starts_on->format('d M Y') }} – {{ $period->ends_on->format('d M Y') }} · {{ $period->currency }}</flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-4">
        <div class="app-metric app-metric-primary rounded-2xl p-5"><flux:text>Income</flux:text><div class="mt-2 text-xl font-semibold">{{ \App\Domain\Money\Money::formatMinor($period->income_minor, $period->currency) }}</div></div>
        <div class="app-metric rounded-2xl p-5"><flux:text>Essentials</flux:text><div class="mt-2 text-xl font-semibold">{{ \App\Domain\Money\Money::formatMinor($period->essential_minor, $period->currency) }}</div></div>
        <div class="app-metric rounded-2xl p-5"><flux:text>Reserve</flux:text><div class="mt-2 text-xl font-semibold">{{ \App\Domain\Money\Money::formatMinor($period->reserve_minor, $period->currency) }}</div></div>
        <div class="app-metric app-metric-secondary rounded-2xl p-5"><flux:text>Capacity</flux:text><div class="mt-2 text-xl font-semibold">{{ \App\Domain\Money\Money::formatMinor($period->capacity(), $period->currency) }}</div></div>
    </div>

    <section class="app-card overflow-hidden rounded-2xl">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80">
            <div><flux:heading size="lg">Suggested allocations</flux:heading><flux:text class="mt-1">Advice only — confirming a payment still happens on the promise.</flux:text></div>
            @can('createObligation', $profile)<flux:button wire:click="generate" variant="primary" wire:loading.attr="disabled">Generate plan</flux:button>@endcan
        </div>
        <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
            @forelse ($plan?->allocations ?? [] as $allocation)
                @php($line = $progress[$allocation->id] ?? ['planned_minor' => $allocation->planned_minor, 'paid_minor' => 0])
                <div class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><div class="font-medium">{{ $allocation->obligation->title }}</div><div class="text-sm text-zinc-500">Planned {{ \App\Domain\Money\Money::formatMinor($line['planned_minor'], $period->currency) }}</div></div>
                    <div class="text-sm font-semibold text-emerald-700">Paid {{ \App\Domain\Money\Money::formatMinor($line['paid_minor'], $period->currency) }}</div>
                </div>
            @empty
                <div class="px-5 py-10 text-sm text-zinc-500">Generate a plan to see suggested allocations.</div>
            @endforelse
        </div>
    </section>
</div>
