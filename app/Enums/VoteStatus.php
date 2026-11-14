<?php

namespace App\Enums;

/** Status suara (Bab 7.4). BELUM tidak disimpan: DM yang belum memilih tidak punya baris suara. */
enum VoteStatus: string
{
    case Terkirim = 'TERKIRIM';
    case Dibatalkan = 'DIBATALKAN';
}
