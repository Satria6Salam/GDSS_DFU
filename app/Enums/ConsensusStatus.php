<?php

namespace App\Enums;

/** Status hasil Borda (Bab 9.2, consensus_results). */
enum ConsensusStatus: string
{
    case MenungguFinalisasi = 'MENUNGGU_FINALISASI';
    case PerluMusyawarah = 'PERLU_MUSYAWARAH';
    case Final = 'FINAL';
    case Dibatalkan = 'DIBATALKAN';
}
