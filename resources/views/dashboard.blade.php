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
        {{-- Banner sesi --}}
        <div class="hero" style="background-image: url('{{ asset('images/banner-koridor.jpg') }}')">
            <div class="eyebrow">Sesi keputusan aktif</div>
            <h1>{{ $session->title }}</h1>
            <p>Selamat datang, {{ $user->name }} — {{ $user->isLeader() ? 'ketua tim' : mb_strtolower($user->role->label()) }}.
                {{ $session->status->description() }}</p>
            <x-status-stepper :status="$session->status" variant="dark" class="mt-3" />
            <div class="hero-stats">
                <div><small>Penilaian final</small><b>{{ $finalCount }} / {{ $members->count() }}</b></div>
                <div><small>Kasus</small><b>{{ $caseCount }}</b></div>
                <div><small>Red flag</small><b>{{ $redFlagCases->count() }}</b></div>
                <div><small>CR AHP Anda</small>
                    <b>{{ $myAssessment?->cr !== null ? number_format($myAssessment->cr, 4, ',', '.') : '–' }}</b>
                </div>
            </div>
        </div>

        @if ($redFlagCases->isNotEmpty())
            <x-red-flag-alert :cases="$redFlagCases" class="mt-3 d-lg-none" />
        @endif

        {{-- Aksi cepat --}}
        <div class="card-soft mt-4">
            <div class="panel-title">Tugas Anda</div>
            @php
                $hasAssessment = (bool) $myAssessment;
                $isDone = in_array($myAssessment?->status, [AssessmentStatus::Final, AssessmentStatus::Terkunci], true);
                $actions = [
                    ['icon' => 'sliders', 'tone' => 'blue', 'title' => 'Perbandingan Kriteria',
                     'text' => "{$myProgress['pairs']} dari {$myProgress['pairs_target']} pasang AHP terisi", 'enabled' => $hasAssessment,
                     'done' => $myProgress['pairs'] === $myProgress['pairs_target'], 'href' => route('assessment.ahp', $session)],
                    ['icon' => 'clipboard2-pulse', 'tone' => '', 'title' => 'Skor Kasus',
                     'text' => "{$myProgress['scores']} dari {$myProgress['scores_target']} skor terisi", 'enabled' => $hasAssessment,
                     'done' => $myProgress['scores_target'] > 0 && $myProgress['scores'] === $myProgress['scores_target'], 'href' => route('assessment.scores', $session)],
                    ['icon' => 'bar-chart-line', 'tone' => 'blue', 'title' => 'Ringkasan & Hasil Saya',
                     'text' => $isDone ? 'Penilaian sudah dikirim final' : 'Kirim final, lihat bobot dan peringkat', 'enabled' => $hasAssessment,
                     'done' => $isDone, 'href' => route('assessment.result', $session)],
                    $user->isLeader()
                        ? ['icon' => 'people', 'tone' => '', 'title' => 'Panel Ketua', 'text' => 'Pantau anggota, ubah tahap sesi', 'enabled' => true,
                           'done' => false, 'href' => route('control.show', $session)]
                        : ($session->isStatus(SessionStatus::Final)
                            ? ['icon' => 'trophy', 'tone' => '', 'title' => 'Hasil Konsensus', 'text' => 'Urutan prioritas dan kesimpulan', 'enabled' => true,
                               'done' => false, 'href' => route('consensus.show', $session)]
                            : ['icon' => 'award', 'tone' => '', 'title' => 'Voting', 'text' => 'Konfirmasi suara Anda',
                               'enabled' => $session->isStatus(SessionStatus::Voting), 'done' => false, 'href' => route('vote.show', $session)]),
                ];
            @endphp
            <div class="row g-3">
                @foreach ($actions as $action)
                    <div class="col-sm-6 col-xl-3">
                        <a href="{{ $action['href'] ?? '#' }}" @class(['quick', 'is-done' => $action['done'], 'is-disabled' => ! $action['enabled'] || ! isset($action['href'])]) aria-disabled="{{ $action['enabled'] ? 'false' : 'true' }}">
                            <span class="icon-tile {{ $action['tone'] }}"><i class="bi bi-{{ $action['icon'] }}"></i></span>
                            <div>
                                <b>{{ $action['title'] }}</b>
                                <small>{{ $action['text'] }}</small>
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
                    <h3>Notifikasi</h3>
                    @forelse ($notifications as $notification)
                        <div class="d-flex gap-2 small py-1 border-bottom border-light-subtle">
                            <span class="text-muted-2 text-nowrap">{{ $notification->created_at->format('d/m H:i') }}</span>
                            <span>{{ $notification->data['message'] ?? '' }}</span>
                        </div>
                    @empty
                        <div class="small text-muted-2">Belum ada notifikasi.</div>
                    @endforelse
                </div>
            </div>
        </div>

    @endif
</x-layouts.app>
