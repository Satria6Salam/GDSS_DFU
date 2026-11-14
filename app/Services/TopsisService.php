<?php

namespace App\Services;

use App\Enums\CriterionType;
use InvalidArgumentException;

/**
 * TOPSIS (Bab 5.2, D03, D11).
 *
 * Service murni: alternatif diberi kunci bebas (mis. case_id atau kode kasus), kriteria berindeks 0..n-1.
 */
class TopsisService
{
    private const EPS = 1e-9;

    /**
     * @param  array<int|string, array<int, float|int>>  $matrix  alternatif => nilai per kriteria
     * @param  array<int, float>  $weights  bobot AHP per kriteria
     * @param  array<int, CriterionType>  $types  arah per kriteria; default benefit
     * @param  array<int|string, int|float>  $tiePriority  urutan pemecah seri bila V sama (kecil = lebih dulu);
     *                                                     default urutan kunci alternatif (Bab 5.5)
     * @return array<int|string, array{d_plus: float, d_minus: float, v: float, rank: int}> terurut dari peringkat 1
     */
    public function rank(array $matrix, array $weights, array $types = [], array $tiePriority = []): array
    {
        if ($matrix === []) {
            throw new InvalidArgumentException('Matriks keputusan kosong.');
        }

        $keys = array_keys($matrix);
        $n = count($weights);

        foreach ($matrix as $key => $row) {
            if (count($row) !== $n) {
                throw new InvalidArgumentException("Alternatif {$key} memiliki ".count($row)." nilai, seharusnya {$n}.");
            }
        }

        // Langkah 2: pembagi normalisasi vektor.
        $divisor = [];
        for ($j = 0; $j < $n; $j++) {
            $sumSquares = 0.0;
            foreach ($matrix as $row) {
                $sumSquares += $row[$j] ** 2;
            }
            $divisor[$j] = sqrt($sumSquares);
        }

        // Langkah 3: matriks ternormalisasi terbobot.
        $y = [];
        foreach ($matrix as $key => $row) {
            for ($j = 0; $j < $n; $j++) {
                $y[$key][$j] = $divisor[$j] > 0 ? $weights[$j] * $row[$j] / $divisor[$j] : 0.0;
            }
        }

        // Langkah 4: solusi ideal positif dan negatif.
        $idealPositive = $idealNegative = [];
        for ($j = 0; $j < $n; $j++) {
            $column = array_column($y, $j);
            $isCost = ($types[$j] ?? CriterionType::Benefit) === CriterionType::Cost;
            $idealPositive[$j] = $isCost ? min($column) : max($column);
            $idealNegative[$j] = $isCost ? max($column) : min($column);
        }

        // Langkah 5–6: jarak dan nilai preferensi.
        $result = [];
        foreach ($y as $key => $row) {
            $dPlus = $dMinus = 0.0;
            for ($j = 0; $j < $n; $j++) {
                $dPlus += ($row[$j] - $idealPositive[$j]) ** 2;
                $dMinus += ($row[$j] - $idealNegative[$j]) ** 2;
            }
            $dPlus = sqrt($dPlus);
            $dMinus = sqrt($dMinus);
            $total = $dPlus + $dMinus;

            $result[$key] = [
                'd_plus' => $dPlus,
                'd_minus' => $dMinus,
                // Semua alternatif identik → D⁺ + D⁻ = 0 → V = 0,5 (Bab 5.5).
                'v' => $total > self::EPS ? $dMinus / $total : 0.5,
            ];
        }

        // Langkah 7: urutkan V menurun; V sama (< 1e-9) memakai prioritas pemecah seri lalu urutan kunci.
        $position = array_flip($keys);
        uksort($result, function ($a, $b) use ($result, $tiePriority, $position) {
            $diff = $result[$b]['v'] - $result[$a]['v'];
            if (abs($diff) > self::EPS) {
                return $diff <=> 0;
            }

            return [$tiePriority[$a] ?? PHP_INT_MAX, $position[$a]] <=> [$tiePriority[$b] ?? PHP_INT_MAX, $position[$b]];
        });

        $rank = 0;
        foreach ($result as &$row) {
            $row['rank'] = ++$rank;
        }
        unset($row);

        return $result;
    }

    /**
     * Matriks keputusan kelompok: rata-rata aritmetik skor seluruh DM per sel (D03).
     *
     * @param  list<array<int|string, array<int, float|int>>>  $matrices
     * @return array<int|string, array<int, float>>
     */
    public function averageMatrices(array $matrices): array
    {
        if ($matrices === []) {
            throw new InvalidArgumentException('Minimal satu matriks diperlukan.');
        }

        $count = count($matrices);
        $result = [];
        foreach (array_keys($matrices[0]) as $key) {
            foreach (array_keys($matrices[0][$key]) as $j) {
                $result[$key][$j] = array_sum(array_map(fn ($m) => $m[$key][$j], $matrices)) / $count;
            }
        }

        return $result;
    }
}
