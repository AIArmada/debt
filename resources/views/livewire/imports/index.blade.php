<div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6">
    <div>
        <a href="{{ route('promises.index', $profile) }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">← Back to promises</a>
        <div class="app-eyebrow mt-6">{{ $profile->name }}</div>
        <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">Import proposals</flux:heading>
        <flux:text class="mt-2 leading-6">Rows are suggestions only. A confirmed row uses the normal movement action.</flux:text>
    </div>

    @can('createObligation', $profile)
        <section class="app-card rounded-2xl p-5 sm:p-7">
            <form wire:submit="upload" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1"><label class="block text-sm font-medium">CSV statement</label><input wire:model="file" type="file" accept=".csv,.txt" class="mt-2 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm" /></div>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Build proposals</flux:button>
            </form>
            @error('file')<flux:text class="mt-2 text-red-700">{{ $message }}</flux:text>@enderror
        </section>
    @endcan

    @forelse ($batches as $batch)
        <section class="app-card overflow-hidden rounded-2xl">
            <div class="border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80"><flux:heading size="lg">{{ $batch->filename }}</flux:heading><flux:text class="mt-1 text-sm">{{ $batch->row_count }} rows</flux:text></div>
            <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
                @foreach ($batch->rows as $row)
                    <div wire:key="import-row-{{ $row->id }}" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div><div class="font-medium">{{ $row->amount_minor }} {{ $row->currency }}</div><div class="mt-1 text-sm text-zinc-500">{{ $row->occurred_on->format('d M Y') }} · {{ $row->description ?? 'No description' }}</div><div class="mt-1 text-xs text-zinc-500">{{ $row->suggestedObligation?->title ? 'Suggested: '.$row->suggestedObligation->title : 'No match yet' }}</div></div>
                        <div class="flex items-center gap-3 text-sm"><span class="text-zinc-500">{{ ucfirst($row->status->value) }}</span>@can('createObligation', $profile) @if ($row->status === \App\Domain\Enums\ImportRowStatus::Pending && $row->suggested_obligation_id)<button type="button" wire:click="confirm('{{ $row->id }}')" wire:confirm="Confirm this import row as a movement?" wire:loading.attr="disabled" class="font-semibold text-emerald-700 hover:underline">Confirm</button><button type="button" wire:click="dismiss('{{ $row->id }}')" wire:confirm="Dismiss this import row?" wire:loading.attr="disabled" class="text-red-700 hover:underline">Dismiss</button>@endif @endcan</div>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <div class="app-card px-5 py-10 text-center text-sm text-zinc-500">No import proposals yet.</div>
    @endforelse
</div>
