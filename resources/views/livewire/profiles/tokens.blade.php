<div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
    <div>
        <a href="{{ route('profile-settings.edit', $profile) }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">← Back to profile settings</a>
        <div class="app-eyebrow mt-6">{{ $profile->name }}</div>
        <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">API tokens</flux:heading>
        <flux:text class="mt-2">Use a token to connect an approved integration to this profile.</flux:text>
    </div>

    @if ($latestPlainToken)
        <section x-data="{ copied: false }" class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-4 text-sm text-amber-950">
            <flux:heading size="sm">Copy this secret now</flux:heading>
            <flux:text class="mt-1">It will not be shown again.</flux:text>
            <div class="mt-3 flex gap-2">
                <code x-ref="secret" class="block min-w-0 flex-1 break-all rounded-lg bg-white/70 p-3 text-xs" data-test="new-token-secret">{{ $latestPlainToken }}</code>
                <button type="button" class="shrink-0 rounded-lg border border-amber-400 px-3 py-2 text-xs font-semibold" @click="navigator.clipboard.writeText($refs.secret.textContent.trim()); copied = true"> <span x-text="copied ? 'Copied' : 'Copy'"></span></button>
            </div>
        </section>
    @endif

    <section class="app-card rounded-2xl p-5 sm:p-7">
        <flux:heading size="lg">Create a token</flux:heading>
        <form wire:submit="createToken" class="mt-5 space-y-4">
            <flux:input wire:model="name" label="Name" placeholder="My integration" required />
            <fieldset>
                <legend class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Abilities</legend>
                <div class="mt-2 flex flex-wrap gap-4">
                    @foreach ($abilityOptions as $ability)
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="abilities" value="{{ $ability->value }}" class="rounded border-zinc-300" />
                            {{ ucfirst($ability->value) }}
                        </label>
                    @endforeach
                </div>
                @error('abilities')<div class="mt-1 text-sm text-red-700">{{ $message }}</div>@enderror
            </fieldset>
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Create token</flux:button>
            </div>
        </form>
    </section>

    <section class="app-card overflow-hidden rounded-2xl">
        <div class="border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80"><flux:heading size="lg">Current tokens</flux:heading></div>
        <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
            @forelse ($tokens as $token)
                <div wire:key="token-{{ $token->id }}" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="font-medium">{{ $token->name }}</div>
                        <div class="text-sm text-zinc-500">{{ collect($token->abilities ?? [])->map(fn (string $ability): string => ucfirst($ability))->join(', ') }} · Created {{ $token->created_at->format('d M Y') }}@if ($token->last_used_at) · Last used {{ $token->last_used_at->format('d M Y') }}@else · Not used yet @endif</div>
                    </div>
                    <flux:button wire:click="revoke('{{ $token->id }}')" wire:confirm="Revoke this token? Existing integrations will stop working." variant="ghost" wire:loading.attr="disabled">Revoke</flux:button>
                </div>
            @empty
                <div class="px-5 py-8 text-sm text-zinc-500">No API tokens yet.</div>
            @endforelse
        </div>
    </section>
</div>
