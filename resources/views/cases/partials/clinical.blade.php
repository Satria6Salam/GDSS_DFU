{{-- Atribut klinis kasus (Bab 4.2) dalam grid kunci–nilai. Dipakai di H05 dan H07. --}}
@php
    $fmt = fn ($v, $d = 1) => number_format($v, $d, ',', '.');
    $kv = [
        'Usia / JK' => "{$case->usia} / {$case->jenis_kelamin}",
        'Lokasi luka' => $case->lokasi_luka,
        'Luas luka' => $fmt($case->luas_luka).' cm²',
        'Kedalaman' => $case->kedalaman,
        'Tanda inflamasi' => $case->tanda_inflamasi ? 'Ya' : 'Tidak',
        'Eritema' => $fmt($case->eritema).' cm',
        'Suhu / nadi / RR' => $fmt($case->suhu)." / {$case->nadi} / {$case->laju_napas}",
        'Leukosit' => $fmt($case->leukosit).' ×10³/µL',
        'ABI' => $fmt($case->abi, 2),
        'Nadi kaki' => $case->nadi_kaki,
        'Nyeri istirahat / gangren' => ($case->nyeri_istirahat ? 'Ya' : 'Tidak').' / '.($case->gangren ? 'Ya' : 'Tidak'),
        'Monofilamen' => "{$case->monofilamen} / 3 terasa",
        'Deformitas' => $case->deformitas,
        'HbA1c / GDS' => $fmt($case->hba1c)."% / {$case->gds}",
        'eGFR' => $case->egfr,
        'Tekanan darah' => $case->tekanan_darah,
        'Progres luka' => $case->progres_luka->label(),
        'Alergi obat' => $case->alergi_obat,
        'Obat rutin' => $case->obat_rutin,
    ];
    $wide = ['Progres luka', 'Obat rutin'];
@endphp
<div class="kv">
    @foreach ($kv as $label => $value)
        <div @if (in_array($label, $wide, true)) style="grid-column: 1 / -1" @endif>
            <small>{{ $label }}</small><b>{{ $value }}</b>
        </div>
    @endforeach
</div>
