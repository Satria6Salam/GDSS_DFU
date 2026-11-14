@props(['title' => null])
<x-layouts.base :title="$title">
    <nav class="app-nav d-flex align-items-center">
        <div class="container-xxl px-4 d-flex align-items-center gap-3">
            <x-brand />
            <span class="ms-auto sim-label">DATA SIMULASI</span>
            <span class="user-chip d-none d-sm-inline-flex px-3"><i class="bi bi-clock"></i> Prototipe Akademik {{ now()->year }}</span>
        </div>
    </nav>
    <main>{{ $slot }}</main>
</x-layouts.base>
