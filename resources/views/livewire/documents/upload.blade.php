<section class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
        <flux:heading size="lg">Add evidence</flux:heading>
        <flux:text class="mt-1 text-sm">Evidence can be a private file, an external reference, or a written note. Add the kind that best preserves what happened.</flux:text>
    </div>
    <form wire:submit="save" class="space-y-5 px-5 py-5">
        @if ($hasPresetTarget)
            <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900/70 dark:bg-sky-950/30 dark:text-sky-100">
                <div class="font-medium">Evidence for this {{ $transaction !== null ? 'movement' : 'fulfillment update' }}</div>
                <div class="mt-1 text-sky-800/80 dark:text-sky-200/80">
                    {{ $transaction !== null ? ucfirst(str_replace('_', ' ', $transaction->entry_type)).' · '.$transaction->occurred_on?->format('d M Y') : ucfirst(str_replace('_', ' ', $event?->event_type ?? 'update')).' · '.$event?->occurred_on?->format('d M Y') }}
                </div>
            </div>
        @else
            <flux:select wire:model.live="targetType" label="What does this support?">
                <flux:select.option value="obligation">The whole record</flux:select.option>
                @if ($obligation->obligation_kind === 'money')
                    <flux:select.option value="transaction">A money movement</flux:select.option>
                @else
                    <flux:select.option value="event">A fulfillment update</flux:select.option>
                @endif
            </flux:select>
            @if ($targetType === 'transaction')
                <flux:select wire:model="transactionId" label="Money movement">
                    <flux:select.option value="">Choose a movement</flux:select.option>
                    @foreach ($transactions as $transaction)
                        <flux:select.option :value="$transaction->id">{{ ucfirst(str_replace('_', ' ', $transaction->entry_type)) }} · {{ $transaction->occurred_on?->format('d M Y') ?? 'No date' }} · {{ \App\Domain\Money\MoneyAmount::format($transaction->amount, $transaction->currency) }}</flux:select.option>
                    @endforeach
                </flux:select>
            @elseif ($targetType === 'event')
                <flux:select wire:model="eventId" label="Fulfillment update">
                    <flux:select.option value="">Choose an update</flux:select.option>
                    @foreach ($events as $event)
                        <flux:select.option :value="$event->id">{{ ucfirst(str_replace('_', ' ', $event->event_type)) }} · {{ $event->occurred_on?->format('d M Y') ?? 'No date' }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        @endif

        <flux:select wire:model.live="evidenceType" label="Evidence form">
            <flux:select.option value="file">Uploaded file — document, image, audio, or video</flux:select.option>
            <flux:select.option value="link">External link — message, post, cloud file, or reference</flux:select.option>
            <flux:select.option value="note">Written note — statement, observation, or attestation</flux:select.option>
        </flux:select>

        @if ($evidenceType !== 'file')
            <div wire:key="evidence-title-non-file">
                <flux:input wire:model="title" label="Evidence title" placeholder="For example: Borrowing agreement confirmed in chat" required />
            </div>
        @else
            <div wire:key="evidence-title-file">
                <flux:input wire:model="title" label="Title" placeholder="Optional — the file name will be used if left blank" />
            </div>
        @endif

        @if ($evidenceType === 'file')
            <div wire:key="evidence-form-file" class="space-y-2">
                <flux:input wire:model="file" type="file" label="Upload file" required />
                <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">Any file up to 50 MB is accepted except executable or active-content files. ZIP and other archives are allowed. Files remain private and are never opened by the server.</p>
                <div wire:loading wire:target="file" class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-800 dark:border-sky-900/70 dark:bg-sky-950/30 dark:text-sky-200">
                    Uploading file securely…
                </div>
                @if ($file)
                    <div wire:loading.remove wire:target="file" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/30 dark:text-emerald-200">
                        Ready to save: <span class="font-medium">{{ $file->getClientOriginalName() }}</span>
                    </div>
                @endif
            </div>
        @elseif ($evidenceType === 'link')
            <div wire:key="evidence-form-link">
                <flux:input wire:model="externalUrl" type="url" label="Source link" placeholder="https://..." required />
            </div>
        @else
            <div wire:key="evidence-form-note">
                <flux:textarea wire:model="content" label="Evidence note" placeholder="Record what was seen, said, agreed, delivered, or witnessed." rows="5" required />
            </div>
        @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <flux:input wire:model="source" label="Source or people involved" placeholder="Optional" />
            <flux:input wire:model="capturedOn" type="date" label="Date captured" />
        </div>

        <flux:select wire:model="category" label="Evidence category">
            <flux:select.option value="agreement">Agreement or terms</flux:select.option>
            <flux:select.option value="receipt">Receipt or payment proof</flux:select.option>
            <flux:select.option value="statement">Statement or account record</flux:select.option>
            <flux:select.option value="message">Message or conversation</flux:select.option>
            <flux:select.option value="photo">Photo or condition record</flux:select.option>
            <flux:select.option value="audio">Audio record</flux:select.option>
            <flux:select.option value="video">Video record</flux:select.option>
            <flux:select.option value="pawn_ticket">Pawn ticket or redemption slip</flux:select.option>
            <flux:select.option value="valuation">Valuation or appraisal</flux:select.option>
            <flux:select.option value="identity">Identity or ownership proof</flux:select.option>
            <flux:select.option value="witness_statement">Witness statement</flux:select.option>
            <flux:select.option value="delivery_proof">Delivery or handover proof</flux:select.option>
            <flux:select.option value="other">Other</flux:select.option>
        </flux:select>

        <flux:select wire:model="verificationStatus" label="Verification">
            <flux:select.option value="needs_review">Needs review</flux:select.option>
            <flux:select.option value="verified">Verified</flux:select.option>
        </flux:select>
        <flux:button type="submit" variant="primary" class="w-full">Save evidence</flux:button>
    </form>
</section>
