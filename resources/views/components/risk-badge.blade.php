@props(['days' => null, 'label' => null])

@php
    $days = $days === null ? null : (int) $days;
    $tone = $days === null ? 'neutral' : ($days < 0 ? 'danger' : ($days <= 7 ? 'danger' : ($days <= 30 ? 'warning' : 'info')));
    $defaultLabel = $days === null ? 'Maturity date missing' : ($days < 0 ? 'Maturity overdue' : ($days <= 7 ? 'Matures within 7 days' : ($days <= 30 ? 'Matures within 30 days' : 'Maturity scheduled')));
@endphp

<x-status-badge :tone="$tone" :label="$label ?? $defaultLabel" {{ $attributes }} />
