<div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
    <div>
        <a href="{{ route('promises.index', $profile) }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">← Back to promises</a>
        <div class="app-eyebrow mt-6">Promise detail</div>
        @if ($editingDetails)
            <form wire:submit="updateDetails" class="mt-4 space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:input wire:model="promiseTitle" label="Promise title" required />
                <flux:input wire:model="promiseDueOn" type="date" label="Due date" description="Past dates are allowed when an agreement was renegotiated." />
                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Save details</flux:button>
                    <flux:button type="button" wire:click="$set('editingDetails', false)" variant="ghost" wire:loading.attr="disabled">Cancel</flux:button>
                </div>
            </form>
        @else
            <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">{{ $record->title }}</flux:heading>
        @endif
        <flux:text class="mt-2 leading-6">Keep the context next to the promise so the movement history stays focused.</flux:text>
        @can('update', $record)
            <div class="mt-4">
                @if (! $record->hasConfirmedMovements())
                    <flux:button wire:click="beginEditingDetails" variant="ghost" wire:loading.attr="disabled">Edit details</flux:button>
                @else
                    <flux:text class="text-sm text-zinc-500">Details are locked because this promise has confirmed movements. <a href="#history" class="font-medium text-emerald-700 hover:underline">Void and correct a movement</a> before editing.</flux:text>
                @endif
                @if ($record->is_archived)
                    <flux:button wire:click="restore" wire:confirm="Restore this promise?" variant="ghost" wire:loading.attr="disabled">Restore promise</flux:button>
                @else
                    <flux:button wire:click="archive" wire:confirm="Archive this promise?" variant="ghost" wire:loading.attr="disabled">Archive promise</flux:button>
                @endif
            </div>
        @endcan
    </div>

    @can('update', $record)
        <section class="app-card rounded-2xl p-5 sm:p-7">
            <form wire:submit="saveNote" class="space-y-4">
                <flux:textarea wire:model="recordNote" label="Note" placeholder="Optional context, such as what this promise is for." rows="3" />
                <div class="flex justify-end">
                    <flux:button type="submit" variant="ghost" wire:loading.attr="disabled">Save note</flux:button>
                </div>
            </form>
        </section>
    @elseif ($record->note)
        <flux:text class="leading-6">{{ $record->note }}</flux:text>
    @endcan

    <section id="evidence" x-data class="app-card rounded-2xl p-5 sm:p-7">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">Evidence</flux:heading>
                <flux:text class="mt-1">Keep agreements, receipts, and context beside the promise.</flux:text>
                <flux:text class="mt-2 text-sm text-emerald-700 dark:text-emerald-300">{{ $this->evidenceTargetDescription() }}</flux:text>
            </div>
            @can('update', $record)
                <flux:button wire:click="prepareRecordEvidence" variant="ghost" wire:loading.attr="disabled">Add evidence</flux:button>
            @endcan
        </div>
        @if ($record->attachments->isNotEmpty())
            <div class="mt-5 space-y-2">
                @foreach ($record->attachments as $attachment)
                    <div wire:key="record-attachment-{{ $attachment->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-900">
                        <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('attachments.download', now()->addMinutes(15), ['profile' => $profile, 'attachment' => $attachment]) }}" class="font-medium text-emerald-700 hover:underline">{{ $attachment->original_name }}</a>
                        @can('delete', $attachment)
                            <button type="button" wire:click="detachEvidence('{{ $attachment->id }}')" wire:confirm="Remove this evidence?" wire:loading.attr="disabled" class="text-xs text-red-700 hover:underline">Remove</button>
                        @endcan
                    </div>
                @endforeach
            </div>
        @endif
        @can('update', $record)
            @if ($showEvidenceForm)
                <form wire:submit="saveEvidence" class="mt-5 grid gap-3 border-t border-zinc-200/80 pt-5 sm:grid-cols-3 dark:border-zinc-700/80">
                    <input wire:model="evidenceFile" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,.csv,.doc,.docx,.xls,.xlsx" class="block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm sm:col-span-2" />
                    <flux:select wire:model="evidenceCategory" label="Category">
                        @foreach (\App\Domain\Enums\AttachmentCategory::cases() as $category)
                            <flux:select.option value="{{ $category->value }}">{{ ucfirst($category->value) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="evidenceLinkUrl" label="Or link URL" placeholder="https://..." class="sm:col-span-2" />
                    <div class="flex items-end"><flux:button type="submit" variant="primary" wire:loading.attr="disabled" class="w-full">Save evidence</flux:button></div>
                    @error('evidenceFile')<flux:text class="text-red-700 sm:col-span-3">{{ $message }}</flux:text>@enderror
                    @error('linkUrl')<flux:text class="text-red-700 sm:col-span-3">{{ $message }}</flux:text>@enderror
                    @error('evidence')<flux:text class="text-red-700 sm:col-span-3">{{ $message }}</flux:text>@enderror
                </form>
            @endif
        @endcan
    </section>

    <section class="app-card rounded-2xl p-5 sm:p-7">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="text-sm text-zinc-500">{{ $obligation->direction->label() }}</div>
                @foreach ($positions as $position)
                    <div wire:key="position-{{ $position['currency'] }}" class="mt-2">
                        <div class="text-3xl font-semibold tracking-tight">{{ \App\Domain\Money\Money::display($position['amount'], $position['currency']) }}</div>
                        <div class="mt-1 text-sm {{ $position['direction'] === \App\Domain\Enums\Direction::Receivable ? 'text-cyan-700' : 'text-emerald-700' }}">{{ $position['label'] }} · {{ $position['currency'] }}</div>
                    </div>
                @endforeach
            </div>
            <div class="flex flex-col gap-2 sm:items-end">
                @if ($obligation->due_on)
                    <div class="text-sm text-zinc-500">Due {{ $obligation->due_on->format('d M Y') }}</div>
                @endif
                <x-status-badge :tone="$recordStatus === \App\Domain\Enums\ObligationStatus::Settled ? 'success' : 'info'" :label="ucfirst($recordStatus->value)" />
            </div>
        </div>

        @if ($obligation->subject_type === \App\Domain\Enums\SubjectType::Money && $obligation->status !== \App\Domain\Enums\ObligationStatus::Settled)
            <div class="mt-6 border-t border-zinc-200/80 pt-5 dark:border-zinc-700/80">
                <form wire:submit="recordPartial" class="grid gap-3 sm:grid-cols-[minmax(0,12rem)_minmax(0,1fr)_auto] sm:items-end">
                    <flux:input wire:model="partialAmount" label="Record payment" placeholder="0.00" inputmode="decimal" />
                    <flux:input wire:model="movementNote" label="Note" placeholder="Optional note" />
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Record</flux:button>
                </form>
                <flux:button wire:click="markSettled" wire:confirm="Record the remaining balance as settled?" variant="ghost" wire:loading.attr="disabled" class="mt-3">Mark settled</flux:button>
            </div>
        @endif

        @if ($obligation->subject_type === \App\Domain\Enums\SubjectType::Quantity && $obligation->quantitySubject && $quantityPosition)
            <div class="mt-6 border-t border-zinc-200/80 pt-5 dark:border-zinc-700/80">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ $obligation->quantitySubject->name }}</flux:heading>
                        <flux:text class="mt-1">{{ $quantityPosition['returned'] }} of {{ $quantityPosition['total'] }} {{ $obligation->quantitySubject->unit }} returned</flux:text>
                    </div>
                    <x-status-badge :tone="$obligation->status === \App\Domain\Enums\ObligationStatus::Settled ? 'success' : 'info'" :label="ucfirst($obligation->status->value)" />
                </div>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                    <div class="h-full rounded-full bg-cyan-600" style="width: {{ min(100, ((float) $quantityPosition['returned'] / max(0.0001, (float) $quantityPosition['total'])) * 100) }}%"></div>
                </div>
                @if ($obligation->status !== \App\Domain\Enums\ObligationStatus::Settled)
                    <form wire:submit="recordQuantityReturn" class="mt-5 grid gap-3 sm:grid-cols-[minmax(0,10rem)_minmax(0,10rem)_minmax(0,1fr)_auto] sm:items-end">
                        <flux:input wire:model="quantityReturn" label="Return" placeholder="0" inputmode="decimal" />
                        <flux:input wire:model="quantityReturnedOn" type="date" label="Date" />
                        <flux:input wire:model="movementNote" label="Note" placeholder="Optional note" />
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Record</flux:button>
                    </form>
                @endif
            </div>
        @endif

        @if ($obligation->subject_type === \App\Domain\Enums\SubjectType::Commitment && $obligation->commitmentSubject)
            <div class="mt-6 border-t border-zinc-200/80 pt-5 dark:border-zinc-700/80">
                <flux:heading size="lg">Completion</flux:heading>
                <blockquote class="mt-3 border-s-2 border-amber-400 ps-4 text-sm text-zinc-700 dark:text-zinc-200">{{ $obligation->commitmentSubject->done_criteria }}</blockquote>
                @if ($obligation->commitmentSubject->completed_at)
                    <flux:text class="mt-3">Completed {{ $obligation->commitmentSubject->completed_at->format('d M Y') }}{{ $obligation->commitmentSubject->completion_note ? ' · '.$obligation->commitmentSubject->completion_note : '' }}</flux:text>
                    <form wire:submit="reopenCommitment" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                        <flux:input wire:model="reopenReason" label="Why reopen?" placeholder="Optional explanation" required />
                        <flux:button type="submit" variant="ghost" wire:loading.attr="disabled">Reopen</flux:button>
                    </form>
                @else
                    <form wire:submit="completeCommitment" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                        <flux:input wire:model="completionNote" label="Note" placeholder="Optional completion note" />
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Mark complete</flux:button>
                    </form>
                @endif
            </div>
        @endif
    </section>

    <section class="app-card rounded-2xl p-5 sm:p-7">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">Promise parts</flux:heading>
                <flux:text class="mt-1">Each part keeps its own direction, subject, and evidence.</flux:text>
            </div>
            <x-status-badge :tone="$recordStatus === \App\Domain\Enums\ObligationStatus::Settled ? 'success' : 'info'" :label="ucfirst($recordStatus->value)" />
        </div>
        <div class="mt-5 divide-y divide-zinc-200/80 rounded-xl border border-zinc-200/80 dark:divide-zinc-700/80 dark:border-zinc-700/80">
            @foreach ($obligations as $part)
                <div wire:key="part-{{ $part->id }}" class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="font-medium">{{ ucfirst($part->subject_type->value) }} · {{ $part->direction->label() }}</div>
                        <div class="text-sm text-zinc-500">
                            @if ($part->subject_type === \App\Domain\Enums\SubjectType::Money && $part->moneySubject)
                                {{ $part->moneySubject->currency }} money
                            @elseif ($part->subject_type === \App\Domain\Enums\SubjectType::Quantity && $part->quantitySubject)
                                {{ $part->quantitySubject->name }} · {{ $part->quantitySubject->unit }}
                            @elseif ($part->commitmentSubject)
                                {{ $part->commitmentSubject->done_criteria }}
                            @endif
                        </div>
                    </div>
                    <x-status-badge :tone="$part->status === \App\Domain\Enums\ObligationStatus::Settled ? 'success' : 'info'" :label="ucfirst($part->status->value)" />
                </div>
            @endforeach
        </div>
        @can('update', $record)
            <form wire:submit="addObligation" class="mt-6 space-y-4 border-t border-zinc-200/80 pt-5 dark:border-zinc-700/80">
                <flux:heading size="sm">Add another part</flux:heading>
                <div class="grid gap-3 sm:grid-cols-3">
                    <flux:select wire:model="partDirection" label="Direction">
                        <option value="{{ \App\Domain\Enums\Direction::Payable->value }}">{{ \App\Domain\Enums\Direction::Payable->label() }}</option>
                        <option value="{{ \App\Domain\Enums\Direction::Receivable->value }}">{{ \App\Domain\Enums\Direction::Receivable->label() }}</option>
                    </flux:select>
                    <flux:select wire:model.live="partSubjectType" label="Subject">
                        <option value="{{ \App\Domain\Enums\SubjectType::Money->value }}">Money</option>
                        <option value="{{ \App\Domain\Enums\SubjectType::Quantity->value }}">Item or time</option>
                        <option value="{{ \App\Domain\Enums\SubjectType::Commitment->value }}">Commitment</option>
                    </flux:select>
                    <flux:input wire:model="partDueOn" type="date" label="Due date" />
                </div>
                @if ($partSubjectType === \App\Domain\Enums\SubjectType::Money->value)
                    <flux:input wire:model="partAmount" label="Amount" inputmode="decimal" placeholder="0.00" />
                @elseif ($partSubjectType === \App\Domain\Enums\SubjectType::Quantity->value)
                    <div class="grid gap-3 sm:grid-cols-3">
                        <flux:input wire:model="partQuantityName" label="What is it?" />
                        <flux:input wire:model="partQuantityTotal" label="How many?" inputmode="decimal" />
                        <flux:input wire:model="partQuantityUnit" label="Unit" />
                    </div>
                    <label class="flex items-center gap-3 text-sm"><input wire:model="partIsFractionable" type="checkbox" class="rounded border-zinc-300 text-emerald-600" /> Fractions allowed</label>
                @else
                    <flux:textarea wire:model="partDoneCriteria" label="What does completion mean?" rows="2" />
                @endif
                <flux:textarea wire:model="partNote" label="Part note" rows="2" />
                <div class="flex justify-end"><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Add part</flux:button></div>
            </form>
        @endcan
    </section>

    <section class="app-card rounded-2xl p-5 sm:p-7">
        <div class="flex items-start justify-between gap-4">
            <div><flux:heading size="lg">Follow up</flux:heading><flux:text class="mt-1">Reminders create a database notification only; they never change a balance.</flux:text></div>
            @if ($obligation->due_on)<flux:text class="text-sm">Due {{ $obligation->due_on->format('d M Y') }}</flux:text>@endif
        </div>
        @can('update', $record)
            <form wire:submit="scheduleReminder" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end"><flux:input wire:model="reminderDate" type="date" label="Remind me on" required /><flux:button type="submit" variant="ghost" wire:loading.attr="disabled">Remind me</flux:button></form>
        @endcan
        @if ($obligation->reminders->isNotEmpty())
            <div class="mt-5 space-y-2">@foreach ($obligation->reminders as $reminder)<div wire:key="reminder-{{ $reminder->id }}" class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-900"><span>{{ ucfirst($reminder->status->value) }} · {{ $reminder->remind_on->format('d M Y') }}</span>@if ($reminder->status !== \App\Domain\Enums\ReminderStatus::Dismissed && $reminder->status !== \App\Domain\Enums\ReminderStatus::Sent)<div class="flex gap-2"><button type="button" wire:click="snoozeReminder('{{ $reminder->id }}')" wire:loading.attr="disabled" class="text-zinc-500 hover:underline">Snooze</button><button type="button" wire:click="dismissReminder('{{ $reminder->id }}')" wire:confirm="Dismiss this reminder?" wire:loading.attr="disabled" class="text-red-700 hover:underline">Dismiss</button></div>@endif</div>@endforeach</div>
        @endif
    </section>

    <section class="app-card overflow-hidden rounded-2xl">
        <div class="border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80">
            <flux:heading size="lg">History</flux:heading>
            <flux:text class="mt-1 text-sm">Confirmed movements are the source of every balance above.</flux:text>
        </div>
        <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
            @if ($obligation->subject_type === \App\Domain\Enums\SubjectType::Money)
                @forelse ($movements as $movement)
                <div id="history" wire:key="movement-{{ $movement->id }}" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="font-medium">{{ str_replace('_', ' ', ucfirst($movement->entry->value)) }}</div>
                        <div class="mt-1 text-sm text-zinc-500">{{ $movement->occurred_on->format('d M Y') }}{{ $movement->note ? ' · '.$movement->note : '' }}</div>
                        @if ($movement->status === \App\Domain\Enums\MovementStatus::Voided)
                            <div class="mt-1 text-xs text-red-700">Voided: {{ $movement->void_reason }}</div>
                        @endif
                        @if ($movement->attachments->isNotEmpty())
                            <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                @foreach ($movement->attachments as $attachment)
                                    <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('attachments.download', now()->addMinutes(15), ['profile' => $profile, 'attachment' => $attachment]) }}" class="text-emerald-700 hover:underline">{{ $attachment->original_name }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-4 sm:text-right">
                        <div class="font-semibold">{{ \App\Domain\Money\Money::display($movement->amount_minor, $movement->currency) }}</div>
                        @can('update', $record)
                            @if ($evidenceMovementId === (string) $movement->id)
                                <span class="text-xs font-semibold text-emerald-700">Selected for evidence</span>
                            @else
                                <button type="button" wire:click="prepareMovementEvidence('{{ $movement->id }}')" x-on:click="document.getElementById('evidence')?.scrollIntoView({ behavior: 'smooth', block: 'start' })" wire:loading.attr="disabled" class="text-xs font-semibold text-emerald-700 hover:underline">Attach</button>
                            @endif
                        @endcan
                        @if ($movement->status === \App\Domain\Enums\MovementStatus::Confirmed)
                            <button type="button" wire:click="voidMovement('{{ $movement->id }}')" wire:confirm="Void this movement?" wire:loading.attr="disabled" class="text-xs font-semibold text-red-700 hover:underline">Void</button>
                        @endif
                    </div>
                </div>
                @empty
                    <div class="px-5 py-8 text-sm text-zinc-500">No movements recorded.</div>
                @endforelse
            @elseif ($obligation->subject_type === \App\Domain\Enums\SubjectType::Quantity)
                @forelse ($returns as $return)
                    <div wire:key="quantity-return-{{ $return->id }}" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="font-medium">{{ $return->quantity }} {{ $obligation->quantitySubject?->unit }} returned</div>
                            <div class="mt-1 text-sm text-zinc-500">{{ $return->occurred_on->format('d M Y') }}{{ $return->note ? ' · '.$return->note : '' }}</div>
                            @if ($return->status === \App\Domain\Enums\MovementStatus::Voided)
                                <div class="mt-1 text-xs text-red-700">Voided: {{ $return->void_reason }}</div>
                            @endif
                            @if ($return->attachments->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                    @foreach ($return->attachments as $attachment)
                                        <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('attachments.download', now()->addMinutes(15), ['profile' => $profile, 'attachment' => $attachment]) }}" class="text-emerald-700 hover:underline">{{ $attachment->original_name }}</a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        @can('update', $record)
                            @if ($evidenceReturnId === (string) $return->id)
                                <span class="text-xs font-semibold text-emerald-700">Selected for evidence</span>
                            @else
                                <button type="button" wire:click="prepareReturnEvidence('{{ $return->id }}')" x-on:click="document.getElementById('evidence')?.scrollIntoView({ behavior: 'smooth', block: 'start' })" wire:loading.attr="disabled" class="text-xs font-semibold text-emerald-700 hover:underline">Attach</button>
                            @endif
                        @endcan
                    </div>
                @empty
                    <div class="px-5 py-8 text-sm text-zinc-500">No returns recorded.</div>
                @endforelse
            @else
                <div class="px-5 py-8 text-sm text-zinc-500">Commitments keep progress in their note and completion history.</div>
            @endif
        </div>
    </section>

    <section class="app-card rounded-2xl p-5 sm:p-7">
        <div>
            <flux:heading size="lg">Activity</flux:heading>
            <flux:text class="mt-1">A quiet record of changes to this promise.</flux:text>
        </div>
        <div class="mt-5 space-y-4">
            @forelse ($activities as $activity)
                <div wire:key="activity-{{ $activity->id }}" class="flex gap-3 text-sm">
                    <div class="mt-1 size-2 shrink-0 rounded-full bg-zinc-300 dark:bg-zinc-600"></div>
                    <div>
                        <div class="font-medium">{{ \App\Domain\ActivityLabels::label($activity->action) }}</div>
                        <div class="mt-1 text-zinc-500">{{ $activity->actor?->name ?? 'System' }} · {{ $activity->occurred_at->format('d M Y, H:i') }}</div>
                    </div>
                </div>
            @empty
                <flux:text class="text-sm">No activity recorded yet.</flux:text>
            @endforelse
        </div>
        <flux:text class="mt-5 text-xs text-zinc-500">Full history is available in export.</flux:text>
    </section>

    @can('delete', $record)
        @if ($canDeleteRecord)
            <section class="app-card rounded-2xl border border-red-200 p-5 sm:p-7 dark:border-red-900/70">
                <flux:heading size="lg">Danger zone</flux:heading>
                <flux:text class="mt-1">This promise has no confirmed movements, returns, or attachments.</flux:text>
                <div class="mt-5 flex flex-wrap gap-3">
                    <flux:button wire:click="restart" wire:confirm="Delete this promise and start a fresh copy? This cannot be undone." variant="ghost" wire:loading.attr="disabled">Restart promise</flux:button>
                    <flux:button wire:click="delete" wire:confirm="Delete this promise? This cannot be undone." variant="danger" wire:loading.attr="disabled">Delete promise</flux:button>
                </div>
            </section>
        @else
            <section class="app-card rounded-2xl border border-zinc-200 p-5 sm:p-7 dark:border-zinc-700">
                <flux:heading size="lg">Deletion unavailable</flux:heading>
                <flux:text class="mt-1">Delete is available only after confirmed movements, returns, and attachments are removed.</flux:text>
            </section>
        @endif
    @endcan
</div>
