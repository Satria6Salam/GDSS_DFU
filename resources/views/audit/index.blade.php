<x-layouts.app title="Audit Log" active="audit">
    <x-slot:header>
        <x-page-header crumb="Sesi {{ $session->title }} › Audit Log" :status="$session->status">
            Audit Log
        </x-page-header>
    </x-slot:header>

    <div class="card-soft">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <span class="small text-muted-2 me-auto">Catatan hanya dapat ditambah; tidak dapat diubah atau dihapus (Bab 7.7).</span>
            <select name="action" class="form-select form-select-sm" style="max-width: 260px" onchange="this.form.submit()" aria-label="Saring aksi">
                <option value="">Semua aksi</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected($selected === $action)>{{ str_replace('_', ' ', $action) }}</option>
                @endforeach
            </select>
        </form>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-2">
                <thead><tr><th>Waktu</th><th>Pelaku</th><th>Aksi</th><th>Status</th><th>Catatan / data</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap small">{{ $log->created_at->format('d-m-Y H:i:s') }}</td>
                        <td class="small">{{ $log->user?->name ?? 'Sistem' }}</td>
                        <td class="small fw-bold text-navy">{{ str_replace('_', ' ', $log->action) }}</td>
                        <td class="small text-nowrap">
                            @if ($log->from_status || $log->to_status)
                                {{ $log->from_status ?? '–' }} → {{ $log->to_status ?? '–' }}
                            @endif
                        </td>
                        <td class="small text-muted-2">
                            @if ($log->note) “{{ $log->note }}” @endif
                            @if ($log->meta)
                                <code class="small">{{ \Illuminate\Support\Str::limit(json_encode($log->meta, JSON_UNESCAPED_UNICODE), 120) }}</code>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted-2 py-4">Belum ada catatan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links('pagination::bootstrap-5') }}
    </div>
</x-layouts.app>
