<?php

namespace App\Enums;

/** Status sesi keputusan (Bab 7.2). */
enum SessionStatus: string
{
    case Persiapan = 'PERSIAPAN';
    case Penilaian = 'PENILAIAN';
    case Voting = 'VOTING';
    case MenungguFinalisasi = 'MENUNGGU_FINALISASI';
    case Final = 'FINAL';

    public function label(): string
    {
        return match ($this) {
            self::Persiapan => 'Persiapan',
            self::Penilaian => 'Penilaian',
            self::Voting => 'Voting',
            self::MenungguFinalisasi => 'Menunggu Finalisasi',
            self::Final => 'Final',
        };
    }

    /** Kalimat ringkas tahap untuk banner dashboard. */
    public function description(): string
    {
        return match ($this) {
            self::Persiapan => 'Ketua sedang menyiapkan kasus dan kriteria. Penilaian akan dibuka setelah persiapan selesai.',
            self::Penilaian => 'Penilaian sedang berlangsung. Voting dapat dimulai setelah ketiga anggota mengirim penilaian final.',
            self::Voting => 'Voting dibuka. Tinjau peringkat TOPSIS Anda lalu kirim suara.',
            self::MenungguFinalisasi => 'Semua suara sudah masuk. Hasil konsensus menunggu finalisasi ketua.',
            self::Final => 'Hasil konsensus telah difinalisasi dan dapat dilihat seluruh anggota.',
        };
    }

    /** Urutan tahap untuk stepper antarmuka (1–5). */
    public function step(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }
}
