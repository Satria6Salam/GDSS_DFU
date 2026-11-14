@php use App\Support\AuditLabels; @endphp
<x-layouts.app title="Audit Log" active="audit">
    <x-slot:header>
        <x-page-header crumb="Sesi {{ $session->title }} › Audit Log" :status="$session->status">
            Audit Log
        </x-page-header>
    </x-slot:header>

    <div class="card-soft">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <span class="small text-muted-2 me-auto">{{ $logs->total() }} catatan. Hanya dapat ditambah; tidak dapat diubah atau dihapus.</span>
            <select name="action" class="form-select form-select-sm" style="max-width: 260px" onchange="this.form.submit()" aria-label="Saring aksi">
                <option value="">Semua aksi</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected($selected === $action)>{{ AuditLabels::label($action) }}</option>
                @endforeach
            </select>
        </form>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-2">
                <thead><tr><th style="width: 150px">Waktu</th><th style="width: 170px">Pelaku</th><th>Aksi</th><th>Perubahan status</th><th>Keterangan</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap small num">{{ $log->created_at->format('d-m-Y H:i:s') }}</td>
                        <td class="small">{{ $log->user?->name ?? 'Sistem' }}</td>
                        <td class="small">
                            <i class="bi bi-{{ AuditLabels::icon($log->action) }} text-muted-2 me-1"></i>
                            <span class="fw-semibold">{{ AuditLabels::label($log->action) }}</span>
                        </td>
                        <td class="small text-nowrap">
                            @if ($log->from_status || $log->to_status)
                                <span class="text-muted-2">{{ str_replace('_', ' ', $log->from_status ?? '–') }}</span>
                                <i class="bi bi-arrow-right mx-1 text-muted-2"></i>{{ str_replace('_', ' ', $log->to_status ?? '–') }}
                            @endif
                        </td>
                        <td class="small text-muted-2">
                            {{ AuditLabels::detail($log) }}
                            @if ($log->note) <div class="text-body">“{{ $log->note }}”</div> @endif
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
