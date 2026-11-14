<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Analytical Hierarchy Process (Bab 5.1, D01, D02, D06).
 *
 * Metode: normalisasi kolom + rata-rata baris (aproksimasi eigenvector).
 * Service murni: matriks berindeks 0..n-1, tanpa akses basis data.
 */
class AhpService
{
    public const CR_THRESHOLD = 0.10;

    /** Random Index Saaty untuk n = 1..10. */
    public const RANDOM_INDEX = [1 => 0.0, 2 => 0.0, 3 => 0.58, 4 => 0.90, 5 => 1.12, 6 => 1.24, 7 => 1.32, 8 => 1.41, 9 => 1.45, 10 => 1.49];

    private const EPS = 1e-9;

    /**
     * Bentuk matriks resiprokal dari nilai di atas diagonal (langkah 1).
     *
     * @param  array<int, array{i:int, j:int, value:float}>  $upper  i < j, indeks 0-based
     * @return array<int, array<int, float>>
     */
    public function buildMatrix(array $upper, int $n): array
    {
        $matrix = array_fill(0, $n, array_fill(0, $n, 1.0));
        $expected = $n * ($n - 1) / 2;

        if (count($upper) !== $expected) {
            throw new InvalidArgumentException("Matriks {$n}×{$n} membutuhkan {$expected} perbandingan, diterima ".count($upper).'.');
        }

        foreach ($upper as $pair) {
            [$i, $j, $value] = [(int) $pair['i'], (int) $pair['j'], (float) $pair['value']];

            if ($i >= $j || $j >= $n) {
                throw new InvalidArgumentException("Pasangan ({$i}, {$j}) harus berada di atas diagonal.");
            }
            if (! self::isSaatyValue($value)) {
                throw new InvalidArgumentException("Nilai {$value} bukan nilai skala Saaty (1/9 … 9).");
            }

            $matrix[$i][$j] = $value;
            $matrix[$j][$i] = 1 / $value;
        }

        return $matrix;
    }

    /** Nilai sah: 1..9 atau kebalikannya 1/2..1/9. */
    public static function isSaatyValue(float $value): bool
    {
        if ($value <= 0) {
            return false;
        }
        $whole = $value >= 1 ? $value : 1 / $value;

        return abs($whole - round($whole)) < 1e-6 && round($whole) >= 1 && round($whole) <= 9;
    }

    /**
     * Bobot kriteria (langkah 2–4).
     *
     * @param  array<int, array<int, float>>  $matrix
     * @return array<int, float>
     */
    public function weights(array $matrix): array
    {
        $n = count($matrix);
        $columnSums = [];
        for ($j = 0; $j < $n; $j++) {
            $columnSums[$j] = array_sum(array_column($matrix, $j));
        }

        $weights = [];
        for ($i = 0; $i < $n; $i++) {
            $rowSum = 0.0;
            for ($j = 0; $j < $n; $j++) {
                $rowSum += $matrix[$i][$j] / $columnSums[$j];
            }
            $weights[$i] = $rowSum / $n;
        }

        return $weights;
    }

    /**
     * λmax, CI, dan CR (langkah 5–7).
     *
     * @param  array<int, array<int, float>>  $matrix
     * @param  array<int, float>|null  $weights
     * @return array{lambda_max: float, ci: float, cr: float, consistent: bool}
     */
    public function consistency(array $matrix, ?array $weights = null): array
    {
        $n = count($matrix);
        $weights ??= $this->weights($matrix);

        $ratioSum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $aw = 0.0;
            for ($j = 0; $j < $n; $j++) {
                $aw += $matrix[$i][$j] * $weights[$j];
            }
            $ratioSum += $aw / $weights[$i];
        }

        $lambdaMax = $ratioSum / $n;
        $ci = $n > 1 ? ($lambdaMax - $n) / ($n - 1) : 0.0;
        $ri = self::RANDOM_INDEX[$n] ?? throw new InvalidArgumentException("RI untuk n = {$n} tidak tersedia.");
        $cr = $ri > 0 ? $ci / $ri : 0.0;

        return [
            'lambda_max' => $lambdaMax,
            'ci' => $ci,
            'cr' => $cr,
            'consistent' => $cr <= self::CR_THRESHOLD + self::EPS,
        ];
    }

    /**
     * Bobot + uji konsistensi sekaligus.
     *
     * @return array{weights: array<int, float>, lambda_max: float, ci: float, cr: float, consistent: bool}
     */
    public function analyze(array $matrix): array
    {
        $weights = $this->weights($matrix);

        return ['weights' => $weights, ...$this->consistency($matrix, $weights)];
    }

    /**
     * Agregasi kelompok AIJ: rata-rata geometrik tiap sel (D02, Bab 5.1.3).
     *
     * @param  list<array<int, array<int, float>>>  $matrices
     * @return array<int, array<int, float>>
     */
    public function aggregate(array $matrices): array
    {
        if ($matrices === []) {
            throw new InvalidArgumentException('Minimal satu matriks diperlukan untuk agregasi.');
        }

        $k = count($matrices);
        $n = count($matrices[0]);
        $result = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $product = 1.0;
                foreach ($matrices as $matrix) {
                    $product *= $matrix[$i][$j];
                }
                $result[$i][$j] = $product ** (1 / $k);
            }
        }

        return $result;
    }
}
