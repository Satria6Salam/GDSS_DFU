@php use App\Enums\SessionStatus; @endphp
<x-layouts.app title="Riwayat Sesi" active="riwayat">
    <x-slot:header>
        <x-page-header crumb="Riwayat keputusan">
            Riwayat Sesi Keputusan
        </x-page-header>
    </x-slot:header>

    @if ($canCreate)
        <form method="POST" action="{{ route('sessions.store') }}" class="card-soft mb-4 d-flex flex-wrap gap-2 align-items-end">
            @csrf
            <div class="flex-grow-1" style="min-width: 240px">
                <label for="title" class="form-label">Buat sesi baru</label>
                <input id="title" name="title" class="form-control" placeholder="Judul sesi, mis. Evaluasi Desember" value="{{ old('title') }}">
                @error('title') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <button class="btn btn-grad"><i class="bi bi-plus-lg me-1"></i> Buat Sesi</button>
        </form>
    @elseif (auth()->user()->isLeader())
        <p class="small text-muted-2"><i class="bi bi-info-circle me-1"></i>Sesi baru dapat dibuat setelah sesi yang berjalan difinalisasi.</p>
    @endif

    @forelse ($sessions as $session)
        @php $final = $session->consensusResults->first(); @endphp
        <div class="card-soft mb-3">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="icon-tile {{ $session->isStatus(SessionStatus::Final) ? '' : 'blue' }}">
                    <i class="bi bi-{{ $session->isStatus(SessionStatus::Final) ? 'check2-circle' : 'hourglass-split' }}"></i>
                </span>
                <div class="flex-grow-1">
                    <b class="text-navy">{{ $session->title }}</b>
                    <div class="small text-muted-2">
                        {{ $session->cases_count }} kasus · dataset {{ $session->dataset_version ?? '–' }} ·
                        dibuat {{ $session->created_at->format('d-m-Y') }} oleh {{ $session->creator->name }}
                        @if ($session->finalized_at) · difinalisasi {{ $session->finalized_at->format('d-m-Y H:i') }} @endif
                    </div>
                </div>
                <x-status-badge :status="$session->status" />
                <div class="d-flex gap-2">
                    @if (auth()->user()->isLeader())
                        <a href="{{ route('control.show', $session) }}" class="btn btn-sm btn-outline-soft">Panel</a>
                    @endif
                    @if ($session->isStatus(SessionStatus::Final))
                        <a href="{{ route('consensus.show', $session) }}" class="btn btn-sm btn-grad">Lihat Hasil</a>
                    @else
                        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-soft">Dashboard</a>
                    @endif
                </div>
            </div>
            @if ($final)
                <div class="small mt-3 text-muted-2">
                    Urutan prioritas:
                    @foreach ($final->items as $item)
                        <b class="text-navy">{{ $item->patientCase->kode_kasus }}</b>{{ $loop->last ? '' : ' →' }}
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        <div class="card-soft text-center py-5 text-muted-2">Belum ada sesi keputusan.</div>
    @endforelse
</x-layouts.app>
