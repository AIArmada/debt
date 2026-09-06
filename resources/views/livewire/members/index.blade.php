<div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
    <div>
        <a href="{{ route('promises.index', $profile) }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">← Back to promises</a>
        <div class="app-eyebrow mt-6">{{ $profile->name }}</div>
        <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">Members</flux:heading>
        <flux:text class="mt-2">Invite people to read or update this profile.</flux:text>
    </div>
    @if ($latestInvitationLink)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">Share this one-use invitation link: <a class="break-all underline" href="{{ $latestInvitationLink }}">{{ $latestInvitationLink }}</a></div>
    @endif
    <section class="app-card rounded-2xl p-5 sm:p-7">
        <flux:heading size="lg">Invite a member</flux:heading>
        <form wire:submit="invite" class="mt-5 grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto] sm:items-end">
            <flux:input wire:model="email" type="email" label="Email" required />
            <flux:select wire:model="role" label="Role"><option value="{{ \App\Domain\Enums\MemberRole::Viewer->value }}">Viewer</option><option value="{{ \App\Domain\Enums\MemberRole::Editor->value }}">Editor</option></flux:select>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Invite</flux:button>
        </form>
    </section>
    <section class="app-card overflow-hidden rounded-2xl">
        <div class="border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80"><flux:heading size="lg">Current members</flux:heading></div>
        <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
            @foreach ($members as $member)
                <div wire:key="member-{{ $member->id }}" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><div class="font-medium">{{ $member->user->name }}</div><div class="text-sm text-zinc-500">{{ $member->user->email }}</div></div>
                    <div class="flex items-center gap-3">@if ($member->role !== \App\Domain\Enums\MemberRole::Owner)<select wire:change="changeRole('{{ $member->id }}', $event.target.value)" wire:loading.attr="disabled" class="rounded-lg border-zinc-300 bg-transparent text-sm"><option value="{{ \App\Domain\Enums\MemberRole::Viewer->value }}" @selected($member->role === \App\Domain\Enums\MemberRole::Viewer)>Viewer</option><option value="{{ \App\Domain\Enums\MemberRole::Editor->value }}" @selected($member->role === \App\Domain\Enums\MemberRole::Editor)>Editor</option></select><flux:button wire:click="remove('{{ $member->id }}')" wire:confirm="Remove this member?" variant="ghost" wire:loading.attr="disabled">Remove</flux:button>@else<span class="text-sm text-zinc-500">Owner</span>@endif</div>
                </div>
            @endforeach
        </div>
    </section>
</div>
