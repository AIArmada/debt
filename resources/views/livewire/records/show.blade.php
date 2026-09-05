<div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-7">
    @if (session('record-updated'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200">{{ session('record-updated') }}</div>@endif
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a wire:navigate href="{{ route('records.index') }}" class="text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">← Back to records</a>
            @php($recordStateTone = match ($record->stateLabel()) { 'All obligations settled' => 'success', 'Partly resolved' => 'warning', 'Open' => 'info', default => 'neutral' })
            <div class="mt-5 flex flex-wrap items-center gap-3">
                <span class="app-eyebrow">{{ $record->profile->name }}</span>
                <x-status-badge :tone="$recordStateTone" :label="$record->stateLabel()" />
            </div>
            <div class="mt-2 flex items-start gap-3"><span class="app-section-icon hidden shrink-0 sm:inline-flex"><flux:icon name="document-text" class="size-5" /></span><flux:heading size="xl" class="text-3xl tracking-tight">{{ $record->title }}</flux:heading></div>
            <flux:text class="mt-1">{{ $record->primaryParty()?->preferred_name ?? 'No party recorded yet' }} · {{ $record->obligations->count() }} obligation{{ $record->obligations->count() === 1 ? '' : 's' }} · {{ $record->sensitivity === 'shared' ? 'Shared with profile collaborators' : 'Private to profile access' }}</flux:text>
        </div>

        <div class="flex flex-wrap gap-2">
            @can('update', $record)
                <flux:button variant="ghost" :href="route('records.edit', $record)" wire:navigate>Edit record</flux:button>
            @endcan
            @can('update', $record)
                <flux:modal.trigger name="add-obligation">
                    <flux:button variant="primary">Add obligation</flux:button>
                </flux:modal.trigger>
            @endcan
        </div>
    </div>

    @if ($record->description)
        <section class="rounded-2xl border border-sky-100 bg-sky-50/60 p-5 dark:border-sky-900/50 dark:bg-sky-950/20">
            <div class="app-eyebrow text-sky-700 dark:text-sky-300">Shared context</div>
            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-sky-950/80 dark:text-sky-100/80">{{ $record->description }}</p>
        </section>
    @endif

    <livewire:records.manage-parties :record="$record" />

    <section class="space-y-4">
        <div>
            <flux:heading size="lg">Obligations in this record</flux:heading>
            <flux:text class="mt-1 text-sm">Each component keeps its own direction, balance, progress, movement history, and evidence.</flux:text>
        </div>

        @forelse ($record->obligations as $obligation)
            <article class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-col gap-4 border-b border-zinc-200 px-5 py-5 sm:flex-row sm:items-start sm:justify-between dark:border-zinc-700">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:heading size="lg">{{ $obligation->title }}</flux:heading>
                            <x-obligation-badge :kind="$obligation->obligation_kind" :label="$obligation->kindLabel()" />
                            @if ($obligation->obligation_kind === 'money')
                                <x-direction-badge :direction="$obligation->currentPositionDirection()" :reversed="$obligation->isPositionReversed()" :label="$obligation->isPositionReversed() ? 'Position changed — review' : $obligation->effectiveDirectionLabel()" />
                            @else
                                <x-direction-badge :direction="$obligation->direction" :label="$obligation->direction === 'payable' ? 'I owe / must do' : 'Owed to me / must be done'" />
                            @endif
                        </div>
                        <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm text-zinc-500">
                            <span>{{ $obligation->obligation_kind === 'money' ? $obligation->effectiveDirectionLabel() : ($obligation->direction === 'payable' ? 'I owe / must do' : 'Owed to me') }}</span>
                            <span>{{ $obligation->categoryLabel() }}</span>
                            @if ($obligation->next_due_on)
                                <span>Due {{ $obligation->next_due_on->format('d M Y') }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @can('update', $obligation)
                            <flux:button size="sm" variant="ghost" :href="route('records.obligations.edit', [$record, $obligation])" wire:navigate>Edit obligation</flux:button>
                        @endcan
                        @if ($obligation->obligation_kind === 'money')
                            @can('recordTransaction', $obligation)
                                <flux:modal.trigger name="record-transaction-{{ $obligation->id }}">
                                    <flux:button size="sm" variant="primary">Add movement</flux:button>
                                </flux:modal.trigger>
                            @endcan
                            @can('recordEvent', $obligation)
                                <flux:modal.trigger name="record-event-{{ $obligation->id }}">
                                    <flux:button size="sm" variant="ghost">Add context</flux:button>
                                </flux:modal.trigger>
                            @endcan
                        @else
                            @can('recordEvent', $obligation)
                                <flux:modal.trigger name="record-event-{{ $obligation->id }}">
                                    <flux:button size="sm" variant="primary">Add fulfillment update</flux:button>
                                </flux:modal.trigger>
                            @endcan
                        @endif
                        <flux:modal.trigger name="obligation-evidence-{{ $obligation->id }}">
                            <flux:button size="sm" variant="ghost">Add evidence</flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>

                <div class="grid gap-4 px-5 py-5 md:grid-cols-3">
                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Current position</div>
                        @if ($obligation->obligation_kind === 'money')
                            <div class="mt-2"><x-direction-badge :direction="$obligation->currentPositionDirection()" :reversed="$obligation->isPositionReversed()" :label="$obligation->isPositionReversed() ? 'Position changed — review' : $obligation->effectiveDirectionLabel()" /></div>
                            @foreach ($obligation->currencyPositions() as $position)
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-zinc-500"><span>{{ \App\Domain\Money\MoneyAmount::format($position['amount'], $position['currency']) }}</span><x-direction-badge :direction="$position['direction']" :label="$position['direction'] === 'payable' ? 'to pay' : 'to receive'" /></div>
                            @endforeach
                            @if ($obligation->hasMultipleCurrencyExposures())
                                <div class="mt-3 text-xs text-sky-700 dark:text-sky-300">Currencies are kept separate; no FX conversion applied.</div>
                            @endif
                        @elseif ($obligation->isQuantityBased())
                            <div class="mt-2 text-xl font-semibold">{{ \App\Domain\Money\Decimal::display($obligation->current_subject_quantity ?? '0') }} {{ $obligation->subject_unit }}</div>
                            <div class="mt-1 text-sm text-zinc-500">{{ $obligation->subject_name }} outstanding</div>
                            @if ($obligation->quantityNeedsReview())
                                <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs leading-5 text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200">Quantity needs review: {{ $obligation->quantityMode()->isCountable() ? 'whole-unit items cannot be fractional' : 'check the quantity and unit' }}.</div>
                            @endif
                        @else
                            <div class="mt-2"><x-status-badge :tone="$obligation->status === 'settled' || $obligation->status === 'fulfilled' ? 'success' : 'info'" :label="$obligation->status === 'settled' || $obligation->status === 'fulfilled' ? 'Completed' : 'In progress'" /></div>
                            <div class="mt-1 text-sm text-zinc-500">Commitment progress is recorded as dated updates.</div>
                        @endif
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">What completion means</div>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $obligation->completion_criteria ?: ($obligation->description ?: 'Add details or completion criteria when the obligation needs more context.') }}</p>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Evidence and history</div>
                        <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $obligation->transactions->count() }} movement{{ $obligation->transactions->count() === 1 ? '' : 's' }} · {{ $obligation->events->count() }} {{ $obligation->obligation_kind === 'money' ? 'context note' : 'fulfillment update' }}{{ $obligation->events->count() === 1 ? '' : 's' }}</div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($obligation->obligation_kind === 'money')
                                @can('manageTerms', $obligation)
                                    <flux:modal.trigger name="terms-{{ $obligation->id }}">
                                        <flux:button size="sm" variant="outline">Terms</flux:button>
                                    </flux:modal.trigger>
                                @endcan
                                <flux:modal.trigger name="scenarios-{{ $obligation->id }}">
                                    <flux:button size="sm" variant="outline">Scenarios</flux:button>
                                </flux:modal.trigger>
                                @can('manageSchedule', $obligation)
                                    @if ($obligation->currentPositionDirection() === 'receivable')
                                        <flux:modal.trigger name="collection-schedule-{{ $obligation->id }}">
                                            <flux:button size="sm" variant="outline">Collections</flux:button>
                                        </flux:modal.trigger>
                                    @else
                                        <flux:modal.trigger name="schedule-{{ $obligation->id }}">
                                            <flux:button size="sm" variant="outline">Schedule</flux:button>
                                        </flux:modal.trigger>
                                    @endif
                                @endcan
                                @can('managePaymentInstructions', $obligation)
                                    <flux:modal.trigger name="payment-instructions-{{ $obligation->id }}">
                                        <flux:button size="sm" variant="outline">Payment instructions</flux:button>
                                    </flux:modal.trigger>
                                @endcan
                            @endif
                            @if ($obligation->isPawnCategory())
                                @can('manageAssets', $obligation)
                                    <flux:modal.trigger name="assets-{{ $obligation->id }}">
                                        <flux:button size="sm" variant="outline">Pawn assets</flux:button>
                                    </flux:modal.trigger>
                                @endcan
                            @endif
                            @if ($obligation->obligation_kind === 'asset')
                                @can('manageDelivery', $obligation)
                                    <flux:modal.trigger name="delivery-{{ $obligation->id }}">
                                        <flux:button size="sm" variant="outline">Delivery &amp; handover</flux:button>
                                    </flux:modal.trigger>
                                @endcan
                            @endif
                            @can('manageCommunication', $obligation)
                                <flux:modal.trigger name="messages-{{ $obligation->id }}">
                                    <flux:button size="sm" variant="outline">Messages</flux:button>
                                </flux:modal.trigger>
                            @endcan
                        </div>
                    </div>
                </div>

                @if ($obligation->obligation_kind === 'money' && isset($calculationPreviews[$obligation->id]))
                    @php($preview = $calculationPreviews[$obligation->id])
                    <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">
                        <div class="text-sm font-semibold">{{ $preview['label'] }}</div>
                        @if ($preview['estimate'] !== null)
                            <div class="mt-1 text-lg font-semibold">{{ \App\Domain\Money\MoneyAmount::format($preview['estimate'], $obligation->currency) }}</div>
                        @endif
                        <p class="mt-1 text-xs leading-5 text-zinc-500">{{ $preview['explanation'] }}</p>
                        @if ($obligation->terms->first()?->interest_rate !== null)
                            <p class="mt-2 text-xs text-zinc-500">{{ \App\Domain\Money\Decimal::display($obligation->terms->first()->interest_rate) }}% interest · Terms effective {{ $obligation->terms->first()->effective_from?->format('d M Y') ?? 'date not recorded' }}</p>
                        @endif
                    </div>
                @endif

                <livewire:obligations.manage-parties :obligation="$obligation" :key="'obligation-parties-'.$obligation->id" />

                @if ($obligation->transactions->isNotEmpty())
                    <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">
                        <div class="mb-3 text-sm font-semibold">Movement history</div>
                        <div class="space-y-3">
                            @foreach ($obligation->transactions as $transaction)
                                <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-3 sm:flex-row sm:items-start sm:justify-between dark:border-zinc-700">
                                    <div>
                                        @php($transactionStatusTone = match ($transaction->status) { 'confirmed', 'recorded', 'completed' => 'success', 'pending', 'draft' => 'warning', 'failed', 'reversed' => 'danger', default => 'neutral' })
                                        <div class="flex flex-wrap items-center gap-2 font-medium"><span>{{ \Illuminate\Support\Str::headline($transaction->entry_type) }} · {{ \App\Domain\Money\MoneyAmount::format($transaction->amount, $transaction->currency) }}</span><x-status-badge :tone="$transactionStatusTone" :label="\Illuminate\Support\Str::headline($transaction->status)" /></div>
                                        <div class="mt-1 text-xs text-zinc-500">{{ $transaction->occurred_on?->format('d M Y') ?? 'Date not recorded' }}@if ($transaction->note) · {{ $transaction->note }}@endif</div>
                                        @if ($transaction->documents->isNotEmpty())
                                            <div class="mt-2 text-xs text-sky-700 dark:text-sky-300">{{ $transaction->documents->count() }} evidence item{{ $transaction->documents->count() === 1 ? '' : 's' }} attached</div>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 gap-2">
                                        @can('recordTransaction', $obligation)
                                            <flux:modal.trigger name="edit-transaction-{{ $transaction->id }}">
                                                <flux:button size="sm" variant="outline">Edit movement</flux:button>
                                            </flux:modal.trigger>
                                        @endcan
                                        @can('uploadDocument', $obligation)
                                            <flux:modal.trigger name="transaction-evidence-{{ $transaction->id }}">
                                                <flux:button size="sm" variant="ghost">Add evidence</flux:button>
                                            </flux:modal.trigger>
                                        @endcan
                                    </div>
                                </div>
                                @if ($transaction->documents->isNotEmpty())
                                    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                                        @foreach ($transaction->documents as $document)
                                            @include('livewire.obligations.partials.evidence-record', ['document' => $document])
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($obligation->events->isNotEmpty())
                    <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">
                        <div class="mb-3 text-sm font-semibold">{{ $obligation->obligation_kind === 'money' ? 'Context history' : 'Fulfillment history' }}</div>
                        <div class="space-y-3">
                            @foreach ($obligation->events as $event)
                                <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-3 sm:flex-row sm:items-start sm:justify-between dark:border-zinc-700">
                                    <div>
                                        <div class="font-medium">{{ \Illuminate\Support\Str::headline($event->event_type) }}@if ($event->quantity) · {{ \App\Domain\Money\Decimal::display($event->quantity) }} {{ $event->unit }}@endif</div>
                                        <div class="mt-1 text-xs text-zinc-500">{{ $event->occurred_on?->format('d M Y') ?? 'Date not recorded' }}@if ($event->note) · {{ $event->note }}@endif</div>
                                        @if ($event->documents->isNotEmpty())
                                            <div class="mt-2 text-xs text-sky-700 dark:text-sky-300">{{ $event->documents->count() }} evidence item{{ $event->documents->count() === 1 ? '' : 's' }} attached</div>
                                        @endif
                                    </div>
                                    @can('uploadDocument', $obligation)
                                        <div class="shrink-0">
                                            <flux:modal.trigger name="event-evidence-{{ $event->id }}">
                                                <flux:button size="sm" variant="ghost">Add evidence</flux:button>
                                            </flux:modal.trigger>
                                        </div>
                                    @endcan
                                </div>
                                @if ($event->documents->isNotEmpty())
                                    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                                        @foreach ($event->documents as $document)
                                            @include('livewire.obligations.partials.evidence-record', ['document' => $document])
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-zinc-300 px-6 py-14 text-center dark:border-zinc-700">
                <flux:heading size="lg">This record has no obligations yet</flux:heading>
                <flux:text class="mt-2">Add the first component to begin tracking what is owed.</flux:text>
            </div>
        @endforelse
    </section>

    @if ($record->documents->isNotEmpty())
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">Shared evidence</flux:heading>
                    <flux:text class="mt-1 text-sm">Evidence that supports the arrangement as a whole.</flux:text>
                </div>
                @if ($record->obligations->first())
                    @can('uploadDocument', $record->obligations->first())
                        <flux:modal.trigger name="record-evidence">
                            <flux:button size="sm" variant="ghost">Add shared evidence</flux:button>
                        </flux:modal.trigger>
                    @endcan
                @endif
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($record->documents as $document)
                    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                        @include('livewire.obligations.partials.evidence-record', ['document' => $document])
                    </div>
                @endforeach
            </div>
        </section>
    @elseif ($record->obligations->first())
        <section class="rounded-2xl border border-dashed border-zinc-300 p-5 dark:border-zinc-700">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">Shared evidence</flux:heading>
                    <flux:text class="mt-1 text-sm">Attach an agreement, message, receipt, photo, link, or note to the arrangement.</flux:text>
                </div>
                @can('uploadDocument', $record->obligations->first())
                    <flux:modal.trigger name="record-evidence">
                        <flux:button size="sm" variant="primary">Add shared evidence</flux:button>
                    </flux:modal.trigger>
                @endcan
            </div>
        </section>
    @endif

    @if ($record->obligations->first() && auth()->user()->can('uploadDocument', $record->obligations->first()))
        <flux:modal name="record-evidence" class="w-full max-w-xl">
            <livewire:documents.upload :obligation="$record->obligations->first()" modal-name="record-evidence" :key="'record-evidence-'.$record->id" />
        </flux:modal>
    @endif

    @can('update', $record)
        <flux:modal name="add-obligation" class="w-full max-w-2xl">
            <livewire:records.add-obligation :record="$record" :key="'add-obligation-'.$record->id" />
        </flux:modal>
    @endcan

    @foreach ($record->obligations as $obligation)
        @if ($obligation->obligation_kind === 'money')
            @can('recordTransaction', $obligation)
                <flux:modal name="record-transaction-{{ $obligation->id }}" class="w-full max-w-xl">
                    <livewire:obligations.record-transaction :obligation="$obligation" modal-name="record-transaction-{{ $obligation->id }}" :key="'record-transaction-'.$obligation->id" />
                </flux:modal>
            @endcan
        @else
            @can('recordEvent', $obligation)
                <flux:modal name="record-event-{{ $obligation->id }}" class="w-full max-w-xl">
                    <livewire:obligations.record-event :obligation="$obligation" modal-name="record-event-{{ $obligation->id }}" :key="'record-event-'.$obligation->id" />
                </flux:modal>
            @endcan
        @endif

        @can('uploadDocument', $obligation)
            <flux:modal name="obligation-evidence-{{ $obligation->id }}" class="w-full max-w-xl">
                <livewire:documents.upload :obligation="$obligation" modal-name="obligation-evidence-{{ $obligation->id }}" :key="'obligation-evidence-'.$obligation->id" />
            </flux:modal>
        @endcan

        @if ($obligation->obligation_kind === 'money')
            @can('recordEvent', $obligation)
                <flux:modal name="record-event-{{ $obligation->id }}" class="w-full max-w-xl">
                    <livewire:obligations.record-event :obligation="$obligation" modal-name="record-event-{{ $obligation->id }}" :key="'record-event-'.$obligation->id" />
                </flux:modal>
            @endcan

            @can('manageTerms', $obligation)
                <flux:modal name="terms-{{ $obligation->id }}" class="w-full max-w-xl">
                    <livewire:obligations.edit-terms :obligation="$obligation" :key="'terms-'.$obligation->id" />
                </flux:modal>
            @endcan

            <flux:modal name="scenarios-{{ $obligation->id }}" class="w-full max-w-xl">
                <livewire:obligations.scenarios :obligation="$obligation" :key="'scenarios-'.$obligation->id" />
            </flux:modal>

            @can('manageSchedule', $obligation)
                @if ($obligation->currentPositionDirection() === 'receivable')
                    <flux:modal name="collection-schedule-{{ $obligation->id }}" class="w-full max-w-xl">
                        <livewire:obligations.manage-collection-schedule :obligation="$obligation" :key="'collection-schedule-'.$obligation->id" />
                    </flux:modal>
                @else
                    <flux:modal name="schedule-{{ $obligation->id }}" class="w-full max-w-xl">
                        <livewire:obligations.manage-schedule :obligation="$obligation" :key="'schedule-'.$obligation->id" />
                    </flux:modal>
                @endif
            @endcan

            @can('managePaymentInstructions', $obligation)
                <flux:modal name="payment-instructions-{{ $obligation->id }}" class="w-full max-w-xl">
                    <livewire:obligations.manage-payment-instructions :obligation="$obligation" :key="'payment-instructions-'.$obligation->id" />
                </flux:modal>
            @endcan
        @endif

        @if ($obligation->isPawnCategory())
            @can('manageAssets', $obligation)
                <flux:modal name="assets-{{ $obligation->id }}" class="w-full max-w-xl">
                    <livewire:obligations.manage-assets :obligation="$obligation" :key="'assets-'.$obligation->id" />
                </flux:modal>
            @endcan
        @endif

        @if ($obligation->obligation_kind === 'asset')
            @can('manageDelivery', $obligation)
                <flux:modal name="delivery-{{ $obligation->id }}" class="w-full max-w-xl">
                    <livewire:obligations.manage-delivery-instructions :obligation="$obligation" :key="'delivery-'.$obligation->id" />
                </flux:modal>
            @endcan
        @endif

        @can('manageCommunication', $obligation)
            <flux:modal name="messages-{{ $obligation->id }}" class="w-full max-w-xl">
                <livewire:communication.composer :obligation="$obligation" :key="'messages-'.$obligation->id" />
            </flux:modal>
        @endcan

        @foreach ($obligation->transactions as $transaction)
            @can('recordTransaction', $obligation)
                <flux:modal name="edit-transaction-{{ $transaction->id }}" class="w-full max-w-xl">
                    <livewire:obligations.edit-transaction :obligation="$obligation" :transaction="$transaction" :key="'edit-transaction-'.$transaction->id" />
                </flux:modal>
            @endcan
            @can('uploadDocument', $obligation)
                <flux:modal name="transaction-evidence-{{ $transaction->id }}" class="w-full max-w-xl">
                    <livewire:documents.upload :obligation="$obligation" :transaction="$transaction" modal-name="transaction-evidence-{{ $transaction->id }}" :key="'transaction-evidence-'.$transaction->id" />
                </flux:modal>
            @endcan
        @endforeach

        @foreach ($obligation->events as $event)
            @can('uploadDocument', $obligation)
                <flux:modal name="event-evidence-{{ $event->id }}" class="w-full max-w-xl">
                    <livewire:documents.upload :obligation="$obligation" :event="$event" modal-name="event-evidence-{{ $event->id }}" :key="'event-evidence-'.$event->id" />
                </flux:modal>
            @endcan
        @endforeach
    @endforeach
</div>
