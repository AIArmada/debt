<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="app-body min-h-screen antialiased">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200/80 bg-white/80 shadow-[4px_0_24px_rgba(42,67,56,0.035)] backdrop-blur-xl dark:border-zinc-800/80 dark:bg-zinc-950/80 dark:shadow-[4px_0_24px_rgba(0,0,0,0.18)]">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('home') }}" wire:navigate />
                <div class="ms-auto flex items-center gap-1">
                    <flux:sidebar.collapse class="lg:hidden" />
                </div>
            </flux:sidebar.header>

            @php($currentProfile = request()->route('profile'))
            @php($accessibleProfiles = app(\App\Services\ProfileAccess::class)->accessibleProfiles(auth()->user())->get())
            <div class="px-3 py-3">
                <flux:dropdown align="start">
                    <flux:button variant="subtle" class="w-full justify-between" data-test="profile-switcher">
                        <span class="truncate">{{ $currentProfile instanceof \App\Models\FinancialProfile ? $currentProfile->name : 'Your profiles' }}</span>
                        <flux:icon name="chevron-down" class="size-4" />
                    </flux:button>
                    <flux:menu>
                        @foreach ($accessibleProfiles as $accessibleProfile)
                            <flux:menu.item :href="route('promises.index', ['profile' => $accessibleProfile])" wire:navigate>{{ $accessibleProfile->name }}</flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>
            </div>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('home')" :current="request()->routeIs('promises.*')" wire:navigate>
                        {{ __('Home') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="user-group" :href="$currentProfile ? route('people.index', ['profile' => $currentProfile]) : route('home')" :current="request()->routeIs('people.*')" wire:navigate>
                        {{ __('People') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="arrow-up-tray" :href="$currentProfile ? route('imports.index', ['profile' => $currentProfile]) : route('home')" :current="request()->routeIs('imports.*')" wire:navigate>
                        {{ __('Imports') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="chart-bar" :href="$currentProfile ? route('plans.index', ['profile' => $currentProfile]) : route('home')" :current="request()->routeIs('plans.*')" wire:navigate>
                        {{ __('Plans') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="arrow-path-rounded-square" :href="$currentProfile ? route('exchange-rates.index', ['profile' => $currentProfile]) : route('home')" :current="request()->routeIs('exchange-rates.*')" wire:navigate>
                        {{ __('Currency') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="cog" :href="$currentProfile instanceof \App\Models\FinancialProfile ? route('profile-settings.edit', ['profile' => $currentProfile]) : route('profile.edit')" :current="request()->routeIs('profile.edit', 'profile-settings.*', 'profile-tokens.*')" wire:navigate>
                        {{ __('Settings') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <flux:header class="hidden border-b border-zinc-200/80 bg-white/70 backdrop-blur-xl dark:border-zinc-800/80 dark:bg-zinc-950/70 lg:flex">
            <flux:spacer />
            <x-notification-bell :unread-count="$unreadNotificationCount ?? 0" />
        </flux:header>

        <!-- Mobile User Menu -->
        <flux:header class="border-b border-zinc-200/80 bg-white/70 backdrop-blur-xl dark:border-zinc-800/80 dark:bg-zinc-950/70 lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <x-notification-bell :unread-count="$unreadNotificationCount ?? 0" />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
