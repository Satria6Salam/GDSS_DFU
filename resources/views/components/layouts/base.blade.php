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
        <div class="container-xxl px-4 d-flex gap-2 align-items-center">
            <i class="bi bi-shield-check"></i>
            <span>Prototipe akademik — bukan perangkat medis dan tidak menggantikan keputusan tenaga kesehatan.
                Kondisi darurat tidak menunggu voting. Seluruh data adalah DATA SIMULASI.</span>
        </div>
    </footer>

    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    {{ $scripts ?? '' }}
</body>
</html>
