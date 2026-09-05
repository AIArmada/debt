<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="app-body min-h-screen antialiased">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200/80 bg-white/80 shadow-[4px_0_24px_rgba(42,67,56,0.035)] backdrop-blur-xl dark:border-zinc-800/80 dark:bg-zinc-950/80 dark:shadow-[4px_0_24px_rgba(0,0,0,0.18)]">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <div class="ms-auto flex items-center gap-1">
                    <livewire:notifications.bell />
                    <flux:sidebar.collapse class="lg:hidden" />
                </div>
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="wallet" :href="route('records.index')" :current="request()->routeIs('records.*')" wire:navigate>
                        {{ __('Records') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="user-group" :href="route('parties.index')" :current="request()->routeIs('parties.*')" wire:navigate>
                        {{ __('Parties') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="squares-2x2" :href="route('financial-profiles.index')" :current="request()->routeIs('financial-profiles.*')" wire:navigate>
                        {{ __('Profiles') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="chart-bar" :href="route('plans.index')" :current="request()->routeIs('plans.*')" wire:navigate>
                        {{ __('Budget & plans') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="arrow-down-tray" :href="route('imports.index')" :current="request()->routeIs('imports.*')" wire:navigate>
                        {{ __('Bank imports') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="bell" :href="route('notifications.settings')" :current="request()->routeIs('notifications.*')" wire:navigate>
                        {{ __('Notifications') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="link" :href="route('integrations.index')" :current="request()->routeIs('integrations.*')" wire:navigate>
                        {{ __('Connections') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="border-b border-zinc-200/80 bg-white/70 backdrop-blur-xl dark:border-zinc-800/80 dark:bg-zinc-950/70 lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <livewire:notifications.bell />

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
