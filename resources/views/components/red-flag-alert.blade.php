@props(['cases'])
{{-- Peringatan eskalasi versi baris; tampil selama ada kasus ber-red flag dan tidak dapat ditutup (D12). --}}
@if ($cases->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'alert-redflag', 'role' => 'alert']) }}>
        <span class="icon-tile"><i class="bi bi-exclamation-triangle"></i></span>
        <div>
            EVALUASI SEGERA —
            @foreach ($cases as $case)
                {{ $case->kode_kasus }} ({{ $case->redFlags->map(fn ($f) => $f->code->value)->implode(', ') }}){{ $loop->last ? '' : ';' }}
            @endforeach
            memiliki tanda bahaya. Jangan menunggu voting; ikuti protokol klinis.
        </div>
    </div>
@endif
