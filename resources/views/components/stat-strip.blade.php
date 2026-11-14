@props(['items'])
{{-- $items: list of ['icon' => 'people', 'value' => '3', 'label' => 'Decision Maker'] --}}
<div {{ $attributes->merge(['class' => 'stat-strip']) }}>
    @foreach ($items as $item)
        <div>
            <i class="bi bi-{{ $item['icon'] }}"></i>
            <span><b>{{ $item['value'] }}</b><small>{{ $item['label'] }}</small></span>
        </div>
    @endforeach
</div>
