<?php

namespace App\Enums;

/** Cakupan hasil AHP/TOPSIS (Bab 9.2): milik satu DM atau kelompok. */
enum ResultScope: string
{
    case Individual = 'INDIVIDUAL';
    case Kelompok = 'KELOMPOK';
}
