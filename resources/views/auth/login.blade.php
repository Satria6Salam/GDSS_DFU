<x-layouts.base title="Masuk">
    <main class="auth-wrap">
        <section class="auth-photo" style="background-image: url('{{ asset('images/login-tim-dokter.jpg') }}')">
            <x-brand href="#" />

            <div>
                <h2>Prioritas evaluasi kasus kaki diabetik, diputuskan bersama.</h2>
                <p>Dokter, perawat luka, dan apoteker klinis menilai kasus secara terpisah. Sistem menggabungkan penilaian
                    dengan AHP, TOPSIS, dan Borda Count menjadi satu urutan prioritas yang dapat ditelusuri.</p>
                <div class="role-strip">
                    <div><img src="{{ asset('images/peran-dokter.jpg') }}" alt="" class="avatar-photo">Dokter (ketua tim)</div>
                    <div><img src="{{ asset('images/peran-perawat.jpg') }}" alt="" class="avatar-photo">Perawat luka</div>
                    <div><img src="{{ asset('images/peran-apoteker.jpg') }}" alt="" class="avatar-photo">Apoteker klinis</div>
                </div>
                <div class="photo-credit">Foto: Vitaly Gariev, Nappy, National Cancer Institute / Unsplash — ilustratif.</div>
            </div>
        </section>

        <section class="auth-form">
            <div class="inner">
                <span class="sim-label">DATA SIMULASI</span>
                <h1 class="mt-3 mb-1">Masuk ke akun Anda</h1>
                <p class="text-muted-2 small mb-4">Gunakan akun decision maker yang diberikan ketua tim.</p>

                @if (session('status'))
                    <div class="alert alert-info py-2 small d-flex gap-2 align-items-center" role="status">
                        <i class="bi bi-info-circle"></i>{{ session('status') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger py-2 small d-flex gap-2 align-items-center" role="alert">
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
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-icon">
                            <i class="bi bi-lock"></i>
                            <input id="password" name="password" type="password" class="form-control"
                                   autocomplete="current-password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-grad w-100 py-2">Masuk</button>
                </form>

                <div class="auth-meta">
                    {{ $stats['users'] }} decision maker · {{ $stats['criteria'] }} kriteria klinis · {{ $stats['cases'] }} kasus simulasi<br>
                    Lupa password? Hubungi ketua tim. Akun dibuat oleh sistem, tanpa pendaftaran mandiri.
                </div>
            </div>
        </section>
    </main>
</x-layouts.base>
