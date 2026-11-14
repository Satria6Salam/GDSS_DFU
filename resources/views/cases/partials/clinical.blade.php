{{-- Atribut klinis kasus (Bab 4.2), dikelompokkan sesuai kriteria C1–C6 agar mudah dipakai saat memberi skor. --}}
@php
    $fmt = fn ($v, $d = 1) => number_format($v, $d, ',', '.');
    $yn = fn ($v) => $v ? 'Ya' : 'Tidak';
    $groups = [
        'Identitas' => [
            'Usia / jenis kelamin' => "{$case->usia} th / {$case->jenis_kelamin}",
            'Lama DM' => "{$case->lama_dm} tahun",
            'Lokasi luka' => $case->lokasi_luka,
        ],
        'C1 Infeksi' => [
            'Tanda inflamasi' => $yn($case->tanda_inflamasi),
            'Eritema' => $fmt($case->eritema).' cm',
            'Suhu · nadi · RR' => $fmt($case->suhu).' °C · '.$case->nadi.' · '.$case->laju_napas,
            'Leukosit' => $fmt($case->leukosit).' ×10³/µL',
        ],
        'C2 Perfusi' => [
            'ABI' => $fmt($case->abi, 2),
            'Nadi kaki' => $case->nadi_kaki,
            'Nyeri istirahat · gangren' => $yn($case->nyeri_istirahat).' · '.$yn($case->gangren),
        ],
        'C3 Luka' => [
            'Luas' => $fmt($case->luas_luka).' cm²',
            'Kedalaman' => $case->kedalaman,
        ],
        'C4 Neuropati' => [
            'Monofilamen' => "{$case->monofilamen} dari 3 titik terasa",
            'Deformitas' => $case->deformitas,
        ],
        'C5 Sistemik' => [
            'HbA1c · GDS' => $fmt($case->hba1c).'% · '.$case->gds.' mg/dL',
            'eGFR' => $case->egfr,
            'Tekanan darah' => $case->tekanan_darah,
        ],
        'C6 Progres' => [
            'Perkembangan luka' => $case->progres_luka->label(),
        ],
        'Farmasi' => [
            'Alergi obat' => $case->alergi_obat,
            'Obat rutin' => $case->obat_rutin,
        ],
    ];
@endphp
<dl class="clinical">
    @foreach ($groups as $group => $rows)
        <div class="clinical-group">
            <dt>{{ $group }}</dt>
            <dd>
                @foreach ($rows as $label => $value)
                    <div><span>{{ $label }}</span><b>{{ $value }}</b></div>
                @endforeach
            </dd>
        </div>
    @endforeach
</dl>
