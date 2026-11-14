@php
    use App\Enums\SessionStatus;
    $fmt = fn ($v) => number_format($v, 4, ',', '.');
@endphp
<x-layouts.app title="Voting" active="voting">
    <x-slot:header>
        <x-page-header crumb="Sesi {{ $session->title }} › Voting" :status="$session->status">
            Konfirmasi Suara Anda
        </x-page-header>
    </x-slot:header>

    <x-red-flag-alert :cases="$redFlagCases" class="mb-4" />

    @if (! $isOpen && ! $vote)
        <div class="card-soft text-center py-5">
            <span class="icon-tile blue mb-3"><i class="bi bi-{{ $session->status->step() < 3 ? 'hourglass-split' : 'check2-all' }}"></i></span>
            @if ($session->status->step() < 3)
                <h3>Voting belum dibuka</h3>
                <p class="text-muted-2 mb-0">Ketua akan memulai voting setelah ketiga anggota mengirim penilaian final.</p>
            @else
                <h3>Voting sudah ditutup</h3>
                <p class="text-muted-2 mb-3">Lihat urutan konsensus pada halaman Hasil.</p>
                <a class="btn btn-grad" href="{{ route('consensus.show', $session) }}">Lihat Hasil</a>
            @endif
        </div>
    @else
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card-soft">
                    <h3>Peringkat TOPSIS Anda <span class="small text-muted-2 fw-normal">→ menjadi suara Borda</span></h3>
                    <div class="d-none d-md-grid small fw-bold text-muted-2 px-3 pb-2"
                         style="grid-template-columns: 40px 110px 1fr 70px 70px; gap: 12px">
                        <span>#</span><span>Kasus</span><span>Kedekatan ke solusi ideal</span><span>Nilai V</span><span>Poin</span>
                    </div>
                    @foreach ($ranking as $row)
                        <div @class(['rank-row', 'top' => $row->rank <= 3]) style="grid-template-columns: 40px 110px 1fr 70px 70px">
                            <span class="rank-no">{{ $row->rank }}</span>
                            <b class="text-navy">
                                {{ $row->patientCase->kode_kasus }}
                                @if ($row->patientCase->hasRedFlag())
                                    <span class="badge-status badge-rf ms-1">{{ $row->patientCase->redFlags->map(fn ($f) => $f->code->value)->implode(' · ') }}</span>
                                @endif
                            </b>
                            <div class="track"><i style="width: {{ $row->v * 100 }}%"></i></div>
                            <span class="small">{{ $fmt($row->v) }}</span>
                            <span class="badge-status badge-final text-center">{{ $caseCount - $row->rank }} poin</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card-soft">
                    <span class="icon-tile blue mb-2"><i class="bi bi-award"></i></span>
                    @if ($vote)
                        <h3>Suara Terkirim</h3>
                        <p class="text-muted-2">Suara Anda tercatat pada {{ $vote->submitted_at->format('d-m-Y H:i') }} dan tidak dapat diubah.
                            @if ($session->isStatus(SessionStatus::Voting))
                                Hasil Borda dihitung otomatis setelah semua anggota mengirim suara.
                            @endif
                        </p>
                        <x-status-badge status="TERKIRIM" />
                    @else
                        <h3>Kirim Suara</h3>
                        <p class="text-muted-2">Urutan dihitung dari penilaian final Anda dan tidak dapat diubah (D04).
                            Suara hanya dapat dikirim sekali.</p>
                        <form method="POST" action="{{ route('vote.store', $session) }}">
                            @csrf
                            <label class="d-flex gap-2 align-items-start p-3 rounded-3 mb-3" style="background: #F6F9FD">
                                <input type="checkbox" name="confirm" value="1" class="form-check-input mt-1" @checked(old('confirm'))>
                                <span>Saya telah meninjau urutan ini dan mengirimkannya sebagai suara saya.</span>
                            </label>
                            @error('confirm')
                                <div class="text-danger small mb-2">{{ $message }}</div>
                            @enderror
                            <button class="btn btn-grad w-100" @disabled($ranking->isEmpty())>Kirim Suara <i class="bi bi-arrow-right ms-1"></i></button>
                        </form>
                    @endif
                    <div class="small text-muted-2 mt-3 d-flex gap-2 align-items-center">
                        <i class="bi bi-lock"></i> Penilaian Anda <x-status-badge :status="$assessment?->status ?? 'BELUM'" />
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-layouts.app>
