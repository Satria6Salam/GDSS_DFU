@props(['crumb' => null, 'status' => null])
{{-- Judul halaman polos + breadcrumb + tombol aksi (opsional) + stepper tahap sesi. --}}
<div class="page-head">
    <div class="container-xxl px-4">
        <div class="d-flex flex-wrap align-items-end gap-2">
            <div class="me-auto">
                @if ($crumb)
                    <div class="crumb">{{ $crumb }}</div>
                @endif
                <h1>{{ $slot }}</h1>
            </div>
            @isset($actions)
                <div class="d-flex gap-2 no-print">{{ $actions }}</div>
            @endisset
        </div>
        @if ($status)
            <x-status-stepper :status="$status" />
        @endif
    </div>
</div>
