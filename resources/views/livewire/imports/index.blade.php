<div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-7">
    <div class="flex items-start gap-4"><span class="app-page-icon hidden shrink-0 sm:inline-flex"><flux:icon name="arrow-down-tray" class="size-6" /></span><div><div class="app-eyebrow">Review before recording</div><flux:heading size="xl" class="mt-2 text-3xl tracking-tight">Bank imports</flux:heading><flux:text class="mt-2 leading-6">Bring in a CSV statement, review the rows, then decide what becomes part of your records.</flux:text></div></div>
    @if (session('import-created'))
        <div class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">{{ session('import-created') }}</div>
    @endif
    @if (session('import-row-recorded'))
        <div class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">{{ session('import-row-recorded') }}</div>
    @endif
    @if (session('import-row-unmatched'))
        <div class="rounded-lg bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:bg-sky-950/30 dark:text-sky-200">{{ session('import-row-unmatched') }}</div>
    @endif
    <div class="grid gap-7 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700"><flux:heading size="lg">Imported statements</flux:heading></div>
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($imports as $import)
                    <div class="px-5 py-5">
                        @php($importStatusTone = match ($import->status) { 'completed', 'complete' => 'success', 'processing', 'uploaded' => 'info', 'failed', 'rejected' => 'danger', default => 'neutral' })
                        <div class="flex flex-wrap items-start justify-between gap-3"><div><div class="font-medium">{{ $import->original_filename }}</div><div class="mt-1 text-sm text-zinc-500">{{ $import->row_count }} rows · {{ $import->matched_count }} recorded · {{ $import->currency }} · {{ $import->created_at->format('d M Y, H:i') }}</div></div><x-status-badge :tone="$importStatusTone" :label="ucfirst($import->status)" /></div>
                        <div class="mt-4 overflow-x-auto"><table class="w-full min-w-[680px] text-left text-sm"><thead class="text-xs uppercase tracking-wide text-zinc-500"><tr><th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Description</th><th class="py-2 pr-3">Amount</th><th class="py-2 pr-3">Match</th><th class="py-2">Action</th></tr></thead><tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($import->rows as $row)
                                <tr><td class="py-3 pr-3">{{ $row->occurred_on?->format('d M Y') ?? '—' }}</td><td class="max-w-[220px] truncate py-3 pr-3">{{ $row->description ?? '—' }}</td><td class="py-3 pr-3 font-medium">{{ \App\Domain\Money\MoneyAmount::format(abs((int) $row->amount), $row->currency) }}<div class="mt-1"><x-direction-badge :direction="$row->suggested_direction" :label="ucfirst($row->suggested_direction)" /></div></td><td class="py-3 pr-3"><select wire:change="matchRow('{{ $row->id }}', $event.target.value)" @disabled($row->status === 'recorded') class="rounded-md border-zinc-300 bg-white text-sm dark:border-zinc-300 dark:bg-zinc-900"><option value="">{{ $row->obligation_id ? 'Clear match' : 'Choose money obligation' }}</option>@foreach ($recordOptions as $record)@foreach ($record->obligations as $obligation)<option value="{{ $obligation->id }}" @selected($row->obligation_id === $obligation->id)>{{ \Illuminate\Support\Str::limit($record->title.' · '.$obligation->title, 40) }}</option>@endforeach @endforeach</select></td><td class="py-3">@if ($row->status === 'recorded')<x-status-badge tone="success" label="Recorded · linked" />@elseif ($row->obligation_id)<flux:button size="sm" variant="ghost" wire:click="recordRow('{{ $row->id }}')">Record</flux:button>@else<x-status-badge tone="neutral" label="Review" />@endif</td></tr>
                            @endforeach
                        </tbody></table></div>
                    </div>
                @empty
                    <div class="px-5 py-12 text-center"><flux:heading size="lg">No imports yet</flux:heading><flux:text class="mt-2">A CSV statement is kept as a review queue until you match and record rows.</flux:text></div>
                @endforelse
            </div>
        </section>
        <section class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700"><flux:heading size="lg">Import CSV</flux:heading><flux:text class="mt-1 text-sm">Required: amount. Optional: date, description, reference. Files stay private.</flux:text></div>
            <form wire:submit="runImport" class="space-y-5 px-5 py-5">
                <flux:select wire:model.live="profileId" label="Profile">@foreach ($profiles as $profile)<flux:select.option value="{{ $profile->id }}">{{ $profile->name }}</flux:select.option>@endforeach</flux:select>
                <div class="space-y-2">
                    <flux:input wire:model="file" type="file" label="Upload CSV statement" accept=".csv,.txt" required />
                    <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">CSV or TXT up to 10 MB. Executable and active-content files are rejected. Required column: amount. The statement stays private until you review each row.</p>
                    <div wire:loading wire:target="file" class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-800 dark:border-sky-900/70 dark:bg-sky-950/30 dark:text-sky-200">
                        Uploading statement securely…
                    </div>
                    @if ($file)
                        <div wire:loading.remove wire:target="file" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/30 dark:text-emerald-200">
                            Ready to review: <span class="font-medium">{{ $file->getClientOriginalName() }}</span>
                        </div>
                    @endif
                </div>
                <flux:select wire:model="currency" label="Statement currency" required>@foreach (\App\Domain\Money\Currency::options() as $code => $label)<flux:select.option value="{{ $code }}">{{ $code }} — {{ $label }}</flux:select.option>@endforeach</flux:select>
                <flux:button type="submit" variant="primary" class="w-full">Import for review</flux:button>
            </form>
        </section>
    </div>
    @if ($canManageImports)
        <livewire:collections.accounts :profile="$profile" :key="'collection-accounts-'.$profile->id" />
    @endif
</div>
