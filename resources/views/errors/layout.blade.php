<x-layouts.guest :title="$title">
    <div class="container-xxl px-4 py-5">
        <div class="card-soft text-center mx-auto py-5" style="max-width: 560px">
            <span class="icon-tile {{ $tone ?? 'blue' }} mb-3" style="width: 56px; height: 56px; font-size: 1.6rem">
                <i class="bi bi-{{ $icon }}"></i>
            </span>
            <div class="eyebrow text-teal">Kode {{ $code }}</div>
            <h1 class="fs-3 fw-800 text-navy my-2">{{ $title }}</h1>
            <p class="text-muted-2">{{ $message }}</p>
            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="btn btn-grad mt-2">
                {{ auth()->check() ? 'Kembali ke Dashboard' : 'Masuk' }}
            </a>
        </div>
    </div>
</x-layouts.guest>
