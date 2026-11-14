<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DecisionSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/** Penulis audit log tunggal (Bab 7.7). Semua peristiwa penting dicatat lewat kelas ini. */
class AuditLogger
{
    public function log(
        string $action,
        ?DecisionSession $session = null,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $note = null,
        array $meta = [],
        ?User $user = null,
        bool $system = false,
    ): AuditLog {
        return AuditLog::create([
            // Aksi otomatis sistem dicatat tanpa pelaku (Bab 7.7).
            'user_id' => $system ? null : ($user ?? Auth::user())?->id,
            'session_id' => $session?->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
