<x-layouts.app title="Kasus" active="kasus">
    <x-slot:header>
        <x-page-header crumb="Sesi {{ $session->title }} › Kasus" :status="$session->status">
            Daftar Kasus Simulasi
        </x-page-header>
    </x-slot:header>

    @if ($hidden)
        <div class="card-soft text-center py-5">
            <span class="icon-tile blue mb-3"><i class="bi bi-hourglass-split"></i></span>
            <h3>Kasus belum dibuka</h3>
            <p class="text-muted-2 mb-0">Data kasus dapat dilihat setelah ketua membuka tahap penilaian.</p>
        </div>
    @else
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="sim-label">DATA SIMULASI</span>
            <span class="small text-muted-2">{{ $cases->count() }} kasus · dataset {{ $session->dataset_version ?? '–' }}</span>
            @if ($editable)
                <div class="ms-auto d-flex gap-2">
                    @if ($cases->isEmpty())
                        <form method="POST" action="{{ route('cases.import', $session) }}">
                            @csrf
                            <button class="btn btn-outline-soft"><i class="bi bi-download me-1"></i> Impor {{ config('gdss.dataset_version') }}</button>
                        </form>
                    @endif
                    <a href="{{ route('cases.create', $session) }}" class="btn btn-grad"><i class="bi bi-plus-lg me-1"></i> Tambah Kasus</a>
                </div>
            @elseif (auth()->user()->isLeader())
                <span class="ms-auto small text-muted-2"><i class="bi bi-lock me-1"></i>Kasus terkunci sejak tahap penilaian dimulai.</span>
            @endif
        </div>

        @if ($cases->isEmpty())
            <div class="card-soft text-center py-5">
                <img src="{{ asset('images/kasus-glukometer.jpg') }}" alt="" class="empty-photo">
                <h3>Belum ada kasus</h3>
                <p class="text-muted-2 mb-0">Tambahkan kasus satu per satu atau impor dataset {{ config('gdss.dataset_version') }}.</p>
            </div>
        @else
            <div class="card-soft d-flex flex-wrap gap-3 align-items-center mb-3 py-3">
                <img src="{{ asset('images/kasus-glukometer.jpg') }}" alt="" style="width: 120px; height: 72px; object-fit: cover; border-radius: 6px">
                <div class="small text-muted-2" style="max-width: 760px">
                    Kasus pasien <b class="text-navy">fiktif</b> untuk demonstrasi dan pengujian (Bab 4). Skor C1–C6 diisi masing-masing decision
                    maker berdasarkan data klinis dan rubrik. Kasus bertanda merah memiliki tanda bahaya dan dievaluasi segera, terlepas dari skor.
                </div>
            </div>
        @endif
        @if ($cases->isNotEmpty())
            <div class="card-soft">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr><th>Kasus</th><th>Usia / JK</th><th>Lokasi luka</th><th>Luas · kedalaman</th><th>ABI</th><th>Progres</th><th>Red flag</th><th></th></tr>
                        </thead>
                        <tbody>
                        @foreach ($cases as $case)
                            <tr>
                                <td class="fw-bold text-navy">{{ $case->kode_kasus }}</td>
                                <td>{{ $case->usia }} / {{ $case->jenis_kelamin }}</td>
                                <td>{{ $case->lokasi_luka }}</td>
                                <td>{{ number_format($case->luas_luka, 1, ',', '.') }} cm² · {{ $case->kedalaman }}</td>
                                <td>{{ number_format($case->abi, 2, ',', '.') }}</td>
                                <td class="small">{{ $case->progres_luka->label() }}</td>
                                <td>
                                    @forelse ($case->redFlags as $flag)
                                        <span class="badge-status badge-rf" title="{{ $flag->code->label() }} ({{ strtolower($flag->source->value) }})">{{ $flag->code->value }}</span>
                                    @empty
                                        <span class="text-muted-2">–</span>
                                    @endforelse
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('cases.show', [$session, $case]) }}" class="btn btn-sm btn-outline-soft">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</x-layouts.app>
