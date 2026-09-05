<div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-8">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex items-start gap-4">
            <span class="app-page-icon hidden shrink-0 sm:inline-flex"><flux:icon name="chart-bar" class="size-6" /></span>
            <div>
                <div class="app-eyebrow">Plan with clarity</div>
                <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">Budget & repayment plans</flux:heading>
                <flux:text class="mt-2 max-w-2xl leading-6">Turn your actual money obligations into a realistic plan, then record payments against the plan so progress stays visible.</flux:text>
            </div>
        </div>
        <flux:select wire:model.live="profileId" class="w-52" aria-label="Financial profile">
            @foreach ($profiles as $item)
                <flux:select.option :value="$item->id">{{ $item->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @foreach (['budget-created', 'cash-flow-added', 'plan-created', 'plan-paused', 'plan-activated', 'plan-refreshed'] as $message)
        @if (session($message))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200" role="status">{{ session($message) }}</div>
        @endif
    @endforeach

    @if ($budget)
        <section class="app-card overflow-hidden rounded-2xl">
            <div class="flex flex-col gap-4 border-b border-zinc-200/80 px-5 py-5 sm:flex-row sm:items-start sm:justify-between dark:border-zinc-700/80">
                <div class="flex items-start gap-3">
                    <span class="app-section-icon"><flux:icon name="calendar-days" class="size-5" /></span>
                    <div>
                        <flux:heading size="lg">Budget period</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ $budget->starts_on->format('d M Y') }} – {{ $budget->ends_on->format('d M Y') }} · Planning in {{ $budget->currency }}</flux:text>
                    </div>
                </div>
                <div class="rounded-xl bg-emerald-50 px-4 py-3 sm:min-w-52 sm:text-right dark:bg-emerald-950/30">
                    <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Available to plan</div>
                    <div class="mt-1 text-2xl font-semibold tracking-tight text-emerald-950 dark:text-emerald-100">{{ App\Domain\Money\MoneyAmount::format($capacity, $budget->currency) }}</div>
                    <div class="mt-1 text-xs text-emerald-800/70 dark:text-emerald-200/70">Safe capacity less confirmed repayments</div>
                </div>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([['Income', 'income', 'text-emerald-700'], ['Essential costs', 'essential_expenses', 'text-rose-700'], ['Flexible costs', 'flexible_expenses', 'text-amber-700'], ['Emergency reserve', 'emergency_reserve', 'text-sky-700'], ['Safe capacity', 'safe_capacity', 'text-emerald-700'], ['Paid this period', 'actual_repayments', 'text-cyan-700'], ['Available to plan', 'available_to_plan', 'text-emerald-700'], ['After all listed costs', 'recommended_capacity', 'text-zinc-900']] as [$label, $key, $colour])
                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $label }}</div>
                        <div class="mt-2 text-lg font-semibold {{ $colour }} dark:text-zinc-100">{{ App\Domain\Money\MoneyAmount::format($capacityBreakdown[$key], $budget->currency) }}</div>
                    </div>
                @endforeach
            </div>
            <div class="border-t border-zinc-200/80 px-5 py-4 text-sm text-zinc-600 dark:border-zinc-700/80 dark:text-zinc-300">
                The planner uses the amount still available after confirmed repayments in this period. Budget capacity itself remains based on income, costs, and reserve; repayments are shown separately so they are not counted twice.
            </div>
        </section>

        <div class="grid gap-7 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="space-y-7">
                <section class="app-card overflow-hidden rounded-2xl">
                    <div class="border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex items-start gap-3">
                                <span class="app-section-icon"><flux:icon name="list-bullet" class="size-5" /></span>
                                <div>
                                    <flux:heading size="lg">Choose what this plan covers</flux:heading>
                                    <flux:text class="mt-1 text-sm leading-5">Select the money obligations you want this budget to help resolve. They are grouped by record and linked back to the full history.</flux:text>
                                </div>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <flux:button type="button" size="sm" variant="ghost" wire:click="selectAllObligations">Select all</flux:button>
                                <flux:button type="button" size="sm" variant="ghost" wire:click="clearObligations">Clear</flux:button>
                            </div>
                        </div>
                    </div>
                    <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
                        @forelse ($candidates as $candidate)
                            <div wire:key="candidate-{{ $candidate['obligation_id'] }}" class="flex items-start gap-4 px-5 py-4 transition hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30">
                                <input wire:model.live="selectedObligationIds" value="{{ $candidate['obligation_id'] }}" type="checkbox" class="mt-1.5 size-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 dark:border-zinc-600" aria-label="Include {{ $candidate['title'] }}">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a wire:navigate href="{{ route('records.show', $candidate['record']) }}" class="font-medium text-zinc-900 hover:text-emerald-700 dark:text-zinc-100 dark:hover:text-emerald-300">{{ $candidate['title'] }}</a>
                                        @if ($candidate['due_on'])
                                            <x-status-badge tone="warning" :label="'Due '.$candidate['due_on']->format('d M Y')" />
                                        @endif
                                    </div>
                                    <div class="mt-1 text-sm text-zinc-500">{{ $candidate['party_name'] ?: 'No party recorded' }} · {{ $candidate['record']->title }}</div>
                                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-zinc-600 dark:text-zinc-300">
                                        <span>Outstanding <strong class="font-semibold text-zinc-900 dark:text-zinc-100">{{ App\Domain\Money\MoneyAmount::format($candidate['current_amount'], $candidate['currency']) }}</strong></span>
                                        <span>Minimum <strong class="font-semibold text-zinc-900 dark:text-zinc-100">{{ App\Domain\Money\MoneyAmount::format($candidate['minimum_amount'], $candidate['currency']) }}</strong></span>
                                        <span>Paid this period <strong class="font-semibold text-emerald-700 dark:text-emerald-300">{{ App\Domain\Money\MoneyAmount::format($candidate['paid_this_period'], $candidate['currency']) }}</strong></span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="px-5 py-10 text-center">
                                <span class="app-icon-badge mx-auto size-11"><flux:icon name="check" class="size-5" /></span>
                                <flux:heading size="lg" class="mt-3">No payable money obligations in {{ $budget->currency }}</flux:heading>
                                <flux:text class="mt-2">Create a money record in this currency, or review the separate-currency section below.</flux:text>
                            </div>
                        @endforelse
                    </div>
                    @error('selectedObligationIds')
                        <div class="border-t border-rose-200 bg-rose-50 px-5 py-3 text-sm text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200">{{ $message }}</div>
                    @enderror
                </section>

                @if ($excludedCandidates->isNotEmpty())
                    <section class="rounded-2xl border border-sky-200/80 bg-sky-50/60 shadow-sm dark:border-sky-900/60 dark:bg-sky-950/20">
                        <div class="border-b border-sky-200/80 px-5 py-5 dark:border-sky-900/60">
                            <div class="flex items-start gap-3">
                                <span class="app-section-icon bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300"><flux:icon name="globe-alt" class="size-5" /></span>
                                <div>
                                    <flux:heading size="lg">Kept separate from this {{ $budget->currency }} plan</flux:heading>
                                    <flux:text class="mt-1 text-sm leading-5 text-sky-900/75 dark:text-sky-100/75">Different currencies are not added together and no exchange rate is assumed. Create a budget in the native currency when you want to plan those obligations.</flux:text>
                                </div>
                            </div>
                        </div>
                        <div class="divide-y divide-sky-200/70 dark:divide-sky-900/50">
                            @foreach ($excludedCandidates as $excluded)
                                <a wire:key="excluded-{{ $excluded['obligation_id'] }}-{{ $excluded['currency'] }}" wire:navigate href="{{ route('records.show', $excluded['record']) }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-sky-100/60 dark:hover:bg-sky-950/30">
                                    <div class="min-w-0"><div class="truncate font-medium text-zinc-900 dark:text-zinc-100">{{ $excluded['title'] }}</div><div class="mt-2 flex flex-wrap items-center gap-2"><span class="text-sm text-sky-900/70 dark:text-sky-100/70">{{ $excluded['currency'] }}</span><x-direction-badge :direction="$excluded['direction']" :label="$excluded['direction'] === 'payable' ? 'You owe them' : 'They owe you'" /></div></div>
                                    <div class="shrink-0 text-right font-semibold text-zinc-900 dark:text-zinc-100">{{ App\Domain\Money\MoneyAmount::format($excluded['amount'], $excluded['currency']) }}</div>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($plan)
                    <section class="app-card overflow-hidden rounded-2xl">
                        <div class="border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                        <div class="app-eyebrow">Current plan</div>
                                        <div class="mt-1 flex flex-wrap items-center gap-2">
                                            <flux:heading size="lg">{{ $plan->name }}</flux:heading>
                                        @php($planStatusTone = match ($plan->status) { 'active' => 'info', 'needs_review' => 'warning', 'completed' => 'success', 'failed' => 'danger', 'paused', 'superseded' => 'neutral', default => 'neutral' })
                                        <x-status-badge :tone="$planStatusTone" :label="$plan->statusLabel()" />
                                    </div>
                                    <flux:text class="mt-1 text-sm">{{ ucfirst(str_replace('_', ' ', $plan->strategy)) }} · {{ App\Domain\Money\MoneyAmount::format($plan->available_amount, $plan->currency) }} available when generated</flux:text>
                                </div>
                                <div class="flex flex-wrap items-start gap-3 sm:justify-end">
                                    <div class="rounded-xl bg-zinc-50 px-4 py-3 text-sm dark:bg-zinc-800/60">
                                        <div class="text-zinc-500">Plan total</div>
                                        <div class="mt-1 font-semibold text-zinc-900 dark:text-zinc-100">{{ App\Domain\Money\MoneyAmount::format($planProgress->sum('planned'), $plan->currency) }}</div>
                                    </div>
                                    @if ($canManageBudget)
                                        @if ($plan->status === 'active')
                                            <flux:button size="sm" variant="ghost" wire:click="pausePlan('{{ $plan->id }}')">Pause plan</flux:button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                        @if ($plan->needsReview())
                            <div class="border-b border-amber-200 bg-amber-50/70 px-5 py-4 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-100">
                                <div class="font-semibold">This plan needs your review</div>
                                <div class="mt-1">{{ $plan->review_reason ?: 'A confirmed movement changed the assumptions behind this plan.' }} Review the proposed refresh before making it active.</div>
                            </div>
                        @elseif ($plan->status === 'completed')
                            <div class="border-b border-emerald-200 bg-emerald-50/70 px-5 py-4 text-sm text-emerald-950 dark:border-emerald-900/60 dark:bg-emerald-950/20 dark:text-emerald-100">All planned payments have been recorded. New charges or adjustments will ask you to review this plan again.</div>
                        @endif
                        <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
                            @forelse ($planProgress as $progress)
                                <div wire:key="plan-allocation-{{ $progress['allocation']->id }}" class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2"><x-priority-badge :rank="$progress['allocation']->priority_rank" /><a wire:navigate href="{{ route('records.show', $progress['obligation']->record) }}" class="font-medium text-zinc-900 hover:text-emerald-700 dark:text-zinc-100 dark:hover:text-emerald-300">{{ $progress['obligation']->title }}</a></div>
                                        <p class="mt-2 pl-9 text-sm text-zinc-500">{{ $progress['allocation']->priority_reason }}</p>
                                        <div class="mt-3 flex flex-wrap gap-2 pl-9 text-xs">
                                            <x-status-badge tone="neutral" :label="'Planned '.App\Domain\Money\MoneyAmount::format($progress['planned'], $progress['currency'])" />
                                            <x-status-badge tone="success" :label="'Paid '.App\Domain\Money\MoneyAmount::format($progress['paid'], $progress['currency'])" />
                                            <x-status-badge tone="warning" :label="'Remaining '.App\Domain\Money\MoneyAmount::format($progress['remaining'], $progress['currency'])" />
                                            @php($progressTone = in_array($progress['state'], ['paid', 'complete', 'completed'], true) ? 'success' : (in_array($progress['state'], ['short', 'partial', 'needs_review'], true) ? 'warning' : 'info'))
                                            <x-status-badge :tone="$progressTone" :label="ucfirst($progress['state'])" />
                                        </div>
                                    </div>
                                    @if ($canRecordTransactions)
                                        <flux:modal.trigger name="plan-payment-{{ $progress['allocation']->id }}">
                                            <flux:button size="sm" variant="primary" class="shrink-0"><flux:icon name="plus" class="size-4" /> Record payment</flux:button>
                                        </flux:modal.trigger>
                                    @endif
                                </div>
                            @empty
                                <div class="px-5 py-8 text-sm text-zinc-500">No allocation was created because no selected obligation had a current payable balance.</div>
                            @endforelse
                        </div>
                    </section>
                @endif

                @if ($plans->isNotEmpty())
                    <section class="app-card overflow-hidden rounded-2xl">
                        <div class="border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80">
                            <div class="flex items-start gap-3">
                                <span class="app-section-icon"><flux:icon name="clock" class="size-5" /></span>
                                <div>
                                    <flux:heading size="lg">Plan history</flux:heading>
                                    <flux:text class="mt-1 text-sm leading-5">Keep different approaches together. Only one plan can be active for this budget and currency; older plans remain available as snapshots.</flux:text>
                                </div>
                            </div>
                        </div>
                        <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
                            @foreach ($plans as $history)
                                @php($historyIsCurrent = $plan?->id === $history->id)
                                <div wire:key="plan-history-{{ $history->id }}" class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $history->name }}</span>
                                            @php($historyStatusTone = match ($history->status) { 'active' => 'info', 'needs_review' => 'warning', 'completed' => 'success', 'failed' => 'danger', 'paused', 'superseded' => 'neutral', default => 'neutral' })
                                            <x-status-badge :tone="$historyStatusTone" :label="$history->statusLabel()" />
                                            @if ($historyIsCurrent)<span class="text-xs font-medium text-emerald-700 dark:text-emerald-300">Current view</span>@endif
                                        </div>
                                        <div class="mt-1 text-sm text-zinc-500">{{ ucfirst(str_replace('_', ' ', $history->strategy)) }} · {{ $history->allocations_count }} obligation{{ $history->allocations_count === 1 ? '' : 's' }} · Generated {{ $history->generated_at?->format('d M Y, H:i') }}</div>
                                        @if ($history->status === 'needs_review')
                                            <div class="mt-2 text-xs text-amber-800 dark:text-amber-200">{{ $history->review_reason ?: 'A confirmed movement changed this plan.' }}</div>
                                        @elseif ($history->status === 'paused')
                                            <div class="mt-2 text-xs text-zinc-500">Paused {{ $history->paused_at?->format('d M Y, H:i') ?: 'manually' }} · can be activated again.</div>
                                        @endif
                                    </div>
                                    @if ($canManageBudget)
                                        @if ($history->status === 'paused')
                                            <flux:button size="sm" variant="primary" wire:click="activatePlan('{{ $history->id }}')">Activate plan</flux:button>
                                        @elseif ($history->status === 'needs_review')
                                            <flux:button size="sm" variant="primary" wire:click="activatePlan('{{ $history->id }}')">Review &amp; activate</flux:button>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            <div class="space-y-7">
                <section class="app-card overflow-hidden rounded-2xl">
                    <div class="border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80">
                        <div class="flex items-start gap-3"><span class="app-section-icon"><flux:icon name="arrows-right-left" class="size-5" /></span><div><flux:heading size="lg">Cash flow</flux:heading><flux:text class="mt-1 text-sm">Add the income and costs that shape this period.</flux:text></div></div>
                    </div>
                    <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
                        @forelse ($budget->cashFlowEntries as $entry)
                            <div class="flex items-center justify-between gap-4 px-5 py-3"><div><div class="font-medium">{{ $entry->name }}</div><div class="mt-1 text-xs text-zinc-500">{{ ucfirst($entry->type) }} · {{ $entry->is_essential ? 'Essential' : 'Flexible' }}</div></div><div class="font-medium {{ $entry->type === 'income' ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300' }}">{{ $entry->type === 'income' ? '+' : '−' }} {{ App\Domain\Money\MoneyAmount::format($entry->amount, $budget->currency) }}</div></div>
                        @empty
                            <div class="px-5 py-6 text-sm text-zinc-500">No cash-flow entries yet.</div>
                        @endforelse
                    </div>
                    <form wire:submit="addCashFlow" class="space-y-5 border-t border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80">
                        <flux:select wire:model="cashFlowType" label="Type"><flux:select.option value="income">Income</flux:select.option><flux:select.option value="expense">Expense</flux:select.option></flux:select>
                        <flux:input wire:model="cashFlowName" label="Name" required />
                        <flux:input wire:model="cashFlowCategory" label="Category" required />
                        <flux:input wire:model="cashFlowAmount" type="number" step="any" min="0.0001" label="Amount ({{ $budget->currency }})" required />
                        <flux:checkbox wire:model="cashFlowEssential" label="Essential expense" />
                        <flux:checkbox wire:model="cashFlowRecurring" label="Recurring" />
                        <flux:button type="submit" variant="primary" class="w-full">Add cash-flow entry</flux:button>
                    </form>
                </section>

                <section class="app-card overflow-hidden rounded-2xl">
                    <div class="border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80"><div class="flex items-start gap-3"><span class="app-section-icon"><flux:icon name="sparkles" class="size-5" /></span><div><flux:heading size="lg">Generate plan</flux:heading><flux:text class="mt-1 text-sm">The plan suggests allocations; it never changes a balance until you record a payment.</flux:text></div></div></div>
                    <form wire:submit="generatePlan" class="space-y-5 px-5 py-5">
                        <flux:select wire:model="strategy" label="Strategy"><flux:select.option value="highest_interest">Highest interest / cost</flux:select.option><flux:select.option value="smallest_balance">Smallest balance</flux:select.option><flux:select.option value="earliest_due">Earliest due date</flux:select.option><flux:select.option value="manual">Current record order</flux:select></flux:select>
                        <div class="rounded-xl bg-emerald-50/80 p-4 text-sm leading-5 text-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-100">{{ count($selectedObligationIds) }} obligation{{ count($selectedObligationIds) === 1 ? '' : 's' }} selected · minimums are considered before extra funds.</div>
                        <flux:button type="submit" variant="primary" class="w-full">{{ $plan?->needsReview() ? 'Recalculate repayment plan' : ($plan ? 'Generate new repayment plan' : 'Generate repayment plan') }}</flux:button>
                    </form>
                </section>
            </div>
        </div>
    @else
        <section class="app-card mx-auto w-full max-w-3xl rounded-2xl p-6 sm:p-8">
            <div class="flex items-start gap-4"><span class="app-page-icon hidden shrink-0 sm:inline-flex"><flux:icon name="calendar-days" class="size-6" /></span><div><div class="app-eyebrow">Start with a time window</div><flux:heading size="xl" class="mt-2">Create a budget period</flux:heading><flux:text class="mt-2 leading-6">Use a month or another period that matches how you manage cash flow. Your profile’s base currency will be used, and money in other currencies will remain separate.</flux:text></div></div>
            <form wire:submit="createBudget" class="mt-7 grid gap-5 sm:grid-cols-3"><flux:input wire:model="startsOn" type="date" label="Starts" required /><flux:input wire:model="endsOn" type="date" label="Ends" required /><flux:input wire:model="emergencyReserveAmount" type="number" step="any" min="0" label="Emergency reserve" required /><div class="sm:col-span-3"><flux:button type="submit" variant="primary">Create budget period</flux:button></div></form>
        </section>
    @endif

    <flux:modal name="plan-activation" class="w-full max-w-2xl">
        @if ($activationPreview !== [])
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Refresh this plan before activating?</flux:heading>
                    <flux:text class="mt-2 leading-6">A confirmed movement changed the assumptions behind this saved plan. The original remains untouched; this preview uses the current balance and remaining budget capacity.</flux:text>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/60"><div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Available when generated</div><div class="mt-2 font-semibold">{{ App\Domain\Money\MoneyAmount::format($activationPreview['previous_available'] ?? 0, $activationPreview['currency'] ?? 'MYR') }}</div></div>
                    <div class="rounded-xl bg-emerald-50 p-4 dark:bg-emerald-950/30"><div class="text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Available now</div><div class="mt-2 font-semibold text-emerald-900 dark:text-emerald-100">{{ App\Domain\Money\MoneyAmount::format($activationPreview['current_available'] ?? 0, $activationPreview['currency'] ?? 'MYR') }}</div></div>
                </div>
                @if (! empty($activationPreview['has_changes']))
                    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <table class="min-w-[34rem] w-full text-left text-sm">
                            <thead class="border-b border-zinc-200 bg-zinc-50 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/60">
                                <tr><th class="px-4 py-3">Obligation</th><th class="whitespace-nowrap px-4 py-3">Current balance</th><th class="whitespace-nowrap px-4 py-3">Old plan</th><th class="whitespace-nowrap px-4 py-3">Proposed</th></tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach (($activationPreview['rows'] ?? []) as $row)
                                    <tr><td class="max-w-48 truncate px-4 py-3">{{ $row['title'] }}</td><td class="whitespace-nowrap px-4 py-3">{{ App\Domain\Money\MoneyAmount::format($row['current'], $row['currency']) }}</td><td class="whitespace-nowrap px-4 py-3 text-zinc-500">{{ App\Domain\Money\MoneyAmount::format($row['previous'], $row['currency']) }}</td><td class="whitespace-nowrap px-4 py-3 font-semibold">{{ App\Domain\Money\MoneyAmount::format($row['proposed'], $row['currency']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/20 dark:text-sky-100">The current inputs produce the same allocation values, but this refresh will still create a new version so the review is recorded intentionally.</div>
                @endif
                @if (! empty($activationPreview['warning']))
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-100">{{ $activationPreview['warning'] }}</div>
                @endif
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:button type="button" variant="ghost" wire:click="cancelPlanActivation">Keep paused</flux:button>
                    <flux:button type="button" variant="primary" wire:click="refreshAndActivatePlan">Refresh &amp; activate</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    @if ($plan)
        @foreach ($planProgress as $progress)
            @php($allocation = $progress['allocation'])
            @can('recordTransaction', $progress['obligation'])
                <flux:modal name="plan-payment-{{ $allocation->id }}" class="w-full max-w-xl">
                    <livewire:obligations.record-transaction :obligation="$progress['obligation']" :repayment-plan-allocation-id="$allocation->id" modal-name="plan-payment-{{ $allocation->id }}" :key="'plan-payment-'.$allocation->id" />
                </flux:modal>
            @endcan
        @endforeach
    @endif
</div>
