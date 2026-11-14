<?php

namespace App\Enums;

/** Arah kriteria untuk TOPSIS (D11): seluruh C1–C6 = benefit. */
enum CriterionType: string
{
    case Benefit = 'benefit';
    case Cost = 'cost';
}
