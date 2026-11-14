@php
    use App\Enums\AssessmentStatus;
    use App\Enums\ConsensusStatus;
    use App\Enums\SessionStatus;
    use App\Enums\TieBreakRule;
    $status = $session->status;
    $tiedItems = $consensus?->items->where('tie_break_rule', TieBreakRule::Musyawarah) ?? collect();
@endphp
<x-layouts.app title="Panel Ketua" active="panel">
    <x-slot:header>
        <x-page-header crumb="Sesi {{ $session->title }} › Panel Ketua" :status="$status">
            Panel Ketua Tim
        </x-page-header>
    </x-slot:header>

    <x-red-flag-alert :cases="$redFlagCases" class="mb-4" />

    <div class="row g-4">
        {{-- Kolom kiri: status anggota + aksi tahap --}}
        <div class="col-lg-7">
            <div class="card-soft mb-4">
                <div class="d-flex align-items-center mb-2">
                    <h3 class="mb-0 flex-grow-1">Status Anggota</h3>
                    <span class="text-muted-2 small">{{ $status->label() }}</span>
                </div>
                @foreach ($members as $member)
                    <div class="member">
                        <span @class(['avatar', 'teal' => $member['user']->role->value === 'perawat', 'gray' => $member['user']->role->value === 'apoteker'])>
                            {{ $member['user']->initials() }}
                        </span>
                        <div class="who">
                            <b>{{ $member['user']->name }}</b>
                            <small>
                                {{ $member['user']->role->label() }}
                                @if ($member['assessment']?->submitted_at)
                                    · penilaian dikirim {{ $member['assessment']->submitted_at->format('d-m-Y H:i') }}
                                @endif
                            </small>
                        </div>
                        @if (in_array($status, [SessionStatus::Voting, SessionStatus::MenungguFinalisasi, SessionStatus::Final], true))
                            <span class="me-2"><x-status-badge :status="$member['vote'] ? 'TERKIRIM' : 'BELUM'" title="Status suara" /></span>
                        @endif
                        <x-status-badge :status="$member['status']" title="Status penilaian" />
                    </div>
                @endforeach
            </div>

            <div class="card-soft">
                <h3>Langkah Berikutnya</h3>

                @if ($status === SessionStatus::Persiapan)
                    <p class="text-muted-2">Sesi berisi <b>{{ $caseCount }} kasus</b>. Setelah penilaian dibuka, kasus dan kriteria
                        tidak dapat diubah lagi (Bab 4.3).</p>
                    <form method="POST" action="{{ route('sessions.open-assessment', $session) }}">
                        @csrf
                        <button class="btn btn-grad"><i class="bi bi-unlock me-1"></i> Buka Penilaian</button>
                    </form>

                @elseif ($status === SessionStatus::Penilaian)
                    <p class="text-muted-2 mb-3">Voting dapat dimulai setelah ketiga anggota mengirim penilaian final.
                        Saat voting dimulai, semua penilaian terkunci.</p>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <form method="POST" action="{{ route('sessions.start-voting', $session) }}">
                            @csrf
                            <button class="btn btn-grad" @disabled($waiting->isNotEmpty())>
                                <i class="bi bi-{{ $waiting->isEmpty() ? 'play-circle' : 'lock' }} me-1"></i> Mulai Voting
                            </button>
                        </form>
                        @if ($waiting->isNotEmpty())
                            <span class="text-muted-2 small">Menunggu: {{ $waiting->map(fn ($m) => $m['user']->name)->implode(', ') }}</span>
                        @endif
                    </div>

                @elseif ($status === SessionStatus::Voting)
                    <p class="text-muted-2">Voting sedang berlangsung. Hasil Borda dihitung otomatis saat suara ketiga masuk.</p>
                    <button class="btn btn-outline-soft" data-bs-toggle="modal" data-bs-target="#reopenModal">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Buka Ulang Sesi
                    </button>

                @elseif ($status === SessionStatus::MenungguFinalisasi)
                    @if ($consensus?->status === ConsensusStatus::PerluMusyawarah)
                        <div class="alert alert-warning rounded-4 border-0 small">
                            Ada kasus yang seri pada semua tingkat pemecah seri. Tetapkan urutannya melalui musyawarah sebelum finalisasi.
                        </div>
                        <form method="POST" action="{{ route('sessions.deliberate', $session) }}" class="mb-3">
                            @csrf
                            @foreach ($tiedItems as $item)
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <b class="text-navy" style="width: 60px">{{ $item->patientCase->kode_kasus }}</b>
                                    <select name="positions[{{ $item->case_id }}]" class="form-select form-select-sm" style="max-width: 180px">
                                        @foreach ($tiedItems->pluck('final_rank')->sort() as $rank)
                                            <option value="{{ $rank }}" @selected(old("positions.{$item->case_id}", $item->final_rank) == $rank)>Posisi {{ $rank }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                            <label class="form-label mt-2" for="note">Alasan musyawarah</label>
                            <textarea id="note" name="note" class="form-control mb-2" rows="2" required>{{ old('note') }}</textarea>
                            <button class="btn btn-grad"><i class="bi bi-check2 me-1"></i> Tetapkan Urutan</button>
                        </form>
                    @else
                        <p class="text-muted-2">Semua suara sudah masuk. Tinjau urutan di samping, lalu finalisasi.
                            Hasil yang difinalisasi tidak dapat diubah.</p>
                    @endif
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-grad" data-bs-toggle="modal" data-bs-target="#finalizeModal"
                                @disabled($consensus?->status === ConsensusStatus::PerluMusyawarah)>
                            <i class="bi bi-patch-check me-1"></i> Finalisasi &amp; Publikasikan
                        </button>
                        <button class="btn btn-outline-soft" data-bs-toggle="modal" data-bs-target="#reopenModal">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Buka Ulang Sesi
                        </button>
                    </div>

                @else
                    <p class="text-muted-2">Sesi telah difinalisasi pada {{ $session->finalized_at?->format('d-m-Y H:i') }}.
                        Untuk keputusan baru, buat sesi baru.</p>
                    <form method="POST" action="{{ route('sessions.store') }}" class="d-flex gap-2" style="max-width: 520px">
                        @csrf
                        <input name="title" class="form-control" placeholder="Judul sesi baru" value="{{ old('title') }}" required>
                        <button class="btn btn-grad text-nowrap"><i class="bi bi-plus-lg me-1"></i> Buat Sesi</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Kolom kanan: ringkasan hasil + aktivitas --}}
        <div class="col-lg-5 d-flex flex-column gap-4">
            @if ($consensus)
                <div class="card-soft">
                    <h3>Urutan Konsensus (Borda)</h3>
                    <table class="table table-sm align-middle mb-2">
                        <thead><tr><th>#</th><th>Kasus</th><th>Poin</th><th>V kelompok</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($consensus->items as $item)
                            <tr>
                                <td class="fw-bold text-navy">{{ $item->final_rank }}</td>
                                <td>
                                    {{ $item->patientCase->kode_kasus }}
                                    @if ($item->patientCase->redFlags->isNotEmpty())
                                        <span class="badge-status badge-rf ms-1">RF</span>
                                    @endif
                                </td>
                                <td class="fw-bold">{{ $item->borda_points }}</td>
                                <td>{{ number_format($item->group_v, 4, ',', '.') }}</td>
                                <td class="small text-muted-2">
                                    {{ $item->tie_break_rule ? str_replace('_', ' ', $item->tie_break_rule->value) : '' }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <div class="small text-muted-2">Rincian suara tiap anggota tampil setelah hasil difinalisasi (D13).</div>
                </div>
            @endif

            <div class="card-soft">
                <h3><i class="bi bi-clock-history me-1"></i> Aktivitas Terakhir</h3>
                @forelse ($logs as $log)
                    <div class="small mb-2">
                        <span class="text-navy fw-bold">{{ str_replace('_', ' ', $log->action) }}</span>
                        <span class="text-muted-2">· {{ $log->user?->name ?? 'Sistem' }} · {{ $log->created_at->format('d-m H:i') }}</span>
                        @if ($log->note)
                            <div class="text-muted-2">“{{ $log->note }}”</div>
                        @endif
                    </div>
                @empty
                    <div class="small text-muted-2">Belum ada aktivitas.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Dialog konfirmasi (Bab 10.6) --}}
    <div class="modal fade" id="reopenModal" tabindex="-1" aria-labelledby="reopenTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('sessions.reopen', $session) }}" class="modal-content rounded-4 border-0">
                @csrf
                <div class="modal-header border-0"><h5 class="modal-title fw-800 text-navy" id="reopenTitle">Buka Ulang Sesi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
                <div class="modal-body pt-0">
                    <p class="text-muted-2 small">Membuka ulang akan membatalkan semua suara dan mengembalikan sesi ke tahap Penilaian.</p>
                    <label class="form-label" for="reason">Alasan (wajib)</label>
                    <textarea id="reason" name="reason" class="form-control" rows="3" required>{{ old('reason') }}</textarea>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-soft" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-grad">Buka Ulang</button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="finalizeModal" tabindex="-1" aria-labelledby="finalizeTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('sessions.finalize', $session) }}" class="modal-content rounded-4 border-0">
                @csrf
                <div class="modal-header border-0"><h5 class="modal-title fw-800 text-navy" id="finalizeTitle">Finalisasi Hasil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
                <div class="modal-body pt-0 text-muted-2">Hasil yang difinalisasi tidak dapat diubah. Lanjutkan?</div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-soft" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-grad">Finalisasi &amp; Publikasikan</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
