<div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
    <div>
        <a href="{{ route('promises.index', $profile) }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">{{ __('promises.back_to_promises') }}</a>
        <div class="app-eyebrow mt-6">{{ __('promises.detail') }}</div>
        @if ($editingDetails)
            <form wire:submit="updateDetails" class="mt-4 space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:input wire:model="promiseTitle" :label="__('promises.promise_title')" required />
                <flux:input wire:model="promiseDueOn" type="date" :label="__('promises.due_date')" :description="__('promises.past_dates_allowed')" />
                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('promises.save_details') }}</flux:button>
                    <flux:button type="button" wire:click="$set('editingDetails', false)" variant="ghost" wire:loading.attr="disabled">{{ __('promises.cancel') }}</flux:button>
                </div>
            </form>
        @else
            <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">{{ $record->title }}</flux:heading>
            @if ($obligation->due_on)
                <flux:text class="mt-2 text-sm">{{ __('promises.due', ['date' => $obligation->due_on->format('d M Y')]) }}@if ($hijriDueDates[$obligation->getKey()] ?? null) · {{ $hijriDueDates[$obligation->getKey()] }} {{ __('promises.hijri_suffix') }}@endif</flux:text>
            @endif
        @endif
        <flux:text class="mt-2 leading-6">{{ __('promises.keep_context') }}</flux:text>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <x-status-badge :tone="$record->visibility === \App\Domain\Enums\RecordVisibility::Private ? 'warning' : 'info'" :label="__('promises.visibility_labels.'.$record->visibility->value)" />
            @if ($obligation->status === \App\Domain\Enums\ObligationStatus::Settled && $obligation->closure_reason)
                <span class="text-sm text-zinc-500">{{ __('promises.closure_reasons.'.$obligation->closure_reason->value) }}</span>
            @endif
        </div>
        @can('update', $record)
            <div class="mt-4">
                <flux:button wire:click="beginEditingDetails" variant="ghost" wire:loading.attr="disabled">{{ __('promises.edit_details') }}</flux:button>
                @if ($record->is_archived)
                    <flux:button wire:click="restore" wire:confirm="{{ __('promises.restore_confirm') }}" variant="ghost" wire:loading.attr="disabled">{{ __('promises.restore_promise') }}</flux:button>
                @else
                    <flux:button wire:click="archive" wire:confirm="{{ __('promises.archive_confirm') }}" variant="ghost" wire:loading.attr="disabled">{{ __('promises.archive_promise') }}</flux:button>
                @endif
            </div>
        @endcan
    </div>

    @can('createObligation', $profile)
        <flux:button wire:click="addAnotherLikeThis" variant="ghost" wire:loading.attr="disabled">{{ __('promises.add_another_like_this') }}</flux:button>
    @endcan

    @can('update', $record)
        @if ($chargeObligation)
            <section class="app-card rounded-2xl p-5 sm:p-7">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ __('promises.message') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('promises.not_sent_automatically') }}</flux:text>
                    </div>
                    @if ($chargeObligation->direction === \App\Domain\Enums\Direction::Payable)
                        <flux:button type="button" wire:click="requestPauseMessage" variant="ghost">{{ __('promises.request_pause') }}</flux:button>
                    @endif
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <flux:select wire:model.live="messageTemplate" :label="__('promises.template')">
                        @foreach ($messageTemplates as $templateKey)
                            <flux:select.option value="{{ $templateKey }}">{{ __('templates.labels.'.$templateKey, [], $messageLocale) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="messageLocale" :label="__('promises.language')">
                        <flux:select.option value="en">{{ __('promises.english') }}</flux:select.option>
                        <flux:select.option value="ms">{{ __('promises.bahasa_melayu') }}</flux:select.option>
                    </flux:select>
                </div>
                <div class="mt-3" x-data="{ copied: false }">
                    <label class="mt-3 block text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('promises.preview') }}<textarea x-ref="message" readonly rows="5" class="mt-1 block w-full rounded-xl border border-zinc-300 bg-zinc-50 px-3 py-2 text-sm text-zinc-800 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">{{ $messagePreview }}</textarea></label>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <flux:button type="button" variant="primary" x-on:click="navigator.clipboard.writeText($refs.message.value).then(() => copied = true)">{{ __('promises.copy_message') }}</flux:button>
                        <span x-show="copied" x-cloak class="self-center text-sm text-emerald-700">{{ __('promises.copied') }}</span>
                        @if ($whatsAppUrl)
                            <a href="{{ $whatsAppUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">{{ __('promises.open_whatsapp') }}</a>
                        @endif
                    </div>
                </div>
            </section>
        @endif
    @endcan

    @can('update', $record)
        <section class="app-card rounded-2xl p-5 sm:p-7">
            <form wire:submit="saveNote" class="space-y-4">
                <flux:textarea wire:model="recordNote" :label="__('promises.note')" :placeholder="__('promises.optional_note_context')" rows="3" />
                <div class="flex justify-end">
                    <flux:button type="submit" variant="ghost" wire:loading.attr="disabled">{{ __('promises.save_note') }}</flux:button>
                </div>
            </form>
        </section>
    @elseif ($record->note)
        <flux:text class="leading-6">{{ $record->note }}</flux:text>
    @endcan

    @can('update', $record)
        <section class="app-card rounded-2xl p-5 sm:p-7">
            <form wire:submit="saveVisibility" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <flux:select wire:model="visibility" :label="__('promises.visibility')">
                    @foreach (\App\Domain\Enums\RecordVisibility::cases() as $recordVisibility)
                        <flux:select.option value="{{ $recordVisibility->value }}">{{ __('promises.visibility_labels.'.$recordVisibility->value) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button type="submit" variant="ghost" wire:loading.attr="disabled">{{ __('promises.save_visibility') }}</flux:button>
            </form>
        </section>
    @endcan

    @can('createShare', $record)
        <section class="app-card rounded-2xl p-5 sm:p-7">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('promises.share_this_promise') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('promises.share_description') }}</flux:text>
                </div>
                <flux:button wire:click="createShare" variant="primary" wire:loading.attr="disabled">{{ __('promises.create_share_link') }}</flux:button>
            </div>
            @if ($shareUrl !== '')
                <div class="mt-4 break-all rounded-xl bg-zinc-50 p-3 text-sm dark:bg-zinc-900">
                    <a href="{{ $shareUrl }}" class="font-medium text-emerald-700 hover:underline">{{ $shareUrl }}</a>
                </div>
            @endif
            @if ($shares->isNotEmpty())
                <div class="mt-4 space-y-2">
                    @foreach ($shares as $share)
                        <div wire:key="share-{{ $share->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                            <span>{{ $share->revoked_at ? __('promises.share_status.revoked') : ($share->expires_at && $share->expires_at->isPast() ? __('promises.share_status.expired') : __('promises.share_status.active')) }} · {{ __('promises.created_on', ['date' => $share->created_at->format('d M Y')]) }}</span>
                            @if (! $share->revoked_at)
                                <button type="button" wire:click="revokeShare('{{ $share->id }}')" wire:confirm="{{ __('promises.confirm_revoke_share') }}" wire:loading.attr="disabled" class="text-red-700 hover:underline">{{ __('promises.revoke') }}</button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endcan

    <section class="app-card space-y-5 rounded-2xl p-5 sm:p-7">
        <div>
            <flux:heading size="lg">{{ __('promises.parties') }}</flux:heading>
            <flux:text class="mt-1">{{ __('promises.guarantors_help') }}</flux:text>
        </div>
        <div class="space-y-2">
            @forelse ($guarantors as $guarantor)
                <div wire:key="guarantor-{{ $guarantor->id }}" class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                    <span>{{ $guarantor->party?->displayName() }}</span>
                    @can('update', $record)
                        <button type="button" wire:click="removeGuarantor('{{ $guarantor->id }}')" wire:confirm="{{ __('promises.remove_guarantor_confirm') }}" wire:loading.attr="disabled" class="text-red-700 hover:underline">{{ __('app.remove') }}</button>
                    @endcan
                </div>
            @empty
                <flux:text>{{ __('promises.no_guarantors') }}</flux:text>
            @endforelse
        </div>
        @can('update', $record)
            <form wire:submit="addGuarantor" class="grid gap-3 border-t border-zinc-200/80 pt-4 sm:grid-cols-2 dark:border-zinc-700/80">
                <flux:select wire:model="guarantorPartyId" label="{{ __('promises.existing_party') }}">
                    <option value="">{{ __('promises.choose_party') }}</option>
                    @foreach ($shareParties as $shareParty)
                        <option value="{{ $shareParty->id }}">{{ $shareParty->displayName() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="newGuarantorName" label="{{ __('promises.or_new_guarantor') }}" />
                <div class="sm:col-span-2 sm:flex sm:justify-end"><flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('promises.add_guarantor') }}</flux:button></div>
                @error('partyId')<flux:text class="text-red-700 sm:col-span-2">{{ $message }}</flux:text>@enderror
                @error('partyName')<flux:text class="text-red-700 sm:col-span-2">{{ $message }}</flux:text>@enderror
            </form>
        @endcan
    </section>

    @foreach ($obligations as $shareObligation)
        @if ($shareObligation->subject_type === \App\Domain\Enums\SubjectType::Money && $shareObligation->moneySubject)
            <section wire:key="obligation-shares-{{ $shareObligation->id }}" class="app-card space-y-5 rounded-2xl p-5 sm:p-7">
                <div>
                    <flux:heading size="lg">{{ __('promises.obligation_shares') }}</flux:heading>
                    <flux:text class="mt-1">{{ $shareObligation->title }} · {{ __('promises.share_amounts_note') }}</flux:text>
                </div>
                @if ($shareObligation->shares->isNotEmpty())
                    <div class="space-y-2">
                        @foreach ($shareObligation->shares->sortBy('party_id') as $share)
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span>{{ $share->party?->displayName() }} · {{ number_format($share->share_bps / 100, 2) }}%</span>
                                <span class="font-semibold">{{ \App\Domain\Money\Money::display($shareAmounts[$shareObligation->id][$share->party_id] ?? 0, $shareObligation->moneySubject->currency) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:text>{{ __('promises.unsplit_primary_party') }}</flux:text>
                @endif
                @can('update', $record)
                    <form wire:submit="saveShares('{{ $shareObligation->id }}')" class="space-y-3 border-t border-zinc-200/80 pt-4 dark:border-zinc-700/80">
                        @foreach ($shareRows[$shareObligation->id] ?? [] as $index => $row)
                            <div wire:key="share-row-{{ $shareObligation->id }}-{{ $index }}" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-end">
                                <flux:select wire:model="shareRows.{{ $shareObligation->id }}.{{ $index }}.partyId" label="{{ __('promises.party') }}" required>
                                    <option value="">{{ __('promises.choose_party') }}</option>
                                    @foreach ($shareParties as $shareParty)
                                        <option value="{{ $shareParty->id }}">{{ $shareParty->displayName() }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:input wire:model="shareRows.{{ $shareObligation->id }}.{{ $index }}.percentage" label="{{ __('promises.share_percent') }}" inputmode="decimal" placeholder="50.00" required />
                                <flux:button type="button" wire:click="removeShareRow('{{ $shareObligation->id }}', {{ $index }})" variant="ghost">{{ __('app.remove') }}</flux:button>
                            </div>
                        @endforeach
                        <div class="flex flex-wrap justify-between gap-3">
                            <flux:button type="button" wire:click="addShareRow('{{ $shareObligation->id }}')" variant="ghost">{{ __('promises.add_share') }}</flux:button>
                            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('promises.save_shares') }}</flux:button>
                        </div>
                        @error('shares')<flux:text class="text-red-700">{{ $message }}</flux:text>@enderror
                        @foreach ($shareRows[$shareObligation->id] ?? [] as $index => $row)
                            @error("shareRows.{$shareObligation->id}.{$index}.percentage")<flux:text class="text-red-700">{{ $message }}</flux:text>@enderror
                        @endforeach
                    </form>
                @endcan
            </section>
        @endif
    @endforeach

    <section id="evidence" x-data class="app-card rounded-2xl p-5 sm:p-7">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('promises.evidence') }}</flux:heading>
                <flux:text class="mt-1">{{ __('promises.evidence_description') }}</flux:text>
                <flux:text class="mt-2 text-sm text-emerald-700 dark:text-emerald-300">{{ $this->evidenceTargetDescription() }}</flux:text>
            </div>
            @can('update', $record)
                <flux:button wire:click="prepareRecordEvidence" variant="ghost" wire:loading.attr="disabled">{{ __('promises.add_evidence') }}</flux:button>
            @endcan
        </div>
        @if ($record->attachments->isNotEmpty())
            <div class="mt-5 space-y-2">
                @foreach ($record->attachments as $attachment)
                    <div wire:key="record-attachment-{{ $attachment->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-900">
                        <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('attachments.download', now()->addMinutes(15), ['profile' => $profile, 'attachment' => $attachment]) }}" class="font-medium text-emerald-700 hover:underline">{{ $attachment->original_name }}</a>
                        @can('delete', $attachment)
                            <button type="button" wire:click="detachEvidence('{{ $attachment->id }}')" wire:confirm="{{ __('promises.remove_evidence_confirm') }}" wire:loading.attr="disabled" class="text-xs text-red-700 hover:underline">{{ __('app.remove') }}</button>
                        @endcan
                    </div>
                @endforeach
            </div>
        @endif
        @can('update', $record)
            @if ($showEvidenceForm)
                <form wire:submit="saveEvidence" class="mt-5 grid gap-3 border-t border-zinc-200/80 pt-5 sm:grid-cols-3 dark:border-zinc-700/80">
                    <input wire:model="evidenceFile" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,.csv,.doc,.docx,.xls,.xlsx" class="block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm sm:col-span-2" />
                    <flux:select wire:model="evidenceCategory" :label="__('promises.category')">
                        @foreach (\App\Domain\Enums\AttachmentCategory::cases() as $category)
                            <flux:select.option value="{{ $category->value }}">{{ __('promises.attachment_categories.'.$category->value) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="evidenceLinkUrl" :label="__('promises.or_link_url')" placeholder="https://..." class="sm:col-span-2" />
                    <div class="flex items-end"><flux:button type="submit" variant="primary" wire:loading.attr="disabled" class="w-full">{{ __('promises.save_evidence') }}</flux:button></div>
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
                <div class="text-sm text-zinc-500">{{ __('promises.directions.'.$obligation->direction->value) }}</div>
                @foreach ($positions as $position)
                    <div wire:key="position-{{ $position['currency'] }}" class="mt-2">
                        <div class="text-3xl font-semibold tracking-tight">{{ \App\Domain\Money\Money::display($position['amount'], $position['currency']) }}</div>
                        <div class="mt-1 text-sm {{ $position['direction'] === \App\Domain\Enums\Direction::Receivable ? 'text-cyan-700' : 'text-emerald-700' }}">{{ $position['direction'] ? __('promises.directions.'.$position['direction']->value) : __('promises.statuses.settled') }} · {{ $position['currency'] }}</div>
                    </div>
                @endforeach
                @foreach ($balanceBreakdowns as $currency => $breakdown)
                    <dl wire:key="breakdown-{{ $currency }}" class="mt-4 grid grid-cols-2 gap-x-6 gap-y-1 text-xs text-zinc-500 sm:grid-cols-3">
                        <div><dt>{{ __('promises.borrowed') }}</dt><dd class="font-medium text-zinc-800 dark:text-zinc-200">{{ \App\Domain\Money\Money::display($breakdown['principal_in'], $currency) }}</dd></div>
                        <div><dt>{{ __('promises.charges') }}</dt><dd class="font-medium text-zinc-800 dark:text-zinc-200">{{ \App\Domain\Money\Money::display($breakdown['charges_in'], $currency) }}</dd></div>
                        <div><dt>{{ __('promises.paid') }}</dt><dd class="font-medium text-zinc-800 dark:text-zinc-200">{{ \App\Domain\Money\Money::display($breakdown['settled_total'], $currency) }}</dd></div>
                        <div><dt>{{ __('promises.remaining_principal') }}</dt><dd class="font-medium text-zinc-800 dark:text-zinc-200">{{ \App\Domain\Money\Money::display($breakdown['unpaid_principal'], $currency) }}</dd></div>
                        <div><dt>{{ __('promises.remaining_charges') }}</dt><dd class="font-medium text-zinc-800 dark:text-zinc-200">{{ \App\Domain\Money\Money::display($breakdown['unpaid_charges'], $currency) }}</dd></div>
                    </dl>
                @endforeach
            </div>
            <div class="flex flex-col gap-2 sm:items-end">
                @if ($obligation->due_on)
                    <div class="text-sm text-zinc-500">{{ __('promises.final_due', ['date' => $obligation->due_on->format('d M Y')]) }}@if ($hijriDueDates[$obligation->getKey()] ?? null) · {{ $hijriDueDates[$obligation->getKey()] }} {{ __('promises.hijri_suffix') }}@endif</div>
                @endif
                <x-status-badge :tone="match ($obligation->status) { \App\Domain\Enums\ObligationStatus::Settled => 'success', \App\Domain\Enums\ObligationStatus::Disputed => 'danger', \App\Domain\Enums\ObligationStatus::Paused => 'warning', default => 'info' }" :label="__('promises.statuses.'.$obligation->status->value)" />
            </div>
        </div>

        @if ($obligation->subject_type === \App\Domain\Enums\SubjectType::Money && $obligation->status !== \App\Domain\Enums\ObligationStatus::Settled)
            <div class="mt-6 border-t border-zinc-200/80 pt-5 dark:border-zinc-700/80">
                @can('update', $record)
                <form wire:submit="recordPartial" class="grid gap-3 sm:grid-cols-[minmax(0,12rem)_minmax(0,1fr)_auto] sm:items-end">
                    <flux:input wire:model="partialAmount" :label="__('promises.record_payment')" placeholder="0.00" inputmode="decimal" />
                    <flux:input wire:model="movementNote" :label="__('promises.note')" :placeholder="__('promises.optional_note')" />
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('promises.record') }}</flux:button>
                </form>
                <flux:button wire:click="markSettled" wire:confirm="{{ __('promises.settle_confirm') }}" variant="ghost" wire:loading.attr="disabled" class="mt-3">{{ __('promises.mark_settled') }}</flux:button>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <form wire:submit="forgiveBalance" class="flex items-end gap-2">
                        <flux:input wire:model="forgiveAmount" :label="__('promises.forgive_amount')" placeholder="0.00" inputmode="decimal" />
                        <flux:button type="submit" variant="ghost" wire:loading.attr="disabled">{{ __('promises.forgive') }}</flux:button>
                    </form>
                    <form wire:submit="writeOffBalance" class="flex items-end gap-2">
                        <flux:input wire:model="writeOffAmount" :label="__('promises.write_off_amount')" placeholder="0.00" inputmode="decimal" />
                        <flux:button type="submit" variant="ghost" wire:loading.attr="disabled">{{ __('promises.write_off') }}</flux:button>
                    </form>
                </div>
                @endcan
            </div>
        @endif

        @if ($obligation->subject_type === \App\Domain\Enums\SubjectType::Quantity && $obligation->quantitySubject && $quantityPosition)
            <div class="mt-6 border-t border-zinc-200/80 pt-5 dark:border-zinc-700/80">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ $obligation->quantitySubject->name }}</flux:heading>
                        <flux:text class="mt-1">{{ __('promises.quantity_progress', ['returned' => $quantityPosition['returned'], 'total' => $quantityPosition['total'], 'unit' => $obligation->quantitySubject->unit]) }}</flux:text>
                    </div>
                    <x-status-badge :tone="$obligation->status === \App\Domain\Enums\ObligationStatus::Settled ? 'success' : 'info'" :label="__('promises.statuses.'.$obligation->status->value)" />
                </div>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                    <div class="h-full rounded-full bg-cyan-600" style="width: {{ min(100, ((float) $quantityPosition['returned'] / max(0.0001, (float) $quantityPosition['total'])) * 100) }}%"></div>
                </div>
                @if ($obligation->status !== \App\Domain\Enums\ObligationStatus::Settled)
                    @can('update', $record)
                    <form wire:submit="recordQuantityReturn" class="mt-5 grid gap-3 sm:grid-cols-[minmax(0,10rem)_minmax(0,10rem)_minmax(0,1fr)_auto] sm:items-end">
                        <flux:input wire:model="quantityReturn" :label="__('promises.return')" placeholder="0" inputmode="decimal" />
                        <flux:input wire:model="quantityReturnedOn" type="date" :label="__('promises.date')" />
                        <flux:input wire:model="movementNote" :label="__('promises.note')" :placeholder="__('promises.optional_note')" />
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('promises.record') }}</flux:button>
                    </form>
                    @endcan
                @endif
            </div>
        @endif

        @if ($obligation->subject_type === \App\Domain\Enums\SubjectType::Commitment && $obligation->commitmentSubject)
            <div class="mt-6 border-t border-zinc-200/80 pt-5 dark:border-zinc-700/80">
                <flux:heading size="lg">{{ __('promises.completion') }}</flux:heading>
                <blockquote class="mt-3 border-s-2 border-amber-400 ps-4 text-sm text-zinc-700 dark:text-zinc-200">{{ $obligation->commitmentSubject->done_criteria }}</blockquote>
                @if ($obligation->commitmentSubject->completed_at)
                    <flux:text class="mt-3">{{ __('promises.completed_on', ['date' => $obligation->commitmentSubject->completed_at->format('d M Y')]) }}{{ $obligation->commitmentSubject->completion_note ? ' · '.$obligation->commitmentSubject->completion_note : '' }}</flux:text>
                    @can('update', $record)
                    <form wire:submit="reopenCommitment" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                        <flux:input wire:model="reopenReason" :label="__('promises.why_reopen')" :placeholder="__('promises.optional_explanation')" required />
                        <flux:button type="submit" variant="ghost" wire:loading.attr="disabled">{{ __('promises.reopen') }}</flux:button>
                    </form>
                    @endcan
                @else
                    @can('update', $record)
                    <form wire:submit="completeCommitment" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                        <flux:input wire:model="completionNote" :label="__('promises.note')" :placeholder="__('promises.optional_completion_note')" />
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('promises.mark_complete') }}</flux:button>
                    </form>
                    @endcan
                @endif
            </div>
        @endif
    </section>

    @if ($chargeObligation instanceof \App\Models\Obligation)
        <section class="app-card space-y-5 rounded-2xl p-5 sm:p-7">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                <flux:heading size="lg">{{ __('promises.instalments') }}</flux:heading>
                <flux:text class="mt-1">{{ __('promises.schedule_against_balance', ['currency' => $chargeObligation->moneySubject->currency]) }}</flux:text>
                </div>
                @if ($chargeObligation->due_on)
                    <flux:text class="text-sm">{{ __('promises.final_due', ['date' => $chargeObligation->due_on->format('d M Y')]) }}@if ($hijriDueDates[$chargeObligation->getKey()] ?? null) · {{ $hijriDueDates[$chargeObligation->getKey()] }} {{ __('promises.hijri_suffix') }}@endif</flux:text>
                @endif
            </div>
            @if ($instalments->isNotEmpty())
                <div>
                    <div class="flex justify-between text-sm"><span>{{ __('promises.total_paid') }}</span><span>{{ \App\Domain\Money\Money::display($instalmentPaid, $chargeObligation->moneySubject->currency) }} / {{ \App\Domain\Money\Money::display($instalmentTotal, $chargeObligation->moneySubject->currency) }}</span></div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                        <div class="h-full rounded-full bg-emerald-600" style="width: {{ $instalmentTotal > 0 ? min(100, ($instalmentPaid / $instalmentTotal) * 100) : 0 }}%"></div>
                    </div>
                </div>
                <div class="space-y-2">
                    @foreach ($instalments as $instalment)
                        @php($instalmentStatus = $instalment->status())
                        @php($isNextInstalment = $instalment->paid_minor < $instalment->amount_minor && $instalments->first(fn ($item) => $item->paid_minor < $item->amount_minor)?->id === $instalment->id)
                        <div wire:key="instalment-{{ $instalment->id }}" class="rounded-xl border px-3 py-3 {{ $isNextInstalment ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30' : 'border-zinc-200 dark:border-zinc-700' }}">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="font-medium">#{{ $instalment->sequence }} · {{ $instalment->due_on->format('d M Y') }}{{ $isNextInstalment ? ' · '.__('promises.next') : '' }}</div>
                                <x-status-badge :tone="match ($instalmentStatus) { \App\Domain\Enums\InstalmentStatus::Settled => 'success', \App\Domain\Enums\InstalmentStatus::Overdue => 'danger', default => 'info' }" :label="__('promises.instalment_statuses.'.$instalmentStatus->value)" />
                            </div>
                            <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('promises.paid_of', ['paid' => \App\Domain\Money\Money::display($instalment->paid_minor, $instalment->currency), 'amount' => \App\Domain\Money\Money::display($instalment->amount_minor, $instalment->currency)]) }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
            @can('update', $record)
                @if ($instalments->isEmpty() && $chargeObligation->status === \App\Domain\Enums\ObligationStatus::Open)
                    <form wire:submit="generateInstalments" class="grid gap-3 border-t border-zinc-200/80 pt-5 sm:grid-cols-2 dark:border-zinc-700/80">
                        <flux:input wire:model="instalmentCount" type="number" min="1" max="600" :label="__('promises.number_of_instalments')" required />
                        <flux:select wire:model="instalmentFrequency" :label="__('promises.frequency')">
                            <flux:select.option value="weekly">{{ __('promises.weekly') }}</flux:select.option>
                            <flux:select.option value="monthly">{{ __('promises.monthly') }}</flux:select.option>
                        </flux:select>
                        <flux:input wire:model="instalmentFirstDueOn" type="date" :label="__('promises.first_due_date')" :description="__('promises.first_due_description')" />
                        <flux:input wire:model="instalmentPayDayOfMonth" type="number" min="1" max="28" :label="__('promises.pay_day_of_month')" :description="__('promises.pay_day_description')" />
                        @error('schedule')<flux:text class="text-red-700 sm:col-span-2">{{ $message }}</flux:text>@enderror
                        @error('count')<flux:text class="text-red-700 sm:col-span-2">{{ $message }}</flux:text>@enderror
                        @error('firstDueOn')<flux:text class="text-red-700 sm:col-span-2">{{ $message }}</flux:text>@enderror
                        <div class="sm:col-span-2 sm:flex sm:justify-end"><flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('promises.generate_schedule') }}</flux:button></div>
                    </form>
                @elseif ($canRegenerateInstalments)
                    <form wire:submit="regenerateInstalments" class="grid gap-3 border-t border-zinc-200/80 pt-5 sm:grid-cols-2 dark:border-zinc-700/80">
                        <flux:input wire:model="instalmentCount" type="number" min="1" max="600" :label="__('promises.number_of_instalments')" required />
                        <flux:select wire:model="instalmentFrequency" :label="__('promises.frequency')">
                            <flux:select.option value="weekly">{{ __('promises.weekly') }}</flux:select.option>
                            <flux:select.option value="monthly">{{ __('promises.monthly') }}</flux:select.option>
                        </flux:select>
                        <flux:input wire:model="instalmentFirstDueOn" type="date" :label="__('promises.first_due_date')" :description="__('promises.first_due_description')" />
                        <flux:input wire:model="instalmentPayDayOfMonth" type="number" min="1" max="28" :label="__('promises.pay_day_of_month')" :description="__('promises.pay_day_description')" />
                        @error('schedule')<flux:text class="text-red-700 sm:col-span-2">{{ $message }}</flux:text>@enderror
                        @error('count')<flux:text class="text-red-700 sm:col-span-2">{{ $message }}</flux:text>@enderror
                        @error('firstDueOn')<flux:text class="text-red-700 sm:col-span-2">{{ $message }}</flux:text>@enderror
                        <div class="sm:col-span-2 sm:flex sm:justify-end"><flux:button type="submit" variant="ghost" wire:loading.attr="disabled">{{ __('promises.regenerate_schedule') }}</flux:button></div>
                    </form>
                @endif
            @endcan
        </section>

        <section class="app-card space-y-6 rounded-2xl p-5 sm:p-7">
            <div>
                <flux:heading size="lg">{{ __('promises.charge_terms') }}</flux:heading>
                <flux:text class="mt-1">{{ __('promises.charge_terms_intro') }}</flux:text>
            </div>

            @if ($chargeTerms)
                <dl class="grid gap-3 rounded-xl bg-zinc-50 p-4 text-sm sm:grid-cols-3 dark:bg-zinc-900">
                    <div><dt class="text-zinc-500">{{ __('promises.mode') }}</dt><dd class="font-medium">{{ __('promises.charge_mode_values.'.$chargeTerms->mode) }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('promises.kind') }}</dt><dd class="font-medium">{{ $profile->interest_free_mode && $chargeTerms->kind === 'interest' ? __('charges.guard.interest_blocked') : __('promises.charge_kind_values.'.$chargeTerms->kind) }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('promises.rate') }}</dt><dd class="font-medium">{{ $chargeTermsGuardedAccrual ? ($chargeTerms->kind === 'interest' ? __('charges.guard.interest_blocked') : __('charges.guard.non_fixed_profit_blocked')) : ($chargeTerms->rate === null ? '—' : rtrim(rtrim((string) $chargeTerms->rate, '0'), '.')) }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('promises.basis_period') }}</dt><dd class="font-medium">{{ $chargeTermsGuardedAccrual ? ($chargeTerms->kind === 'interest' ? __('charges.guard.interest_blocked') : __('charges.guard.non_fixed_profit_blocked')) : ($chargeTerms->basis ? ($chargeTerms->basis === 'fixed_total' ? __('charges.fixed_total.label') : __('promises.charge_basis_values.'.$chargeTerms->basis)) : '—').' · '.($chargeTerms->period ? __('promises.charge_period_values.'.$chargeTerms->period) : '—') }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('promises.lifetime_cap') }}</dt><dd class="font-medium">{{ $chargeTerms->cap_minor === null ? __('app.none') : \App\Domain\Money\Money::display((int) $chargeTerms->cap_minor, (string) $chargeObligation->moneySubject->currency) }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('promises.allocation') }}</dt><dd class="font-medium">{{ __('promises.allocation_values.'.$chargeTerms->allocation_order) }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('promises.terms_acceptance') }}</dt><dd class="font-medium">{{ $chargeTermsAccepted ? __('promises.accepted') : __('promises.not_accepted') }}</dd></div>
                    @if ($chargeTerms->basis === 'fixed_total')
                        <div><dt class="text-zinc-500">{{ __('charges.fixed_total.label') }}</dt><dd class="font-medium">{{ $chargeTerms->fixed_total_agreed_upfront_at ? __('app.yes') : __('app.no') }}</dd></div>
                    @endif
                    @if ($chargeTerms->is_formal)
                        <div><dt class="text-zinc-500">{{ __('promises.formal_debt') }}</dt><dd class="font-medium">{{ __('app.yes') }}</dd></div>
                    @endif
                    @if ($chargeTermsGuardedAccrual || $chargeTermsGuardedPenalty)
                        <div class="sm:col-span-3 space-y-1"><dd class="font-medium text-amber-800 dark:text-amber-300">{{ __('promises.some_terms_blocked') }}</dd>@if ($chargeTermsGuardedPenalty)<dd class="font-medium text-amber-800 dark:text-amber-300">{{ __('charges.guard.percentage_penalty_blocked') }}</dd>@endif</div>
                    @endif
                </dl>
            @else
                <flux:text class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-900">{{ __('promises.no_charge_terms') }}</flux:text>
            @endif

            @can('update', $record)
                @if ($profile->interest_free_mode)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                        <div class="font-semibold">{{ __('charges.guard.label') }}</div>
                        <div class="mt-1">{{ __('charges.guard.disclaimer') }}</div>
                    </div>
                @endif

                <form wire:submit="saveChargeTerms" class="space-y-4 border-t border-zinc-200/80 pt-5 dark:border-zinc-700/80">
                    <input type="hidden" wire:model="chargeCurrency" />
                    <div class="grid gap-3 sm:grid-cols-3">
                        <flux:select wire:model.live="chargeMode" :label="__('promises.mode')">
                            <option value="none">{{ __('promises.charge_mode_values.none') }}</option>
                            <option value="manual">{{ __('promises.charge_mode_values.manual') }}</option>
                            <option value="auto_propose">{{ __('promises.charge_mode_values.auto_propose') }}</option>
                            <option value="auto_apply">{{ __('promises.charge_mode_values.auto_apply') }}</option>
                        </flux:select>
                        <flux:select wire:model.live="chargeKind" :label="__('promises.kind')">
                            <option value="none">{{ __('promises.charge_kind_values.none') }}</option>
                            @unless ($profile->interest_free_mode)
                                <option value="interest">{{ __('promises.charge_kind_values.interest') }}</option>
                            @endunless
                            <option value="profit">{{ __('promises.charge_kind_values.profit') }}</option>
                        </flux:select>
                        <flux:select wire:model.live="chargeBasis" :label="__('promises.basis_period')">
                            <option value="">{{ __('promises.choose_basis') }}</option>
                            @if (! $profile->interest_free_mode || $chargeKind !== 'profit')
                                <option value="flat">{{ __('promises.flat') }}</option>
                                <option value="reducing_simple">{{ __('promises.reducing_simple') }}</option>
                            @endif
                            <option value="fixed_total">{{ __('charges.fixed_total.label') }}</option>
                        </flux:select>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @if ($chargeKind !== 'none' && $chargeBasis !== 'fixed_total')
                            <flux:input wire:model.live="chargeRate" :label="__('promises.rate_per_period')" placeholder="0.015" :description="__('promises.rate_example')" />
                        @endif
                        <flux:select wire:model.live="chargePeriod" :label="__('promises.period')">
                            <option value="daily">{{ __('promises.daily') }}</option>
                            <option value="monthly">{{ __('promises.monthly') }}</option>
                            <option value="yearly">{{ __('promises.yearly') }}</option>
                        </flux:select>
                        <flux:input wire:model="chargeGraceDays" type="number" min="0" :label="__('promises.grace_days')" />
                        <flux:input wire:model.live="chargeCap" :label="__('promises.lifetime_cap')" :placeholder="__('promises.optional')" inputmode="decimal" :description="__('promises.amount_in', ['currency' => $chargeCurrency])" />
                        @if ($chargeBasis === 'fixed_total')
                            <flux:input wire:model.live="chargeTotalPayable" :label="__('charges.fixed_total.total_payable')" placeholder="0.00" inputmode="decimal" :description="__('promises.total_payable_description', ['currency' => $chargeCurrency])" />
                        @endif
                    </div>
                    @if ($chargeBasis === 'fixed_total')
                        <label class="flex items-center gap-3 text-sm">
                            <input wire:model="fixedTotalAgreedUpfront" type="checkbox" class="rounded border-zinc-300 text-emerald-600" />
                            {{ __('charges.fixed_total.agreed_upfront') }}
                        </label>
                        @error('fixedTotalAgreedUpfront')<flux:text class="text-red-700">{{ $message }}</flux:text>@enderror
                    @endif
                    <div class="grid gap-3 sm:grid-cols-3">
                        <flux:select wire:model.live="chargePenaltyKind" :label="__('promises.penalty')">
                            <option value="none">{{ __('promises.penalty_values.none') }}</option>
                            <option value="fixed">{{ __('promises.penalty_values.fixed') }}</option>
                            @unless ($profile->interest_free_mode)
                                <option value="percent_of_overdue">{{ __('promises.penalty_values.percent_of_overdue') }}</option>
                            @endunless
                        </flux:select>
                        @if ($chargePenaltyKind !== 'none')
                            <flux:input wire:model="chargePenaltyValue" :label="__('promises.penalty_value')" :placeholder="__('promises.amount_or_bps')" />
                        @endif
                        <flux:select wire:model="chargeAllocationOrder" :label="__('promises.payment_allocation')">
                            <option value="charges_first">{{ __('promises.allocation_values.charges_first') }}</option>
                            <option value="principal_first">{{ __('promises.allocation_values.principal_first') }}</option>
                        </flux:select>
                    </div>
                    <div class="flex flex-wrap gap-5 text-sm">
                        <label class="flex items-center gap-3"><input wire:model="chargeIsFormal" type="checkbox" class="rounded border-zinc-300 text-emerald-600" /> {{ __('promises.formal_debt_description') }}</label>
                        <label class="flex items-center gap-3"><input wire:model="chargeAutoApplyOnAcceptance" type="checkbox" class="rounded border-zinc-300 text-emerald-600" /> {{ __('promises.auto_apply_after_acceptance') }}</label>
                    </div>
                    @foreach (['chargeTerms', 'kind', 'basis', 'period', 'rate', 'cap', 'totalPayable', 'penaltyKind', 'penaltyValue', 'currency'] as $chargeError)
                        @error($chargeError)<flux:text class="text-red-700">{{ $message }}</flux:text>@enderror
                    @endforeach
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <flux:text class="text-xs text-zinc-500">{{ __('promises.promise_currency', ['currency' => $chargeCurrency]) }}</flux:text>
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('promises.save_charge_terms') }}</flux:button>
                    </div>
                </form>
            @endcan

            @if ($chargePreview)
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="font-semibold">{{ __('promises.estimated_next_periods') }}</div>
                    <div class="mt-1 text-xs text-zinc-500">{{ __('promises.estimate_assumptions') }}</div>
                    <div class="mt-3 space-y-2">
                        @foreach ($chargePreview as $preview)
                            <div wire:key="preview-{{ $loop->index }}" class="flex flex-wrap justify-between gap-2 text-sm">
                                <span>{{ \Illuminate\Support\Carbon::parse($preview['period_start'])->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($preview['period_end'])->format('d M Y') }}</span>
                                <span class="font-medium">{{ \App\Domain\Money\Money::display($preview['amount_minor'], $preview['currency']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @can('update', $record)
                @if (in_array($chargeTerms?->mode, ['auto_propose', 'auto_apply'], true))
                    <flux:button wire:click="generateChargesNow" variant="ghost" wire:loading.attr="disabled">{{ __('promises.generate_now') }}</flux:button>
                @endif
            @endcan

            @if ($proposedCharges->isNotEmpty())
                <div class="border-t border-zinc-200/80 pt-5 dark:border-zinc-700/80">
                    <flux:heading size="sm">{{ __('promises.proposed_charges') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('promises.proposed_no_balance') }}</flux:text>
                    <div class="mt-3 space-y-2">
                        @foreach ($proposedCharges as $proposedCharge)
                            <div wire:key="proposed-charge-{{ $proposedCharge->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 px-3 py-3 text-sm dark:border-zinc-700">
                                <div>
                                    <div class="font-medium">{{ __('promises.money_entries.'.$proposedCharge->entry->value) }} · {{ \App\Domain\Money\Money::display($proposedCharge->amount_minor, $proposedCharge->currency) }}</div>
                                    <div class="text-xs text-zinc-500">{{ __('promises.proposed_not_included', ['date' => $proposedCharge->occurred_on->format('d M Y')]) }}</div>
                                </div>
                                @can('update', $record)
                                    <div class="flex gap-3">
                                        <button type="button" wire:click="confirmProposedCharge('{{ $proposedCharge->id }}')" wire:loading.attr="disabled" class="font-semibold text-emerald-700 hover:underline">{{ __('promises.confirm') }}</button>
                                        <button type="button" wire:click="dismissProposedCharge('{{ $proposedCharge->id }}')" wire:confirm="{{ __('promises.dismiss_charge_confirm') }}" wire:loading.attr="disabled" class="font-semibold text-red-700 hover:underline">{{ __('promises.dismiss') }}</button>
                                    </div>
                                @endcan
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        @can('update', $record)
            <section class="app-card space-y-4 rounded-2xl p-5 sm:p-7">
                <div>
                    <flux:heading size="lg">{{ __('promises.add_charge') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('promises.record_fixed_charge', ['currency' => $chargeCurrency]) }}</flux:text>
                </div>
                <form wire:submit="recordManualCharge" class="grid gap-3 sm:grid-cols-4 sm:items-end">
                    <flux:select wire:model="manualChargeEntry" :label="__('promises.entry')">
                        @unless ($profile->interest_free_mode)
                            <option value="interest_charge">{{ __('promises.interest_charge') }}</option>
                        @endunless
                        <option value="profit_charge">{{ __('promises.profit_charge') }}</option>
                        <option value="fee">{{ __('promises.fee') }}</option>
                        <option value="penalty">{{ __('promises.penalty') }}</option>
                    </flux:select>
                    <flux:input wire:model="manualChargeAmount" :label="__('promises.amount')" inputmode="decimal" placeholder="0.00" />
                    <flux:input wire:model="manualChargeOccurredOn" type="date" :label="__('promises.date')" />
                    <flux:input wire:model="manualChargeNote" :label="__('promises.note')" :placeholder="__('promises.optional_note')" />
                    <div class="sm:col-span-4 sm:flex sm:justify-end"><flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('promises.record_charge') }}</flux:button></div>
                    @foreach (['manualChargeEntry', 'manualChargeAmount', 'manualChargeOccurredOn', 'manualChargeNote', 'entry'] as $chargeError)
                        @error($chargeError)<flux:text class="text-red-700 sm:col-span-4">{{ $message }}</flux:text>@enderror
                    @endforeach
                </form>
            </section>
        @endcan
    @endif

    <section class="app-card rounded-2xl p-5 sm:p-7">
        <div class="flex items-start justify-between gap-4">
            <div>
            <flux:heading size="lg">{{ __('promises.promise_parts') }}</flux:heading>
            <flux:text class="mt-1">{{ __('promises.parts_intro') }}</flux:text>
            </div>
            <x-status-badge :tone="match ($recordStatus) { \App\Domain\Enums\ObligationStatus::Settled => 'success', \App\Domain\Enums\ObligationStatus::Disputed => 'danger', \App\Domain\Enums\ObligationStatus::Paused => 'warning', default => 'info' }" :label="__('promises.statuses.'.$recordStatus->value)" />
        </div>
        <div class="mt-5 divide-y divide-zinc-200/80 rounded-xl border border-zinc-200/80 dark:divide-zinc-700/80 dark:border-zinc-700/80">
            @foreach ($obligations as $part)
                <div wire:key="part-{{ $part->id }}" class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="font-medium">{{ __('promises.subjects.'.$part->subject_type->value) }} · {{ __('promises.directions.'.$part->direction->value) }}</div>
                        <div class="text-sm text-zinc-500">
                            @if ($part->subject_type === \App\Domain\Enums\SubjectType::Money && $part->moneySubject)
                                {{ $part->moneySubject->currency }} {{ __('promises.money_subject') }}
                            @elseif ($part->subject_type === \App\Domain\Enums\SubjectType::Quantity && $part->quantitySubject)
                                {{ $part->quantitySubject->name }} · {{ $part->quantitySubject->unit }}
                            @elseif ($part->commitmentSubject)
                                {{ $part->commitmentSubject->done_criteria }}
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-status-badge :tone="match ($part->status) { \App\Domain\Enums\ObligationStatus::Settled => 'success', \App\Domain\Enums\ObligationStatus::Disputed => 'danger', \App\Domain\Enums\ObligationStatus::Paused => 'warning', default => 'info' }" :label="__('promises.statuses.'.$part->status->value)" />
                        @if ($part->status === \App\Domain\Enums\ObligationStatus::Settled && $part->closure_reason)
                            <span class="text-xs text-zinc-500">{{ __('promises.closure_reasons.'.$part->closure_reason->value) }}</span>
                        @endif
                        @can('update', $record)
                        @if ($part->status === \App\Domain\Enums\ObligationStatus::Open)
                            <button type="button" wire:click="pauseObligation('{{ $part->id }}')" wire:loading.attr="disabled" class="text-xs text-amber-700 hover:underline">{{ __('promises.pause') }}</button>
                            <button type="button" wire:click="disputeObligation('{{ $part->id }}')" wire:confirm="{{ __('promises.dispute_confirm') }}" wire:loading.attr="disabled" class="text-xs text-red-700 hover:underline">{{ __('promises.dispute') }}</button>
                        @elseif ($part->status === \App\Domain\Enums\ObligationStatus::Paused)
                            <button type="button" wire:click="resumeObligation('{{ $part->id }}')" wire:loading.attr="disabled" class="text-xs text-emerald-700 hover:underline">{{ __('promises.resume') }}</button>
                            <button type="button" wire:click="disputeObligation('{{ $part->id }}')" wire:confirm="{{ __('promises.dispute_confirm') }}" wire:loading.attr="disabled" class="text-xs text-red-700 hover:underline">{{ __('promises.dispute') }}</button>
                        @elseif ($part->status === \App\Domain\Enums\ObligationStatus::Disputed)
                            <button type="button" wire:click="resolveDispute('{{ $part->id }}')" wire:loading.attr="disabled" class="text-xs text-emerald-700 hover:underline">{{ __('promises.resolve_dispute') }}</button>
                        @endif
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
        @can('update', $record)
            <form wire:submit="addObligation" class="mt-6 space-y-4 border-t border-zinc-200/80 pt-5 dark:border-zinc-700/80">
                <flux:heading size="sm">{{ __('promises.add_another_part') }}</flux:heading>
                <div class="grid gap-3 sm:grid-cols-3">
                    <flux:select wire:model="partDirection" :label="__('promises.direction')">
                        <option value="{{ \App\Domain\Enums\Direction::Payable->value }}">{{ __('promises.directions.payable') }}</option>
                        <option value="{{ \App\Domain\Enums\Direction::Receivable->value }}">{{ __('promises.directions.receivable') }}</option>
                    </flux:select>
                    <flux:select wire:model.live="partSubjectType" :label="__('promises.subject')">
                        <option value="{{ \App\Domain\Enums\SubjectType::Money->value }}">{{ __('promises.subjects.money') }}</option>
                        <option value="{{ \App\Domain\Enums\SubjectType::Quantity->value }}">{{ __('promises.subjects.quantity') }}</option>
                        <option value="{{ \App\Domain\Enums\SubjectType::Commitment->value }}">{{ __('promises.subjects.commitment') }}</option>
                    </flux:select>
                    <flux:input wire:model="partDueOn" type="date" :label="__('promises.due_date')" />
                </div>
                @if ($partSubjectType === \App\Domain\Enums\SubjectType::Money->value)
                    <flux:input wire:model="partAmount" :label="__('promises.amount')" inputmode="decimal" placeholder="0.00" />
                @elseif ($partSubjectType === \App\Domain\Enums\SubjectType::Quantity->value)
                    <div class="grid gap-3 sm:grid-cols-3">
                        <flux:input wire:model="partQuantityName" :label="__('promises.what_is_it')" />
                        <flux:input wire:model="partQuantityTotal" :label="__('promises.how_many')" inputmode="decimal" />
                        <flux:input wire:model="partQuantityUnit" :label="__('promises.unit')" />
                    </div>
                    <label class="flex items-center gap-3 text-sm"><input wire:model="partIsFractionable" type="checkbox" class="rounded border-zinc-300 text-emerald-600" /> {{ __('promises.fractions_allowed') }}</label>
                @else
                    <flux:textarea wire:model="partDoneCriteria" :label="__('promises.completion_meaning')" rows="2" />
                @endif
                <flux:textarea wire:model="partNote" :label="__('promises.part_note')" rows="2" />
                <div class="flex justify-end"><flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('promises.add_part') }}</flux:button></div>
            </form>
        @endcan
    </section>

    <section class="app-card rounded-2xl p-5 sm:p-7">
        <div class="flex items-start justify-between gap-4">
            <div><flux:heading size="lg">{{ __('promises.follow_up') }}</flux:heading><flux:text class="mt-1">{{ __('promises.reminder_description') }}</flux:text></div>
            @if ($obligation->due_on)<flux:text class="text-sm">{{ __('promises.final_due', ['date' => $obligation->due_on->format('d M Y')]) }}@if ($hijriDueDates[$obligation->getKey()] ?? null) · {{ $hijriDueDates[$obligation->getKey()] }} {{ __('promises.hijri_suffix') }}@endif</flux:text>@endif
        </div>
        @can('update', $record)
            <form wire:submit="scheduleReminder" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end"><flux:input wire:model="reminderDate" type="date" :label="__('promises.remind_me_on')" required /><flux:select wire:model="reminderChannel" :label="__('promises.channel')"><flux:select.option value="database">{{ __('promises.in_app') }}</flux:select.option><flux:select.option value="mail">{{ __('promises.email') }}</flux:select.option></flux:select><flux:button type="submit" variant="ghost" wire:loading.attr="disabled">{{ __('promises.remind_me') }}</flux:button></form>
        @endcan
        @if ($obligation->reminders->isNotEmpty())
            <div class="mt-5 space-y-2">
                @foreach ($obligation->reminders as $reminder)
                    <div wire:key="reminder-{{ $reminder->id }}" class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-900">
                        <span>{{ __('promises.reminder_statuses.'.$reminder->status->value) }} · {{ $reminder->remind_on->format('d M Y') }}</span>
                        @can('update', $record)
                            @if ($reminder->status !== \App\Domain\Enums\ReminderStatus::Dismissed && $reminder->status !== \App\Domain\Enums\ReminderStatus::Sent)
                                <div class="flex gap-2">
                                    <button type="button" wire:click="snoozeReminder('{{ $reminder->id }}')" wire:loading.attr="disabled" class="text-zinc-500 hover:underline">{{ __('app.snooze') }}</button>
                                    <button type="button" wire:click="dismissReminder('{{ $reminder->id }}')" wire:confirm="{{ __('promises.dismiss_reminder_confirm') }}" wire:loading.attr="disabled" class="text-red-700 hover:underline">{{ __('promises.dismiss') }}</button>
                                </div>
                            @endif
                        @endcan
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="app-card overflow-hidden rounded-2xl">
        <div class="border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80">
            <flux:heading size="lg">{{ __('promises.history') }}</flux:heading>
            <flux:text class="mt-1 text-sm">{{ __('promises.history_intro') }}</flux:text>
        </div>
        <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
            @if ($obligation->subject_type === \App\Domain\Enums\SubjectType::Money)
                @forelse ($movements as $movement)
                <div id="history" wire:key="movement-{{ $movement->id }}" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="font-medium">{{ __('promises.money_entries.'.$movement->entry->value) }}</div>
                        <div class="mt-1 text-sm text-zinc-500">{{ $movement->occurred_on->format('d M Y') }}{{ $movement->note ? ' · '.$movement->note : '' }}</div>
                        @if ($movement->status === \App\Domain\Enums\MovementStatus::Proposed)
                            <div class="mt-1 text-xs text-amber-700">{{ __('promises.proposed_not_balance') }}</div>
                        @endif
                        @if ($movement->status === \App\Domain\Enums\MovementStatus::Voided)
                            <div class="mt-1 text-xs text-red-700">{{ __('promises.voided_reason', ['reason' => $movement->void_reason]) }}</div>
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
                                <span class="text-xs font-semibold text-emerald-700">{{ __('promises.selected_for_evidence') }}</span>
                            @else
                                <button type="button" wire:click="prepareMovementEvidence('{{ $movement->id }}')" x-on:click="document.getElementById('evidence')?.scrollIntoView({ behavior: 'smooth', block: 'start' })" wire:loading.attr="disabled" class="text-xs font-semibold text-emerald-700 hover:underline">{{ __('promises.attach') }}</button>
                            @endif
                        @endcan
                        @if ($movement->status === \App\Domain\Enums\MovementStatus::Confirmed)
                            @can('update', $record)
                                <button type="button" wire:click="voidMovement('{{ $movement->id }}')" wire:confirm="{{ __('promises.void_confirm') }}" wire:loading.attr="disabled" class="text-xs font-semibold text-red-700 hover:underline">{{ __('promises.void') }}</button>
                            @endcan
                        @endif
                    </div>
                </div>
                @empty
                    <div class="px-5 py-8 text-sm text-zinc-500">{{ __('promises.no_movements') }}</div>
                @endforelse
            @elseif ($obligation->subject_type === \App\Domain\Enums\SubjectType::Quantity)
                @forelse ($returns as $return)
                    <div wire:key="quantity-return-{{ $return->id }}" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="font-medium">{{ __('promises.returned_quantity', ['quantity' => $return->quantity, 'unit' => $obligation->quantitySubject?->unit]) }}</div>
                            <div class="mt-1 text-sm text-zinc-500">{{ $return->occurred_on->format('d M Y') }}{{ $return->note ? ' · '.$return->note : '' }}</div>
                            @if ($return->status === \App\Domain\Enums\MovementStatus::Voided)
                                <div class="mt-1 text-xs text-red-700">{{ __('promises.voided_reason', ['reason' => $return->void_reason]) }}</div>
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
                                <span class="text-xs font-semibold text-emerald-700">{{ __('promises.selected_for_evidence') }}</span>
                            @else
                                <button type="button" wire:click="prepareReturnEvidence('{{ $return->id }}')" x-on:click="document.getElementById('evidence')?.scrollIntoView({ behavior: 'smooth', block: 'start' })" wire:loading.attr="disabled" class="text-xs font-semibold text-emerald-700 hover:underline">{{ __('promises.attach') }}</button>
                            @endif
                        @endcan
                    </div>
                @empty
                    <div class="px-5 py-8 text-sm text-zinc-500">{{ __('promises.no_returns') }}</div>
                @endforelse
            @else
                <div class="px-5 py-8 text-sm text-zinc-500">{{ __('promises.commitment_history') }}</div>
            @endif
        </div>
    </section>

    <section class="app-card rounded-2xl p-5 sm:p-7">
        <div>
            <flux:heading size="lg">{{ __('promises.activity') }}</flux:heading>
            <flux:text class="mt-1">{{ __('promises.activity_intro') }}</flux:text>
        </div>
        <div class="mt-5 space-y-4">
            @forelse ($activities as $activity)
                <div wire:key="activity-{{ $activity->id }}" class="flex gap-3 text-sm">
                    <div class="mt-1 size-2 shrink-0 rounded-full bg-zinc-300 dark:bg-zinc-600"></div>
                    <div>
                        <div class="font-medium">{{ \App\Domain\ActivityLabels::label($activity->action) }}</div>
                        <div class="mt-1 text-zinc-500">{{ $activity->actor?->name ?? __('promises.system') }} · {{ $activity->occurred_at->format('d M Y, H:i') }}</div>
                    </div>
                </div>
            @empty
                <flux:text class="text-sm">{{ __('promises.no_activity') }}</flux:text>
            @endforelse
        </div>
        <flux:text class="mt-5 text-xs text-zinc-500">{{ __('promises.full_history_export') }}</flux:text>
    </section>

    @can('delete', $record)
        @if ($canDeleteRecord)
            <section class="app-card rounded-2xl border border-red-200 p-5 sm:p-7 dark:border-red-900/70">
                <flux:heading size="lg">{{ __('promises.danger_zone') }}</flux:heading>
                <flux:text class="mt-1">{{ __('promises.delete_empty_only') }}</flux:text>
                <div class="mt-5 flex flex-wrap gap-3">
                    <flux:button wire:click="restart" wire:confirm="{{ __('promises.restart_confirm') }}" variant="ghost" wire:loading.attr="disabled">{{ __('promises.restart_promise') }}</flux:button>
                    <flux:button wire:click="delete" wire:confirm="{{ __('promises.delete_confirm') }}" variant="danger" wire:loading.attr="disabled">{{ __('promises.delete_promise') }}</flux:button>
                </div>
            </section>
        @else
            <section class="app-card rounded-2xl border border-zinc-200 p-5 sm:p-7 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('promises.deletion_unavailable') }}</flux:heading>
                <flux:text class="mt-1">{{ __('promises.delete_requirements') }}</flux:text>
            </section>
        @endif
    @endcan
</div>
