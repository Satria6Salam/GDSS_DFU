@props(['status'])
@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $label = str_replace('_', ' ', $value);
    $class = 'badge-'.strtolower(explode('_', $value)[0]);
@endphp
<span {{ $attributes->merge(['class' => "badge-status {$class}"]) }}>{{ $label }}</span>
