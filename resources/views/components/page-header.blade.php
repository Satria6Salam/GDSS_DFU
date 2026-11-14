@props(['crumb' => null, 'status' => null])
{{-- Judul halaman: gunakan <span class="acc">…</span> untuk kata aksen (Bab 10.1). --}}
<div class="page-head">
    <div class="container-xxl px-4">
        @if ($crumb)
            <div class="crumb">{{ $crumb }}</div>
        @endif
        <h1>{{ $slot }}</h1>
        @if ($status)
            <x-status-stepper :status="$status" />
        @endif
    </div>
</div>
