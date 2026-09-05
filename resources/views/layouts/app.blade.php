<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="app-main min-w-0 w-full max-w-full">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
