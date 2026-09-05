@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'Laravel')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-xl bg-accent-content text-accent-foreground shadow-sm shadow-emerald-900/10">
            <x-app-logo-icon class="size-5 fill-current text-white" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'Laravel')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-xl bg-accent-content text-accent-foreground shadow-sm shadow-emerald-900/10">
            <x-app-logo-icon class="size-5 fill-current text-white" />
        </x-slot>
    </flux:brand>
@endif
