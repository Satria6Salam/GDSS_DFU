@props(['title' => null])
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    {{ $head ?? '' }}
</head>
<body>
    {{ $slot }}

    <footer class="app-footer">
        <div class="container-xxl px-4 d-flex flex-wrap gap-2 align-items-center">
            <span><i class="bi bi-shield-check me-1"></i>Prototipe akademik — bukan perangkat medis dan tidak menggantikan keputusan
                tenaga kesehatan. Kondisi darurat tidak menunggu voting. Seluruh data adalah DATA SIMULASI.</span>
            <span class="ms-auto">GDSS DFU · Praktikum Sistem Informasi {{ now()->year }} ·
                <a href="{{ asset('images/CREDITS.md') }}" class="text-reset">Kredit foto</a></span>
        </div>
    </footer>

    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    {{ $scripts ?? '' }}
</body>
</html>
