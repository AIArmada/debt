<div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-7">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><div class="flex items-start gap-4"><span class="app-page-icon hidden shrink-0 sm:inline-flex"><flux:icon name="squares-2x2" class="size-6" /></span><div><div class="app-eyebrow">Workspaces</div><flux:heading size="xl" class="mt-2 text-3xl tracking-tight">Financial profiles</flux:heading><flux:text class="mt-2 leading-6">Keep personal, family, and business records separate.</flux:text></div></div><flux:button variant="ghost" :href="route('data.export')"><flux:icon name="arrow-down-tray" class="size-4" /> Export my data</flux:button></div>

    @if (session('profile-created'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200" role="status">{{ session('profile-created') }}</div>
    @endif
    @if (session('invitation-accepted'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200" role="status">{{ session('invitation-accepted') }}</div>
    @endif

    <div class="grid gap-7 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700"><flux:heading size="lg">Your profiles</flux:heading></div>
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($profiles as $profile)
                    <div class="flex items-center justify-between gap-4 px-5 py-4">
                        <div><div class="font-medium">{{ $profile->name }}</div><div class="mt-1 text-sm text-zinc-500">{{ ucfirst($profile->type) }} · {{ $profile->base_currency }} · {{ $profile->records()->where('is_archived', false)->count() }} records</div><div class="mt-1 text-xs text-zinc-500">{{ $roles[$profile->id] === 'owner' ? 'Owner' : ucfirst(str_replace('_', ' ', (string) $roles[$profile->id])) }}</div>@if ($profile->is_islamic_mode_enabled)<div class="mt-1 text-xs text-zinc-500">Islamic Mode enabled</div>@endif</div>
                        <div class="flex items-center gap-2"><flux:button size="sm" variant="ghost" :href="route('dashboard', ['profile' => $profile->id])" wire:navigate>Open</flux:button>@can('update', $profile)<flux:button size="sm" variant="ghost" :href="route('financial-profiles.edit', $profile)" wire:navigate>Edit</flux:button>@endcan</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700"><flux:heading size="lg">Add profile</flux:heading></div>
            <form wire:submit="save" class="space-y-5 px-5 py-5">
                <flux:input wire:model="name" label="Name" required />
                <flux:select wire:model="type" label="Type"><flux:select.option value="personal">Personal</flux:select.option><flux:select.option value="business">Business</flux:select.option><flux:select.option value="family">Family</flux:select.option><flux:select.option value="custom">Custom</flux:select.option></flux:select>
                <flux:select wire:model="baseCurrency" label="Base currency" required>@foreach (\App\Domain\Money\Currency::options() as $code => $label)<flux:select.option value="{{ $code }}">{{ $code }} — {{ $label }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model="timezone" label="Timezone" description="Used for due dates, reminders, and date display." searchable required>@foreach (\App\Support\ProfileOptions::timezones() as $value => $label)<flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model="locale" label="Locale" description="Controls language and regional formatting when available." searchable placeholder="Choose a locale (optional)"><flux:select.option value="">Use the application default</flux:select.option>@foreach (\App\Support\ProfileOptions::locales() as $value => $label)<flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>@endforeach</flux:select>
                <flux:checkbox wire:model="isIslamicModeEnabled" label="Enable Islamic Mode guidance" />
                <flux:button type="submit" variant="primary" class="w-full">Create profile</flux:button>
            </form>
        </section>
    </div>
</div>
