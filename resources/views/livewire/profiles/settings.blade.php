<div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6">
    <div>
        <a href="{{ route('promises.index', $profile) }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">← Back to promises</a>
        <div class="app-eyebrow mt-6">{{ $profile->name }}</div>
        <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">Profile settings</flux:heading>
        <flux:text class="mt-2">Update the name and timezone used for this financial profile.</flux:text>
    </div>

    @if (session('profile-settings-saved'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('profile-settings-saved') }}</div>
    @endif

    <section class="app-card rounded-2xl p-5 sm:p-7">
        <form wire:submit="save" class="space-y-5">
            <flux:input wire:model="name" label="Profile name" required />
            <flux:select wire:model="timezone" label="Timezone" required>
                @foreach ($timezones as $timezoneOption)
                    <option value="{{ $timezoneOption }}">{{ $timezoneOption }}</option>
                @endforeach
            </flux:select>
            <div class="rounded-xl bg-zinc-50 px-4 py-3 dark:bg-zinc-900">
                <flux:text class="font-medium">Base currency: {{ $profile->base_currency }}</flux:text>
                <flux:text class="mt-1 text-sm">Base currency is read-only so existing ledger amounts never change.</flux:text>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-3 text-sm">
                    <a href="{{ route('profile-tokens.index', $profile) }}" wire:navigate class="font-semibold text-emerald-700 hover:text-emerald-900">API tokens</a>
                    <a href="{{ route('members.index', $profile) }}" wire:navigate class="font-semibold text-zinc-600 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white">Members</a>
                    <a href="{{ route('profile.exports.csv', $profile) }}" class="font-semibold text-zinc-600 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white">Export CSV</a>
                    <a href="{{ route('profile.exports.json', $profile) }}" class="font-semibold text-zinc-600 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white">Export JSON</a>
                </div>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Save settings</flux:button>
            </div>
        </form>
    </section>
</div>
