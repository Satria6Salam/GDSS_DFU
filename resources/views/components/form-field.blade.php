@props(['name', 'label', 'value' => null, 'type' => 'text', 'unit' => null, 'hint' => null, 'options' => null])
{{-- Satu isian form dengan label, satuan, dan pesan galat (Bab 4.3). $options = [value => label] untuk select. --}}
@php $current = old($name, $value instanceof \BackedEnum ? $value->value : $value); @endphp
<div {{ $attributes->merge(['class' => 'mb-3']) }}>
    <label for="f_{{ $name }}" class="form-label">{{ $label }}</label>
    <div class="input-group">
        @if ($options !== null)
            <select id="f_{{ $name }}" name="{{ $name }}" @class(['form-select', 'is-invalid' => $errors->has($name)])>
                @foreach ($options as $optValue => $optLabel)
                    <option value="{{ $optValue }}" @selected((string) $current === (string) $optValue)>{{ $optLabel }}</option>
                @endforeach
            </select>
        @else
            <input id="f_{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $current }}"
                   @class(['form-control', 'is-invalid' => $errors->has($name)])>
        @endif
        @if ($unit)
            <span class="input-group-text small">{{ $unit }}</span>
        @endif
    </div>
    @error($name)
        <div class="text-danger small mt-1">{{ $message }}</div>
    @else
        @if ($hint) <div class="small text-muted-2 mt-1">{{ $hint }}</div> @endif
    @enderror
</div>
