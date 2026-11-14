@props(['crumb' => null, 'status' => null])
{{-- Judul halaman polos + breadcrumb + stepper tahap sesi. --}}
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
