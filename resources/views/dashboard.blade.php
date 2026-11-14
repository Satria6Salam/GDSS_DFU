@php
    use App\Enums\AssessmentStatus;
    use App\Enums\SessionStatus;
@endphp
<x-layouts.app title="Dashboard" active="dashboard">
    @if (! $session)
        <div class="card-soft text-center py-5">
            <span class="icon-tile blue mb-3"><i class="bi bi-folder2-open"></i></span>
            <h3>Belum ada sesi keputusan</h3>
            <p class="text-muted-2 mb-0">
                {{ $user->isLeader() ? 'Buat sesi baru untuk memulai penilaian.' : 'Ketua tim belum membuat sesi keputusan.' }}
            </p>
        </div>
    @else
        {{-- Hero sambutan --}}
        <div class="hero d-flex flex-column flex-lg-row gap-4">
            <div class="flex-grow-1">
                <div class="eyebrow">Sesi {{ $session->title }} · {{ $user->isLeader() ? 'Ketua Tim' : $user->role->label() }}</div>
                <h1>Selamat datang, {{ $user->name }}</h1>
                <div style="opacity: .92; max-width: 540px; line-height: 1.5">{{ $session->status->description() }}</div>
                <x-status-stepper :status="$session->status" variant="pills" />
            </div>
            <div class="d-flex flex-column gap-2 justify-content-center">
                <div class="float-card">
                    <span class="icon-tile"><i class="bi bi-check2-circle"></i></span>
                    <div><b>{{ $finalCount }} / {{ $members->count() }} Final</b><small>Penilaian anggota</small></div>
                </div>
                <div class="float-card">
                    <span class="icon-tile blue"><i class="bi bi-sliders"></i></span>
                    <div>
                        @if ($myAssessment?->cr !== null)
                            <b>CR {{ number_format($myAssessment->cr, 4, ',', '.') }}</b>
                            <small>{{ $myAssessment->cr <= 0.10 ? 'Matriks AHP Anda konsisten' : 'Matriks AHP perlu ditinjau' }}</small>
                        @else
                            <b>CR belum dihitung</b><small>Isi perbandingan kriteria</small>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if ($redFlagCases->isNotEmpty())
            <x-red-flag-alert :cases="$redFlagCases" class="mt-3 d-lg-none" />
        @endif

        {{-- Aksi cepat --}}
        <div class="card-soft mt-4">
            <div class="panel-title">Apa yang perlu Anda <span class="acc">lakukan?</span></div>
            @php
                $canAssess = $session->isStatus(SessionStatus::Penilaian);
                $actions = [
                    ['icon' => 'sliders', 'tone' => 'blue', 'title' => 'Perbandingan Kriteria',
                     'text' => "{$myProgress['pairs']}/{$myProgress['pairs_target']} pasang AHP terisi", 'enabled' => $canAssess,
                     'href' => route('assessment.ahp', $session)],
                    ['icon' => 'clipboard2-pulse', 'tone' => '', 'title' => 'Skor Kasus',
                     'text' => "{$myProgress['scores']}/{$myProgress['scores_target']} skor C1–C6 terisi", 'enabled' => $canAssess,
                     'href' => route('assessment.scores', $session)],
                    ['icon' => 'bar-chart-line', 'tone' => 'blue', 'title' => 'Ringkasan & Hasil Saya',
                     'text' => 'Kirim final, bobot, nilai V, peringkat', 'enabled' => (bool) $myAssessment,
                     'href' => route('assessment.result', $session)],
                    $user->isLeader()
                        ? ['icon' => 'people', 'tone' => '', 'title' => 'Panel Ketua', 'text' => 'Pantau anggota, ubah tahap sesi', 'enabled' => true,
                           'href' => route('control.show', $session)]
                        : ($session->isStatus(SessionStatus::Final)
                            ? ['icon' => 'trophy', 'tone' => '', 'title' => 'Hasil Konsensus', 'text' => 'Urutan prioritas evaluasi', 'enabled' => true,
                               'href' => route('consensus.show', $session)]
                            : ['icon' => 'award', 'tone' => '', 'title' => 'Voting', 'text' => 'Konfirmasi suara Anda',
                               'enabled' => $session->isStatus(SessionStatus::Voting), 'href' => route('vote.show', $session)]),
                ];
            @endphp
            <div class="row g-3">
                @foreach ($actions as $action)
                    <div class="col-sm-6 col-xl-3">
                        <a href="{{ $action['href'] ?? '#' }}" @class(['quick', 'is-disabled' => ! $action['enabled'] || ! isset($action['href'])]) aria-disabled="{{ $action['enabled'] ? 'false' : 'true' }}">
                            <span class="icon-tile {{ $action['tone'] }}"><i class="bi bi-{{ $action['icon'] }}"></i></span>
                            <div>
                                <b>{{ $action['title'] }}</b>
                                <small>{{ $action['text'] }}</small>
                                <div class="go"><i class="bi bi-arrow-right"></i></div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="row g-4 mt-0">
            <div class="col-lg-7">
                <div class="card-soft h-100">
                    @if ($user->isLeader())
                        <h3>Status Penilaian Anggota</h3>
                        @foreach ($members as $member)
                            <div class="member">
                                <span @class(['avatar', 'teal' => $member['user']->role->value === 'perawat', 'gray' => $member['user']->role->value === 'apoteker'])>
                                    {{ $member['user']->initials() }}
                                </span>
                                <div class="who">
                                    <b>{{ $member['user']->name }}</b>
                                    <small>
                                        {{ $member['user']->isLeader() ? 'Ketua · ' : '' }}
                                        @if ($member['assessment']?->submitted_at)
                                            dikirim {{ $member['assessment']->submitted_at->format('d-m-Y H:i') }}
                                        @elseif ($member['assessment'])
                                            terakhir disimpan {{ $member['assessment']->updated_at->format('d-m-Y H:i') }}
                                        @else
                                            belum mulai
                                        @endif
                                    </small>
                                </div>
                                <x-status-badge :status="$member['status']" />
                            </div>
                        @endforeach
                        <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
                            @php $ready = $session->isStatus(SessionStatus::Penilaian) && $waiting->isEmpty(); @endphp
                            <form method="POST" action="{{ route('sessions.start-voting', $session) }}">
                                @csrf
                                <button type="submit" class="btn btn-grad" @disabled(! $ready)>
                                    <i class="bi bi-{{ $ready ? 'play-circle' : 'lock' }} me-1"></i> Mulai Voting
                                </button>
                            </form>
                            @if ($session->isStatus(SessionStatus::Penilaian) && $waiting->isNotEmpty())
                                <span class="text-muted-2 small">Menunggu: {{ $waiting->implode(', ') }}</span>
                            @elseif (! $session->isStatus(SessionStatus::Penilaian))
                                <span class="text-muted-2 small">Tersedia pada tahap Penilaian.</span>
                            @endif
                        </div>
                    @else
                        <h3>Penilaian Saya</h3>
                        <div class="member">
                            <span class="avatar">{{ $user->initials() }}</span>
                            <div class="who"><b>{{ $user->name }}</b><small>{{ $user->role->label() }}</small></div>
                            <x-status-badge :status="$myAssessment?->status ?? AssessmentStatus::Belum" />
                        </div>
                        <p class="text-muted-2 small mt-3 mb-0">
                            Isi perbandingan kriteria dan skor setiap kasus, lalu kirim final sebelum ketua memulai voting.
                            Hasil anggota lain dapat dilihat setelah keputusan difinalisasi.
                        </p>
                    @endif
                </div>
            </div>

            <div class="col-lg-5 d-flex flex-column gap-4">
                @if ($redFlagCases->isNotEmpty())
                    <div class="emergency">
                        <span class="icon-tile"><i class="bi bi-exclamation-triangle"></i></span>
                        <h3>Eskalasi Segera</h3>
                        @foreach ($redFlagCases as $case)
                            <p class="mb-2">
                                <b>{{ $case->kode_kasus }}</b> memiliki tanda bahaya
                                {{ $case->redFlags->map(fn ($f) => $f->code->value.' ('.mb_strtolower($f->code->label()).')')->implode(' dan ') }}.
                            </p>
                        @endforeach
                        <p class="mb-0 small">Jangan menunggu voting; ikuti protokol klinis.</p>
                    </div>
                @endif
                <div class="card-soft">
                    <h3><i class="bi bi-bell me-1"></i> Notifikasi</h3>
                    @forelse ($notifications as $notification)
                        <div class="small text-muted-2 mb-1">• {{ $notification->data['message'] ?? '' }}</div>
                    @empty
                        <div class="small text-muted-2">Belum ada notifikasi.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <x-stat-strip class="mt-4" :items="[
            ['icon' => 'clipboard2-pulse', 'value' => $caseCount, 'label' => 'Kasus simulasi'],
            ['icon' => 'exclamation-triangle', 'value' => $redFlagCases->count(), 'label' => 'Kasus red flag'],
            ['icon' => 'sliders', 'value' => $criteriaCount, 'label' => 'Kriteria · rubrik '.config('gdss.criteria_version')],
            ['icon' => 'check2-circle', 'value' => $finalCount.'/'.$members->count(), 'label' => 'Penilaian final'],
        ]" />
    @endif
</x-layouts.app>
