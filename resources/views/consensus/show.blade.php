@php
    use App\Enums\ConsensusStatus;
    use App\Enums\ResultScope;
    $fmt = fn ($v) => number_format($v, 4, ',', '.');
    $maxPoints = max(1, ($caseCount - 1) * $members->count());
@endphp
<x-layouts.app title="Hasil Konsensus" active="hasil">
    <x-slot:header>
        <x-page-header crumb="Sesi {{ $session->title }} › Hasil Konsensus" :status="$session->status">
            Urutan Prioritas Evaluasi
        </x-page-header>
    </x-slot:header>

    @if ($redFlagCases->isNotEmpty())
        <div class="alert-redflag mb-4" role="alert">
            <span class="icon-tile"><i class="bi bi-exclamation-triangle"></i></span>
            @php $flagged = $redFlagCases->map(fn ($c) => $c->kode_kasus.' ('.$c->redFlags->map(fn ($f) => $f->code->value)->implode(', ').')')->implode('; '); @endphp
            <div>Eskalasi Segera: {{ $flagged }} tetap dievaluasi segera, terlepas dari urutan konsensus.</div>
        </div>
    @endif

    @if (! $consensus)
        <div class="card-soft text-center py-5">
            <span class="icon-tile blue mb-3"><i class="bi bi-hourglass-split"></i></span>
            <h3>Hasil belum tersedia</h3>
            <p class="text-muted-2 mb-0">
                @if ($session->status->step() === 4)
                    Semua suara sudah masuk. Hasil dapat dilihat setelah ketua memfinalisasi keputusan.
                @else
                    Hasil konsensus tersedia setelah voting selesai dan ketua memfinalisasi keputusan.
                @endif
            </p>
        </div>
    @else
        @unless ($isFinal)
            <div class="alert alert-info small d-flex gap-2 align-items-center">
                <i class="bi bi-eye"></i> Pratinjau ketua: hanya total poin dan urutan. Rincian suara dan bobot tiap anggota terbuka setelah finalisasi (D13).
                @if ($consensus->status === ConsensusStatus::PerluMusyawarah)
                    <b>Ada seri yang perlu ditetapkan di Panel Ketua.</b>
                @endif
            </div>
        @endunless

        {{-- Banner hasil: tiga prioritas teratas --}}
        <div class="hero mb-4" style="background-image: url('{{ asset('images/banner-rapat.jpg') }}')">
            <div class="eyebrow">{{ $isFinal ? 'Keputusan kelompok' : 'Pratinjau sebelum finalisasi' }}</div>
            <h1>{{ $consensus->items->count() }} kasus diurutkan dari {{ $members->count() }} suara</h1>
            <p>Borda Count atas peringkat TOPSIS tiap decision maker; seri dipecah dengan V TOPSIS kelompok (D05).</p>
            <div class="hero-stats">
                @foreach ($consensus->items->take(3) as $item)
                    <div>
                        <small>Prioritas {{ $item->final_rank }}</small>
                        <b>{{ $item->patientCase->kode_kasus }}</b>
                        <span class="small" style="color:#C9D6E6">· {{ $item->borda_points }} poin
                            @if ($item->patientCase->hasRedFlag()) · <span style="color:#FFB4B4">red flag {{ $item->patientCase->redFlags->map(fn ($f) => $f->code->value)->implode(', ') }}</span> @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        @if ($conclusion)
            @include('consensus.partials.conclusion', ['conclusion' => $conclusion, 'consensus' => $consensus])
        @endif

        <div class="card-soft">
            <h3>Hasil Borda Count</h3>
            <div class="table-responsive">
                <table class="table align-middle mb-2">
                    <thead>
                    <tr>
                        <th>#</th><th>Kasus</th>
                        @if ($isFinal)
                            @foreach ($members as $member)
                                <th>{{ $member->role->label() }}</th>
                            @endforeach
                        @endif
                        <th style="width: 30%">Total poin</th><th>V kelompok</th><th>Pemecah seri</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($consensus->items as $item)
                        <tr>
                            <td class="fw-bold text-navy">{{ $item->final_rank }}</td>
                            <td class="fw-bold">
                                {{ $item->patientCase->kode_kasus }}
                                @if ($item->patientCase->hasRedFlag()) <span class="badge-status badge-rf ms-1">RF</span> @endif
                            </td>
                            @if ($isFinal)
                                @foreach ($members as $member)
                                    @php $pos = isset($positions[$member->id][$item->case_id]) ? $positions[$member->id][$item->case_id] + 1 : null; @endphp
                                    <td>{{ $pos ? $pos.' ('.($caseCount - $pos).')' : '–' }}</td>
                                @endforeach
                            @endif
                            <td>
                                <div class="d-flex gap-2 align-items-center">
                                    <b style="width: 24px">{{ $item->borda_points }}</b>
                                    <div class="track flex-grow-1"><i style="width: {{ $item->borda_points / $maxPoints * 100 }}%"></i></div>
                                </div>
                            </td>
                            <td>{{ $fmt($item->group_v) }}</td>
                            <td class="small text-muted-2">{{ $item->tie_break_rule ? str_replace('_', ' ', $item->tie_break_rule->value) : '–' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="small text-muted-2">
                @if ($isFinal) Format suara: posisi (poin). @endif
                {{ $consensus->items->whereNotNull('tie_break_rule')->isEmpty() ? 'Tidak ada seri.' : 'Urutan kasus yang seri ditentukan oleh aturan pemecah seri D05.' }}
                @if ($consensus->deliberation_note) Catatan musyawarah: “{{ $consensus->deliberation_note }}”. @endif
                @if ($isFinal && $consensus->finalized_at)
                    Difinalisasi oleh {{ $consensus->finalizer?->name }}, {{ $consensus->finalized_at->format('d-m-Y H:i') }}.
                @endif
            </div>
        </div>

        @if ($isFinal && $weights->isNotEmpty())
            <div class="card-soft mt-4">
                <h3>Rincian Bobot Kriteria (AHP)</h3>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Sumber</th>
                            @foreach ($weights->first()->weights->sortBy(fn ($w) => $w->criterion->sort_order) as $w)
                                <th title="{{ $w->criterion->name }}">{{ $w->criterion->code }}</th>
                            @endforeach
                            <th>CR</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($weights as $result)
                            <tr @class(['fw-bold' => $result->scope === ResultScope::Kelompok])>
                                <td>{{ $result->scope === ResultScope::Kelompok ? 'Kelompok (AIJ)' : $result->user?->role->label() }}</td>
                                @foreach ($result->weights->sortBy(fn ($w) => $w->criterion->sort_order) as $w)
                                    <td>{{ $fmt($w->weight) }}</td>
                                @endforeach
                                <td>{{ $fmt($result->cr) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</x-layouts.app>
