<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="auth-body min-h-screen antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-8 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="mx-auto flex items-center gap-3 font-semibold tracking-tight text-zinc-900" wire:navigate>
                    <span class="flex size-10 items-center justify-center rounded-xl bg-accent-content text-white shadow-sm shadow-emerald-900/10">
                        <x-app-logo-icon class="size-5 fill-current" />
                    </span>
                    <span>{{ config('app.name', 'Laravel') }}</span>
                </a>
                <div class="auth-card mt-4 flex flex-col gap-6 rounded-2xl p-7 sm:p-8">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
