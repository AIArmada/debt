@props(['direction' => 'payable', 'label' => null, 'reversed' => false])

@php
    $isReversed = filter_var($reversed, FILTER_VALIDATE_BOOLEAN);
    $tone = $isReversed ? 'warning' : ($direction === 'payable' ? 'outgoing' : 'incoming');
    $defaultLabel = $isReversed ? 'Position changed — review' : ($direction === 'payable' ? 'To pay / return' : 'To receive');
@endphp

<x-status-badge :tone="$tone" :label="$label ?? $defaultLabel" {{ $attributes }} />
