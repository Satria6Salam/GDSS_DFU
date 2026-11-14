<?php

namespace App\Enums;

/** Peran decision maker (Bab 6.1). */
enum Role: string
{
    case Dokter = 'dokter';
    case Perawat = 'perawat';
    case Apoteker = 'apoteker';

    public function label(): string
    {
        return match ($this) {
            self::Dokter => 'Dokter',
            self::Perawat => 'Perawat Luka',
            self::Apoteker => 'Apoteker Klinis',
        };
    }
}
