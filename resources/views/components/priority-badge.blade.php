@props(['rank' => 1, 'label' => null])

@php
    $rank = max(1, (int) $rank);
    $tone = $rank <= 2 ? 'priority-high' : ($rank <= 4 ? 'priority-medium' : 'priority-normal');
    $defaultLabel = 'Priority #'.$rank;
@endphp

<x-status-badge :tone="$tone" :label="$label ?? $defaultLabel" {{ $attributes }} />
