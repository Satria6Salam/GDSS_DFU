@php
    use App\Enums\AssessmentStatus;
    $fmt = fn ($v) => number_format($v, 4, ',', '.');
    $done = $assessment && in_array($assessment->status, [AssessmentStatus::Final, AssessmentStatus::Terkunci], true);
@endphp
<x-layouts.app title="Ringkasan & Hasil" active="penilaian">
    <x-slot:header>
        <x-page-header crumb="Sesi {{ $session->title }} › Penilaian Saya" :status="$session->status">
            Ringkasan &amp; Hasil Saya
        </x-page-header>
    </x-slot:header>

    @include('assessment.partials.frame', ['tab' => 'result'])

    @if ($assessment)
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card-soft">
                    <h3>Kelengkapan Penilaian</h3>
                    @php $pairsOk = $pairsFilled === 15; $scoresOk = $scoresFilled === $scoresTarget; $crOk = $liveCr !== null && $liveCr <= 0.10; @endphp
                    <div class="check-line">
                        <i class="bi bi-{{ $pairsOk ? 'check-circle-fill' : 'x-circle-fill' }}"></i>
                        <span class="flex-grow-1">Perbandingan kriteria</span><b>{{ $pairsFilled }} / 15</b>
                    </div>
                    <div class="check-line">
                        <i class="bi bi-{{ $scoresOk ? 'check-circle-fill' : 'x-circle-fill' }}"></i>
                        <span class="flex-grow-1">Skor kasus</span><b>{{ $scoresFilled }} / {{ $scoresTarget }}</b>
                    </div>
                    <div class="check-line">
                        <i class="bi bi-{{ $crOk ? 'check-circle-fill' : 'x-circle-fill' }}"></i>
                        <span class="flex-grow-1">Konsistensi (CR ≤ 0,10)</span>
                        <b>{{ $liveCr === null ? '–' : $fmt($liveCr) }}</b>
                    </div>

                    @if ($editable)
                        <form method="POST" action="{{ route('assessment.submit', $session) }}" class="mt-3">
                            @csrf
                            <button class="btn btn-grad w-100" @disabled($missing > 0 || ! $crOk)>
                                <i class="bi bi-send-check me-1"></i> Kirim Final
                            </button>
                        </form>
                        <p class="small text-muted-2 mt-2 mb-0">
                            @if ($missing > 0)
                                Masih ada {{ $missing }} isian kosong.
                            @elseif (! $crOk)
                                CR melebihi 0,10. Tinjau kembali perbandingan berpasangan.
                            @else
                                Setelah dikirim, penilaian dapat ditarik kembali selama voting belum dimulai.
                            @endif
                        </p>
                    @elseif ($canWithdraw)
                        <form method="POST" action="{{ route('assessment.withdraw', $session) }}" class="mt-3">
                            @csrf
                            <button class="btn btn-outline-soft w-100"><i class="bi bi-arrow-counterclockwise me-1"></i> Tarik Kembali ke Draf</button>
                        </form>
                        <p class="small text-muted-2 mt-2 mb-0">Dikirim {{ $assessment->submitted_at?->format('d-m-Y H:i') }}.</p>
                    @elseif ($assessment->status === AssessmentStatus::Terkunci)
                        <p class="small text-muted-2 mt-3 mb-0"><i class="bi bi-lock me-1"></i> Penilaian terkunci karena voting sudah dimulai.</p>
                    @endif
                </div>

                @if ($done && $ahp)
                    <div class="card-soft mt-4">
                        <h3>Bobot Kriteria (AHP)</h3>
                        @php $max = $ahp->weights->max('weight'); @endphp
                        @foreach ($ahp->weights->sortBy(fn ($w) => $w->criterion->sort_order) as $weight)
                            <div class="weight-row" title="{{ $weight->criterion->name }}">
                                <b>{{ $weight->criterion->code }}</b>
                                <div class="track"><i style="width: {{ $weight->weight / $max * 100 }}%"></i></div>
                                <span>{{ $fmt($weight->weight) }}</span>
                            </div>
                        @endforeach
                        <div class="small text-muted-2 mt-2">λmax = {{ $fmt($ahp->lambda_max) }} · CI = {{ $fmt($ahp->ci) }} · CR = {{ $fmt($ahp->cr) }}</div>
                    </div>
                @endif
            </div>

            <div class="col-lg-7">
                <div class="card-soft h-100">
                    <h3>Peringkat TOPSIS Saya</h3>
                    @if ($done && $ranking->isNotEmpty())
                        <p class="small text-muted-2">Urutan ini menjadi suara Anda pada tahap voting (D04). Nilai V = kedekatan relatif ke
                            solusi ideal; makin besar makin diprioritaskan.</p>
                        @foreach ($ranking as $row)
                            <div @class(['rank-row', 'top' => $row->rank <= 3])>
                                <span class="rank-no">{{ $row->rank }}</span>
                                <b class="text-navy">
                                    {{ $row->patientCase->kode_kasus }}
                                    @if ($row->patientCase->hasRedFlag()) <span class="badge-status badge-rf ms-1">RF</span> @endif
                                </b>
                                <div class="track" title="D⁺ {{ $fmt($row->d_plus) }} · D⁻ {{ $fmt($row->d_minus) }}"><i style="width: {{ $row->v * 100 }}%"></i></div>
                                <span class="small">{{ $fmt($row->v) }}</span>
                            </div>
                        @endforeach
                    @else
                        <div class="text-center py-5">
                            <span class="icon-tile blue mb-3"><i class="bi bi-bar-chart-line"></i></span>
                            <p class="text-muted-2 mb-0">Bobot dan peringkat TOPSIS Anda tampil setelah penilaian dikirim final.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-layouts.app>
