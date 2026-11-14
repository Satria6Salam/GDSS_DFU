{{-- Bagian atas bersama tab penilaian: peringatan red flag, tab, dan pemberitahuan mode baca. --}}
@php use App\Enums\AssessmentStatus; @endphp
<x-red-flag-alert :cases="$redFlagCases" class="mb-3" />

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="{{ route('assessment.ahp', $session) }}" @class(['tab-pill', 'active' => $tab === 'ahp'])>1 · Perbandingan Kriteria</a>
    <a href="{{ route('assessment.scores', $session) }}" @class(['tab-pill', 'active' => $tab === 'scores'])>2 · Skor Kasus</a>
    <a href="{{ route('assessment.result', $session) }}" @class(['tab-pill', 'active' => $tab === 'result'])>3 · Ringkasan &amp; Hasil</a>
    @if ($assessment)
        <span class="ms-auto small text-muted-2">Status penilaian</span>
        <x-status-badge :status="$assessment->status" />
    @endif
</div>

@if (! $assessment)
    <div class="card-soft text-center py-5">
        <span class="icon-tile blue mb-3"><i class="bi bi-hourglass-split"></i></span>
        <h3>Penilaian belum dibuka</h3>
        <p class="text-muted-2 mb-0">Ketua tim akan membuka tahap penilaian setelah kasus dan kriteria siap.</p>
    </div>
@elseif (! $editable && $tab !== 'result')
    <div class="alert alert-info rounded-4 border-0 d-flex gap-2 align-items-center small">
        <i class="bi bi-lock"></i>
        @if ($assessment->status === AssessmentStatus::Final)
            Penilaian sudah dikirim final dan ditampilkan dalam mode baca. Tarik kembali di tab Ringkasan untuk mengubahnya.
        @elseif ($assessment->status === AssessmentStatus::Terkunci)
            Penilaian terkunci karena voting sudah dimulai.
        @else
            Penilaian hanya dapat diubah pada tahap Penilaian.
        @endif
    </div>
@endif
