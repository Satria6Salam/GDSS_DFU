<x-layouts.guest title="Masuk">
    <section class="login-hero">
        <div class="container-xxl px-4 py-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-6">
                    <div class="eyebrow text-teal">Group Decision Support System</div>
                    <h1 class="my-3">Keputusan Bersama.<br>Prioritas <span class="acc">Lebih Jelas.</span></h1>
                    <p class="text-muted-2 fs-6 mb-4" style="max-width: 470px; line-height: 1.6">
                        Dokter, perawat luka, dan apoteker klinis menilai kasus Diabetic Foot Ulcer secara terpisah,
                        lalu sistem menggabungkannya dengan AHP, TOPSIS, dan Borda Count menjadi urutan prioritas evaluasi.
                    </p>

                    <div class="card-soft p-4" style="max-width: 430px">
                        <h3 class="mb-1">Masuk ke akun Anda</h3>
                        <p class="text-muted-2 small mb-3">Gunakan akun decision maker yang diberikan tim.</p>

                        @if (session('status'))
                            <div class="alert alert-info rounded-3 py-2 small d-flex gap-2 align-items-center" role="status">
                                <i class="bi bi-info-circle"></i>{{ session('status') }}
                            </div>
                        @endif
                        @if ($errors->any())
                            <div class="alert alert-danger rounded-3 py-2 small d-flex gap-2 align-items-center" role="alert">
                                <i class="bi bi-exclamation-circle"></i>{{ $errors->first() }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login.attempt') }}" novalidate>
                            @csrf
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-icon">
                                    <i class="bi bi-person"></i>
                                    <input id="username" name="username" type="text" value="{{ old('username') }}"
                                           class="form-control" autocomplete="username" autofocus required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-icon">
                                    <i class="bi bi-lock"></i>
                                    <input id="password" name="password" type="password" class="form-control"
                                           autocomplete="current-password" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-grad w-100">Masuk <i class="bi bi-arrow-right ms-1"></i></button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-6 d-none d-lg-block">
                    <div class="login-illus">
                        @include('auth.partials.illustration')
                        <div class="float-card" style="right: 0; top: 18px">
                            <span class="icon-tile"><i class="bi bi-people"></i></span>
                            <div><b>{{ $stats['users'] }} Decision Maker</b><small>Dokter · Perawat · Apoteker</small></div>
                        </div>
                        <div class="float-card" style="right: -10px; top: 190px">
                            <span class="icon-tile blue"><i class="bi bi-layers"></i></span>
                            <div><b>AHP · TOPSIS · Borda</b><small>Bobot, peringkat, konsensus</small></div>
                        </div>
                        <div class="float-card" style="left: -10px; bottom: 6px">
                            <span class="icon-tile red"><i class="bi bi-exclamation-triangle"></i></span>
                            <div><b>Red Flag Otomatis</b><small>Eskalasi tidak menunggu skor</small></div>
                        </div>
                    </div>
                </div>
            </div>

            <x-stat-strip class="mt-5" :items="[
                ['icon' => 'people', 'value' => $stats['users'], 'label' => 'Decision Maker'],
                ['icon' => 'sliders', 'value' => $stats['criteria'], 'label' => 'Kriteria Klinis'],
                ['icon' => 'clipboard2-pulse', 'value' => $stats['cases'], 'label' => 'Kasus Simulasi'],
                ['icon' => 'layers', 'value' => 3, 'label' => 'Metode Keputusan'],
            ]" />
        </div>
    </section>
</x-layouts.guest>
