<?php

namespace App\Support;

use App\Models\AuditLog;

/** Teks dan ikon yang mudah dibaca untuk kode aksi audit log (Bab 7.7). */
final class AuditLabels
{
    private const MAP = [
        'LOGIN' => ['Masuk ke sistem', 'box-arrow-in-right'],
        'LOGIN_FAILED' => ['Percobaan masuk gagal', 'x-circle'],
        'LOGIN_THROTTLED' => ['Masuk diblokir sementara', 'slash-circle'],
        'LOGOUT' => ['Keluar', 'box-arrow-right'],
        'ACCESS_DENIED' => ['Akses ditolak', 'shield-lock'],
        'SESSION_CREATE' => ['Sesi dibuat', 'folder-plus'],
        'DATASET_IMPORT' => ['Dataset diimpor', 'download'],
        'CASE_CREATE' => ['Kasus ditambahkan', 'plus-square'],
        'CASE_UPDATE' => ['Kasus diubah', 'pencil-square'],
        'CASE_DELETE' => ['Kasus dihapus', 'trash'],
        'RED_FLAG_MANUAL' => ['Red flag manual ditambahkan', 'flag'],
        'SESSION_OPEN_ASSESSMENT' => ['Penilaian dibuka', 'unlock'],
        'ASSESSMENT_SAVE' => ['Draf penilaian disimpan', 'save'],
        'ASSESSMENT_SUBMIT' => ['Penilaian dikirim final', 'send-check'],
        'ASSESSMENT_WITHDRAW' => ['Penilaian ditarik ke draf', 'arrow-counterclockwise'],
        'VOTING_START' => ['Voting dimulai', 'play-circle'],
        'VOTE_SUBMIT' => ['Suara dikirim', 'check2-square'],
        'VOTING_CLOSE' => ['Voting ditutup, Borda dihitung', 'calculator'],
        'DELIBERATION' => ['Urutan seri ditetapkan', 'people'],
        'SESSION_REOPEN' => ['Sesi dibuka ulang', 'arrow-repeat'],
        'SESSION_FINALIZE' => ['Hasil difinalisasi', 'patch-check'],
    ];

    public static function label(string $action): string
    {
        return self::MAP[$action][0] ?? ucfirst(mb_strtolower(str_replace('_', ' ', $action)));
    }

    public static function icon(string $action): string
    {
        return self::MAP[$action][1] ?? 'dot';
    }

    /** Ringkasan data tambahan dalam bahasa biasa, bukan JSON. */
    public static function detail(AuditLog $log): ?string
    {
        $m = $log->meta ?? [];
        $parts = [];

        if (isset($m['type'])) {
            $parts[] = $m['type'] === 'ahp' ? 'perbandingan kriteria' : 'skor kasus';
        }
        if (isset($m['cr'])) {
            $parts[] = 'CR '.number_format((float) $m['cr'], 4, ',', '.');
        }
        if (isset($m['kode_kasus'])) {
            $parts[] = 'kasus '.$m['kode_kasus'];
        }
        if (isset($m['code'])) {
            $parts[] = $m['code'];
        }
        if (isset($m['cases'])) {
            $parts[] = $m['cases'].' kasus';
        }
        if (isset($m['dataset_version'])) {
            $parts[] = $m['dataset_version'];
        }
        if (isset($m['needs_deliberation'])) {
            $parts[] = $m['needs_deliberation'] ? 'ada seri, perlu musyawarah' : 'tanpa seri';
        }
        if (isset($m['path'])) {
            $parts[] = '/'.$m['path'];
        }
        if (isset($m['username']) && $log->action !== 'LOGIN') {
            $parts[] = 'username “'.$m['username'].'”';
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
