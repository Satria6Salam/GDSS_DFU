<?php

namespace App\Services;

use App\Enums\RedFlagCode;
use App\Enums\WoundProgress;

/**
 * Evaluasi tanda bahaya otomatis dari data kasus (Bab 3.5, D12).
 *
 * Service murni: menerima array atribut kasus (nama kolom Bab 4.2), tanpa akses basis data.
 * RF3 (dugaan infeksi nekrotikans) hanya dapat ditambahkan manual oleh ketua.
 */
class RedFlagService
{
    public const ABI_CRITICAL = 0.40;

    public const GDS_CRITICAL = 400;

    /**
     * @param  array<string, mixed>  $case
     * @return list<RedFlagCode> kode red flag terurut RF1 → RF5
     */
    public function evaluate(array $case): array
    {
        $flags = [];

        if ($this->bool($case['tanda_inflamasi'] ?? false) && $this->sirsCount($case) >= 2) {
            $flags[] = RedFlagCode::RF1;
        }

        if ((float) ($case['abi'] ?? 1) < self::ABI_CRITICAL
            || $this->bool($case['nyeri_istirahat'] ?? false)
            || $this->bool($case['gangren'] ?? false)) {
            $flags[] = RedFlagCode::RF2;
        }

        if ((int) ($case['gds'] ?? 0) >= self::GDS_CRITICAL) {
            $flags[] = RedFlagCode::RF4;
        }

        if ($this->progress($case['progres_luka'] ?? null) === WoundProgress::MemburukCepat) {
            $flags[] = RedFlagCode::RF5;
        }

        return $flags;
    }

    /**
     * Jumlah kriteria SIRS yang terpenuhi: suhu > 38 / < 36 °C, nadi > 90, laju napas > 20,
     * leukosit > 12 / < 4 (×10³/µL).
     */
    public function sirsCount(array $case): int
    {
        $suhu = (float) ($case['suhu'] ?? 37);
        $leukosit = (float) ($case['leukosit'] ?? 8);

        return (int) ($suhu > 38 || $suhu < 36)
            + (int) ((int) ($case['nadi'] ?? 0) > 90)
            + (int) ((int) ($case['laju_napas'] ?? 0) > 20)
            + (int) ($leukosit > 12 || $leukosit < 4);
    }

    private function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function progress(mixed $value): ?WoundProgress
    {
        if ($value instanceof WoundProgress) {
            return $value;
        }

        return is_string($value) ? WoundProgress::tryFrom($value) : null;
    }
}
