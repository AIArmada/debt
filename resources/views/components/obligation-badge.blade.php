@props(['kind' => 'money', 'label' => null])

@php
    $tone = match ($kind) {
        'asset' => 'asset',
        'service' => 'service',
        'action' => 'action',
        default => 'money',
    };
    $defaultLabel = match ($kind) {
        'asset' => 'Asset / item',
        'service' => 'Service / time',
        'action' => 'Promise / action',
        default => 'Money',
    };
@endphp

<x-status-badge :tone="$tone" :label="$label ?? $defaultLabel" {{ $attributes }} />
