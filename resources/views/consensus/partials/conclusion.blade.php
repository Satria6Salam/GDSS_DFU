{{-- Kesimpulan & tindak lanjut (berbasis aturan, ConclusionService). Bukan diagnosis atau rencana terapi. --}}
@php
    $num = fn ($v) => number_format($v, 1, ',', '.');
    $tierLabel = ['tinggi' => 'Prioritas tinggi', 'sedang' => 'Prioritas sedang', 'rendah' => 'Prioritas rendah'];
@endphp
<div class="card-soft mb-4">
    <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
        <h3 class="mb-0">Kesimpulan &amp; Tindak Lanjut</h3>
        <span class="small text-muted-2">disusun otomatis dari hasil konsensus</span>
    </div>

    <p class="mb-1">{{ $conclusion['headline'] }}</p>
    <p class="small text-muted-2 mb-3">{{ $conclusion['agreement'] }}</p>

    @if ($consensus->conclusion_note)
        <div class="mb-3 p-3" style="background: var(--primary-soft); border-left: 3px solid var(--primary); border-radius: 6px">
            <div class="section-label mb-1">Keputusan tim — catatan {{ $consensus->finalizer?->name ?? 'ketua' }}</div>
            <div style="white-space: pre-line">{{ $consensus->conclusion_note }}</div>
        </div>
    @endif

    @if ($conclusion['urgent'] !== [])
        <div class="emergency mb-3 py-3">
            <div class="section-label mb-1" style="color: #9B1C1C">Evaluasi segera (di luar antrean)</div>
            @foreach ($conclusion['urgent'] as $case)
                <div class="mb-1">
                    <b>{{ $case['code'] }}</b> — {{ implode('; ', $case['red_flags']) }}. {{ $case['action'] }}
                    @foreach ($case['pharmacy'] as $note) <div class="small">Catatan farmasi: {{ $note }}</div> @endforeach
                </div>
            @endforeach
        </div>
    @endif

    <div class="row g-3">
        @foreach ($conclusion['tiers'] as $tier => $cases)
            <div class="col-lg-4">
                <div class="section-label mb-2">{{ $tierLabel[$tier] }} · {{ count($cases) }} kasus</div>
                @foreach ($cases as $case)
                    <div class="border rounded-2 p-3 mb-2" style="border-color: var(--line) !important">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="rank-no">{{ $case['rank'] }}</span>
                            <b class="text-navy">{{ $case['code'] }}</b>
                            <span class="small text-muted-2">{{ $case['points'] }} poin</span>
                            @if ($case['red_flags'] !== []) <span class="badge-status badge-rf ms-auto">red flag</span> @endif
                        </div>
                        @if ($case['reasons'] !== [])
                            <div class="small text-muted-2 mb-1">
                                Skor kelompok menonjol:
                                {{ collect($case['reasons'])->map(fn ($r) => $r['code'].' '.mb_strtolower($r['name']).' ('.$num($r['mean']).')')->implode(', ') }}
                            </div>
                        @else
                            <div class="small text-muted-2 mb-1">Tidak ada kriteria dengan skor kelompok ≥ 3,5.</div>
                        @endif
                        @if ($tier === 'tinggi' && $case['focus'] !== [])
                            <ul class="small mb-1 ps-3">
                                @foreach ($case['focus'] as $focus) <li>{{ $focus }}</li> @endforeach
                            </ul>
                        @endif
                        @if ($case['red_flags'] === [])
                            @foreach ($case['pharmacy'] as $note) <div class="small" style="color: var(--warn)">Farmasi: {{ $note }}</div> @endforeach
                        @endif
                        <div class="small mt-1"><i class="bi bi-arrow-return-right me-1 text-muted-2"></i>{{ $case['action'] }}</div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    <p class="small text-muted-2 mt-2 mb-0">
        <i class="bi bi-info-circle me-1"></i>Kesimpulan ini menyatakan urutan dan fokus evaluasi berdasarkan skor, bukan diagnosis,
        resep, atau rencana terapi. Keputusan penanganan tetap ditetapkan tenaga kesehatan yang berwenang.
    </p>
</div>
