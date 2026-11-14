<?php

namespace App\Enums;

/** Pilihan progres luka, sejajar dengan rubrik C6 (Bab 3.3). */
enum WoundProgress: string
{
    case Membaik = 'MEMBAIK';
    case Stabil = 'STABIL';
    case TidakMembaik = 'TIDAK_MEMBAIK';
    case Memburuk1Minggu = 'MEMBURUK_1_MINGGU';
    case MemburukCepat = 'MEMBURUK_CEPAT';

    public function label(): string
    {
        return match ($this) {
            self::Membaik => 'Membaik (luas berkurang > 50% dalam 4 minggu)',
            self::Stabil => 'Stabil, belum membaik (< 4 minggu)',
            self::TidakMembaik => 'Tidak membaik (luas berkurang < 50% dalam 4 minggu)',
            self::Memburuk1Minggu => 'Memburuk dalam 1 minggu terakhir',
            self::MemburukCepat => 'Memburuk cepat dalam 24–72 jam',
        };
    }
}
