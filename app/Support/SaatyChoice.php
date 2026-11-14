<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Pilihan skala Saaty dua arah pada form AHP (Bab 5.1.1, Gambar 10.3).
 *
 * "L5" = kriteria kiri 5× lebih penting (a_ij = 5), "R5" = kriteria kanan 5× lebih penting (a_ij = 1/5),
 * "1" = sama penting.
 */
final class SaatyChoice
{
    /** Urutan tombol dari kiri ke kanan: L9 … L2, 1, R2 … R9. */
    public static function options(): array
    {
        return [...array_map(fn ($n) => "L{$n}", range(9, 2)), '1', ...array_map(fn ($n) => "R{$n}", range(2, 9))];
    }

    public static function toValue(string $choice): float
    {
        if ($choice === '1') {
            return 1.0;
        }
        if (! preg_match('/^([LR])([2-9])$/', $choice, $m)) {
            throw new InvalidArgumentException("Pilihan skala tidak dikenal: {$choice}");
        }

        return $m[1] === 'L' ? (float) $m[2] : 1 / (float) $m[2];
    }

    public static function fromValue(float $value): string
    {
        if (abs($value - 1) < 1e-9) {
            return '1';
        }

        return $value > 1 ? 'L'.(int) round($value) : 'R'.(int) round(1 / $value);
    }

    /** Angka yang tertulis pada tombol (tanpa arah). */
    public static function label(string $choice): string
    {
        return ltrim($choice, 'LR');
    }
}
