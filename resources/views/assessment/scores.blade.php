<x-layouts.app title="Skor Kasus" active="penilaian">
    <x-slot:header>
        <x-page-header crumb="Sesi {{ $session->title }} › Penilaian Saya" :status="$session->status">
            Skor Kasus
        </x-page-header>
    </x-slot:header>

    @include('assessment.partials.frame', ['tab' => 'scores'])

    @if ($assessment && $case)
        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
            <div class="case-tabs">
                @foreach ($cases as $item)
                    <a href="{{ route('assessment.scores', [$session, 'case' => $item->kode_kasus]) }}"
                       @class(['active' => $item->is($case), 'rf' => $item->hasRedFlag()])
                       title="{{ $item->hasRedFlag() ? 'Kasus dengan red flag' : '' }}">
                        {{ $item->kode_kasus }}
                        @if (in_array($item->id, $completeCases, true)) <i class="bi bi-check-lg"></i> @endif
                    </a>
                @endforeach
            </div>
            <span class="small text-muted-2">Terisi <b class="text-navy">{{ $scoresFilled }}</b> dari {{ $scoresTarget }} skor</span>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card-soft">
                    <div class="case-head">
                        <span class="case-code">{{ $case->kode_kasus }}</span>
                        <div>
                            <b class="text-navy">Kasus {{ $case->kode_kasus }}</b>
                            <div class="small text-muted-2">{{ $case->tipe_dm }} · {{ $case->lama_dm }} tahun · DATA SIMULASI</div>
                        </div>
                    </div>
                    @if ($case->hasRedFlag())
                        <div class="alert-redflag small mb-3 py-2">
                            <i class="bi bi-exclamation-triangle"></i>
                            {{ $case->redFlags->map(fn ($f) => $f->code->value.' '.$f->code->label())->implode('; ') }}
                        </div>
                    @endif
                    @include('cases.partials.clinical', ['case' => $case])
                </div>
            </div>

            <div class="col-lg-8">
                <form method="POST" action="{{ route('assessment.scores.update', $session) }}" class="card-soft" id="scoreForm">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="case_id" value="{{ $case->id }}">
                    <h3>Skor 1–5 <span class="small text-muted-2 fw-normal">(makin tinggi = makin berat = makin prioritas)</span></h3>
                    @foreach ($criteria as $criterion)
                        @php $selected = (int) old("scores.{$criterion->id}", $current[$criterion->id] ?? 0); @endphp
                        <div class="crit-row">
                            <b title="{{ $criterion->definition }}">{{ $criterion->code }} {{ $criterion->name }}</b>
                            <div class="seg" role="radiogroup" aria-label="Skor {{ $criterion->code }}">
                                @for ($score = 1; $score <= 5; $score++)
                                    @php $id = "s{$criterion->id}_{$score}"; @endphp
                                    <input type="radio" id="{{ $id }}" name="scores[{{ $criterion->id }}]" value="{{ $score }}"
                                           data-hint="{{ $criterion->rubricFor($score) }}" @checked($selected === $score) @disabled(! $editable)>
                                    <label for="{{ $id }}" title="{{ $criterion->rubricFor($score) }}">{{ $score }}</label>
                                @endfor
                            </div>
                            <div class="hint" data-hint-for="{{ $criterion->id }}">
                                {{ $selected ? $criterion->rubricFor($selected) : 'Pilih skor; petunjuk rubrik akan tampil di sini.' }}
                            </div>
                        </div>
                    @endforeach

                    @if ($editable)
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <button class="btn btn-outline-soft" name="next" value="">Simpan Draf</button>
                            @if ($nextCase)
                                <button class="btn btn-grad" name="next" value="{{ $nextCase->kode_kasus }}">
                                    Simpan &amp; Kasus {{ $nextCase->kode_kasus }} <i class="bi bi-arrow-right ms-1"></i>
                                </button>
                            @else
                                <a class="btn btn-grad" href="{{ route('assessment.result', $session) }}">Ke Ringkasan <i class="bi bi-arrow-right ms-1"></i></a>
                            @endif
                        </div>
                    @endif
                </form>
            </div>
        </div>

        <x-slot:scripts>
            <script>
                // Petunjuk rubrik untuk skor yang dipilih (Bab 3.3, komponen rubric-hint).
                document.getElementById('scoreForm').addEventListener('change', function (e) {
                    if (e.target.matches('input[type=radio]')) {
                        const id = e.target.name.match(/\d+/)[0];
                        this.querySelector('[data-hint-for="' + id + '"]').textContent = e.target.dataset.hint;
                    }
                });
            </script>
        </x-slot:scripts>
    @endif
</x-layouts.app>
