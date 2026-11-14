<?php

namespace App\Enums;

/** Kode tanda bahaya (Bab 3.5). */
enum RedFlagCode: string
{
    case RF1 = 'RF1';
    case RF2 = 'RF2';
    case RF3 = 'RF3';
    case RF4 = 'RF4';
    case RF5 = 'RF5';

    public function label(): string
    {
        return match ($this) {
            self::RF1 => 'Infeksi berat',
            self::RF2 => 'Iskemia yang mengancam tungkai',
            self::RF3 => 'Dugaan infeksi nekrotikans',
            self::RF4 => 'Gangguan metabolik akut',
            self::RF5 => 'Perburukan cepat',
        };
    }
}
