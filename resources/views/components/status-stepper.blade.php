@props(['status', 'variant' => 'steps'])
@php
    $current = $status->step();
@endphp
@if ($variant === 'pills')
    <div {{ $attributes->merge(['class' => 'hero-pills']) }}>
        @foreach (\App\Enums\SessionStatus::cases() as $stage)
            <span @class(['done' => $stage->step() < $current, 'current' => $stage->step() === $current])>
                {{ $stage->step() < $current ? '✓ ' : '' }}{{ $stage->label() }}
            </span>
        @endforeach
    </div>
@else
    <div {{ $attributes->merge(['class' => 'stepper']) }}>
        @foreach (\App\Enums\SessionStatus::cases() as $stage)
            <span @class(['done' => $stage->step() < $current, 'current' => $stage->step() === $current])>
                {{ $stage->step() < $current ? '✓ ' : '' }}{{ mb_strtoupper($stage->label()) }}
            </span>
        @endforeach
    </div>
@endif
