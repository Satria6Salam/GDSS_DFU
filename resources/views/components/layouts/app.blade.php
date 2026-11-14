@props(['title' => null, 'active' => null])
@php
    $user = auth()->user();
    // Menu navbar per peran (Bab 10.3). Menu yang rutenya belum dibuat tampil pudar.
    $menu = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard'],
        ['key' => 'kasus', 'label' => 'Kasus', 'route' => 'cases.index'],
        ['key' => 'penilaian', 'label' => 'Penilaian', 'route' => 'assessment.ahp', 'session' => true],
        ['key' => 'voting', 'label' => 'Voting', 'route' => 'vote.show', 'session' => true],
        ['key' => 'hasil', 'label' => 'Hasil', 'route' => 'consensus.show', 'session' => true],
        ['key' => 'riwayat', 'label' => 'Riwayat', 'route' => 'sessions.index'],
        ['key' => 'panel', 'label' => 'Panel Ketua', 'route' => 'control.show', 'leader' => true, 'session' => true],
        ['key' => 'audit', 'label' => 'Audit', 'route' => 'audit.index', 'leader' => true, 'session' => true],
    ];
    $hrefFor = function (array $item) use ($currentSession) {
        if (! Route::has($item['route'])) {
            return null;
        }
        if ($item['session'] ?? false) {
            return $currentSession ? route($item['route'], $currentSession) : null;
        }

        return route($item['route']);
    };
@endphp
<x-layouts.base :title="$title">
    @isset($scripts)
        <x-slot:scripts>{{ $scripts }}</x-slot:scripts>
    @endisset
    <nav class="app-nav navbar navbar-expand-lg py-0">
        <div class="container-xxl px-4">
            <x-brand :href="route('dashboard')" class="me-4" />
            <button class="navbar-toggler my-2" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="Buka menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto">
                    @foreach ($menu as $item)
                        @continue(($item['leader'] ?? false) && ! $user->isLeader())
                        <li class="nav-item">
                            @if ($href = $hrefFor($item))
                                <a @class(['nav-link', 'active' => $active === $item['key']])
                                   href="{{ $href }}">{{ $item['label'] }}</a>
                            @else
                                <span class="nav-link disabled" title="Belum tersedia">{{ $item['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <div class="d-flex align-items-center gap-2 py-2">
                    <span class="sim-label">DATA SIMULASI</span>
                    <div class="dropdown">
                        <button class="user-chip dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar">{{ $user->initials() }}</span>{{ $user->name }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                            <li><span class="dropdown-item-text small text-muted-2">
                                {{ $user->role->label() }}{{ $user->isLeader() ? ' · Ketua tim' : '' }}</span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main>
        {{ $header ?? '' }}
        <div class="container-xxl px-4 py-4">
            @foreach (['success' => 'check-circle', 'info' => 'info-circle', 'error' => 'x-circle'] as $type => $icon)
                @if (session($type))
                    <div class="alert alert-{{ $type === 'error' ? 'danger' : $type }} d-flex gap-2 align-items-center rounded-4 border-0" role="alert">
                        <i class="bi bi-{{ $icon }}"></i>{{ session($type) }}
                    </div>
                @endif
            @endforeach
            {{ $slot }}
        </div>
    </main>
</x-layouts.base>
