@props(['tone' => 'neutral', 'label' => null])

@php
    $allowedTones = [
        'info', 'success', 'warning', 'danger', 'neutral',
        'incoming', 'outgoing', 'money', 'asset', 'service', 'action',
        'priority-high', 'priority-medium', 'priority-normal',
    ];
    $tone = in_array($tone, $allowedTones, true) ? $tone : 'neutral';
@endphp

<span {{ $attributes->class(['app-status', 'app-status-'.$tone]) }} aria-label="{{ $label ?? $slot }}">
    <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
    {{ $label ?? $slot }}
</span>
