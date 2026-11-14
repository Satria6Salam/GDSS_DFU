<x-layouts.app title="Kasus {{ $case->kode_kasus }}" active="kasus">
    <x-slot:header>
        <x-page-header crumb="Sesi {{ $session->title }} › Kasus › {{ $case->kode_kasus }}" :status="$session->status">
            Kasus {{ $case->kode_kasus }}
        </x-page-header>
    </x-slot:header>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card-soft">
                <div class="case-head">
                    <span class="case-code">{{ $case->kode_kasus }}</span>
                    <div>
                        <b class="text-navy">Data klinis kasus {{ $case->kode_kasus }}</b>
                        <div class="small text-muted-2">{{ $case->tipe_dm }} · {{ $case->lama_dm }} tahun · DATA SIMULASI</div>
                    </div>
                    @if ($editable)
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('cases.edit', [$session, $case]) }}" class="btn btn-sm btn-outline-soft"><i class="bi bi-pencil me-1"></i>Ubah</a>
                            <button class="btn btn-sm btn-outline-soft text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="bi bi-trash me-1"></i>Hapus
                            </button>
                        </div>
                    @endif
                </div>
                @include('cases.partials.clinical', ['case' => $case])
            </div>
        </div>

        <div class="col-lg-5 d-flex flex-column gap-4">
            @if ($case->hasRedFlag())
                <div class="emergency">
                    <span class="icon-tile"><i class="bi bi-exclamation-triangle"></i></span>
                    <h3>Eskalasi Segera</h3>
                    @foreach ($case->redFlags as $flag)
                        <p class="mb-2">
                            <b>{{ $flag->code->value }}</b> {{ $flag->code->label() }}
                            <span class="small">· {{ $flag->source->value === 'MANUAL' ? 'manual oleh '.($flag->creator?->name ?? 'ketua') : 'otomatis' }}</span>
                            @if ($flag->note)<br><span class="small">“{{ $flag->note }}”</span>@endif
                        </p>
                    @endforeach
                    <p class="mb-0 small">Jangan menunggu voting; ikuti protokol klinis.</p>
                </div>
            @else
                <div class="card-soft">
                    <h3>Red Flag</h3>
                    <p class="text-muted-2 small mb-0">Tidak ada tanda bahaya pada data kasus ini (aturan RF1–RF5, Bab 3.5).</p>
                </div>
            @endif

            @if ($canFlag)
                <form method="POST" action="{{ route('cases.red-flags.store', [$session, $case]) }}" class="card-soft">
                    @csrf
                    <h3>Tambah Red Flag Manual</h3>
                    <p class="small text-muted-2">Untuk kondisi yang tidak terbaca otomatis, mis. RF3 dugaan infeksi nekrotikans.
                        Red flag tidak dapat dihapus oleh skor atau voting.</p>
                    <label for="code" class="form-label">Kode</label>
                    <select id="code" name="code" class="form-select mb-2">
                        @foreach ($flagCodes as $code)
                            <option value="{{ $code->value }}" @selected(old('code', 'RF3') === $code->value)
                                    @disabled($case->redFlags->contains(fn ($f) => $f->code === $code))>
                                {{ $code->value }} — {{ $code->label() }}
                            </option>
                        @endforeach
                    </select>
                    <label for="note" class="form-label">Catatan (wajib)</label>
                    <textarea id="note" name="note" class="form-control mb-1" rows="2">{{ old('note') }}</textarea>
                    @error('note') <div class="text-danger small mb-1">{{ $message }}</div> @enderror
                    <button class="btn btn-grad mt-2"><i class="bi bi-flag me-1"></i> Tambah Red Flag</button>
                </form>
            @endif
        </div>
    </div>

    @if ($editable)
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('cases.destroy', [$session, $case]) }}" class="modal-content rounded-4 border-0">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header border-0"><h5 class="modal-title fw-800 text-navy" id="deleteTitle">Hapus Kasus {{ $case->kode_kasus }}?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
                    <div class="modal-body pt-0 text-muted-2">Kasus dan red flag-nya akan dihapus dari sesi ini.</div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-outline-soft" data-bs-dismiss="modal">Batal</button>
                        <button class="btn btn-grad">Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-layouts.app>
