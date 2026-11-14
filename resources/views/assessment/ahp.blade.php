@php use App\Support\SaatyChoice; @endphp
<x-layouts.app title="Perbandingan Kriteria" active="penilaian">
    <x-slot:header>
        <x-page-header crumb="Sesi {{ $session->title }} › Penilaian Saya" :status="$session->status">
            Perbandingan Kriteria (AHP)
        </x-page-header>
    </x-slot:header>

    @include('assessment.partials.frame', ['tab' => 'ahp'])

    @if ($assessment)
        <form method="POST" action="{{ route('assessment.ahp.update', $session) }}" id="ahpForm">
            @csrf
            @method('PUT')
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card-soft">
                        <h3 class="mb-1">Seberapa penting kriteria kiri dibanding kanan?</h3>
                        <p class="text-muted-2 small mb-3">Pilih angka di sisi kriteria yang lebih penting: 3 sedikit lebih penting,
                            5 lebih penting, 7 sangat lebih penting, 9 mutlak lebih penting. Angka 1 di tengah = sama penting.</p>
                        <div class="pair-row pair-head" aria-hidden="true">
                            <span>Kriteria kiri</span>
                            <div class="scale-legend"><span>← kiri lebih penting</span><span>sama</span><span>kanan lebih penting →</span></div>
                            <span class="right">Kriteria kanan</span>
                        </div>
                        @foreach ($pairs as $pair)
                            <div class="pair-row">
                                <b title="{{ $pair['left']->definition }}">{{ $pair['left']->code }} {{ $pair['left']->name }}</b>
                                <div class="saaty" role="radiogroup" aria-label="{{ $pair['left']->code }} dibanding {{ $pair['right']->code }}">
                                    @foreach ($choices as $choice)
                                        @php $id = "p{$pair['key']}_{$choice}"; @endphp
                                        <input type="radio" id="{{ $id }}" name="pairs[{{ $pair['key'] }}]" value="{{ $choice }}"
                                               data-value="{{ SaatyChoice::toValue($choice) }}"
                                               @checked(old("pairs.{$pair['key']}", $current[$pair['key']] ?? null) === $choice) @disabled(! $editable)>
                                        <label for="{{ $id }}" @class(['mid' => $choice === '1'])
                                               title="{{ $choice === '1' ? 'Sama penting' : (str_starts_with($choice, 'L') ? $pair['left']->code : $pair['right']->code).' '.SaatyChoice::label($choice).'× lebih penting' }}">
                                            {{ SaatyChoice::label($choice) }}
                                        </label>
                                    @endforeach
                                </div>
                                <b class="right" title="{{ $pair['right']->definition }}">{{ $pair['right']->code }} {{ $pair['right']->name }}</b>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card-soft position-sticky" style="top: 16px">
                        <h3>Bobot Sementara</h3>
                        @foreach ($criteria as $criterion)
                            <div class="weight-row">
                                <b>{{ $criterion->code }}</b>
                                <div class="track"><i data-weight-bar="{{ $loop->index }}" style="width: 0"></i></div>
                                <span data-weight-text="{{ $loop->index }}">–</span>
                            </div>
                        @endforeach
                        <div class="small text-muted-2 mt-3 mb-2" id="lambdaText">λmax – · CI –</div>
                        <div class="cr-pill wait" id="crPill"><i class="bi bi-hourglass"></i><span>Lengkapi 15 pasang untuk menghitung CR</span></div>
                        <div class="small text-muted-2 mt-2">Terisi <b id="filledCount" class="text-teal">{{ $pairsFilled }}</b> / 15 pasang</div>

                        @if ($editable)
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <button class="btn btn-outline-soft" name="next" value="">Simpan Draf</button>
                                <button class="btn btn-grad" name="next" value="scores">Simpan &amp; Isi Skor <i class="bi bi-arrow-right ms-1"></i></button>
                            </div>
                            <p class="small text-muted-2 mt-2 mb-0">Perhitungan di halaman ini hanya pratinjau; nilai resmi dihitung server saat
                                Kirim Final (CR wajib ≤ 0,10).</p>
                        @endif
                    </div>
                </div>
            </div>
        </form>

        <x-slot:scripts>
            <script>
                // Pratinjau AHP di peramban (Bab 5.1.2): normalisasi kolom + rata-rata baris, RI(6) = 1,24.
                (function () {
                    const form = document.getElementById('ahpForm');
                    const ids = @json($criteria->pluck('id')->values());
                    const n = ids.length, RI = {3: .58, 4: .90, 5: 1.12, 6: 1.24, 7: 1.32};
                    const fmt = v => v.toFixed(4).replace('.', ',');

                    function update() {
                        const A = ids.map(() => ids.map(() => 1));
                        let filled = 0;
                        form.querySelectorAll('input[type=radio]:checked').forEach(input => {
                            const [i, j] = input.name.match(/\d+_\d+/)[0].split('_').map(Number);
                            const a = ids.indexOf(i), b = ids.indexOf(j), v = parseFloat(input.dataset.value);
                            A[a][b] = v; A[b][a] = 1 / v; filled++;
                        });
                        document.getElementById('filledCount').textContent = filled;

                        const pill = document.getElementById('crPill');
                        if (filled < n * (n - 1) / 2) {
                            pill.className = 'cr-pill wait';
                            pill.innerHTML = '<i class="bi bi-hourglass"></i><span>Lengkapi ' + (n * (n - 1) / 2) + ' pasang untuk menghitung CR</span>';
                            document.getElementById('lambdaText').textContent = 'λmax – · CI –';
                            ids.forEach((_, k) => {
                                form.querySelector('[data-weight-bar="' + k + '"]').style.width = '0';
                                form.querySelector('[data-weight-text="' + k + '"]').textContent = '–';
                            });
                            return;
                        }

                        const col = ids.map((_, j) => A.reduce((s, row) => s + row[j], 0));
                        const w = A.map(row => row.reduce((s, v, j) => s + v / col[j], 0) / n);
                        const lambda = A.reduce((s, row, i) => s + row.reduce((t, v, j) => t + v * w[j], 0) / w[i], 0) / n;
                        const ci = (lambda - n) / (n - 1), cr = ci / RI[n];
                        const max = Math.max(...w);

                        w.forEach((v, k) => {
                            form.querySelector('[data-weight-bar="' + k + '"]').style.width = (v / max * 100) + '%';
                            form.querySelector('[data-weight-text="' + k + '"]').textContent = fmt(v);
                        });
                        document.getElementById('lambdaText').textContent = 'λmax = ' + fmt(lambda) + ' · CI = ' + fmt(ci);
                        const ok = cr <= 0.10;
                        pill.className = 'cr-pill ' + (ok ? 'ok' : 'bad');
                        pill.innerHTML = '<i class="bi bi-' + (ok ? 'check-circle' : 'exclamation-triangle') + '"></i><span>CR = ' + fmt(cr)
                            + (ok ? ' · konsisten' : ' · melebihi 0,10, tinjau kembali') + '</span>';
                    }

                    form.addEventListener('change', update);
                    update();
                })();
            </script>
        </x-slot:scripts>
    @endif
</x-layouts.app>
