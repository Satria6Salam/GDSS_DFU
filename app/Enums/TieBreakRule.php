<?php

namespace App\Enums;

/** Aturan pemecah seri yang dipakai untuk satu kasus (D05). */
enum TieBreakRule: string
{
    case VKelompok = 'V_KELOMPOK';
    case JumlahPeringkat1 = 'JUMLAH_PERINGKAT_1';
    case Musyawarah = 'MUSYAWARAH';
}
