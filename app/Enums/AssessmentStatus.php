<?php

namespace App\Enums;

/** Status penilaian per decision maker (Bab 7.3). */
enum AssessmentStatus: string
{
    case Belum = 'BELUM';
    case Draf = 'DRAF';
    case Final = 'FINAL';
    case Terkunci = 'TERKUNCI';
}
