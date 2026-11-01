<div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl" class="text-3xl tracking-tight">{{ __('help.title') }}</flux:heading>
        <flux:text class="mt-2 leading-6">{{ __('help.intro') }}</flux:text>
    </div>

    <section class="app-card space-y-5 rounded-2xl p-5 sm:p-7">
        <div><flux:heading size="lg">{{ __('help.akpk_title') }}</flux:heading><flux:text class="mt-2 leading-6">{{ __('help.akpk_body') }}</flux:text></div>
        <div><flux:heading size="lg">{{ __('help.kasih_title') }}</flux:heading><flux:text class="mt-2 leading-6">{{ __('help.kasih_body') }}</flux:text></div>
        <div><flux:heading size="lg">{{ __('help.befrienders_title') }}</flux:heading><flux:text class="mt-2 leading-6">{{ __('help.befrienders_body') }}</flux:text></div>
        <flux:text class="leading-6">{{ __('help.find_local_help') }}</flux:text>
    </section>

    <section class="app-card rounded-2xl p-5 sm:p-7">
        <flux:heading size="lg">{{ __('help.guidance_title') }}</flux:heading>
        <ul class="mt-4 list-disc space-y-2 ps-5 text-sm leading-6 text-zinc-700 dark:text-zinc-200">
            @foreach (__('help.guidance') as $guidance)
                <li>{{ $guidance }}</li>
            @endforeach
        </ul>
    </section>

    <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-100">
        <flux:heading size="lg">{{ __('help.safety_title') }}</flux:heading>
        <flux:text class="mt-2 leading-6">{{ __('help.safety_body') }}</flux:text>
    </section>

    <flux:text class="text-sm text-zinc-500">{{ __('help.disclaimer') }}</flux:text>
</div>
