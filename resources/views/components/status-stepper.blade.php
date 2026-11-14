@props(['status', 'variant' => 'light'])
{{-- Tahap sesi bernomor (Bab 7.2). Varian "dark" untuk dipakai di atas banner berfoto. --}}
@php $current = $status->step(); @endphp
<ol {{ $attributes->class(['steps', 'steps-dark' => $variant === 'dark']) }} aria-label="Tahap sesi">
    @foreach (\App\Enums\SessionStatus::cases() as $stage)
        @php $n = $stage->step(); @endphp
        <li @class(['done' => $n < $current, 'current' => $n === $current]) @if ($n === $current) aria-current="step" @endif>
            <span class="num">@if ($n < $current)<i class="bi bi-check-lg"></i>@else{{ $n }}@endif</span>
            <span class="label">{{ $stage->label() }}</span>
        </li>
    @endforeach
</ol>
