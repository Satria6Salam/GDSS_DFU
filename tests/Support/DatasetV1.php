<?php

namespace Tests\Support;

/**
 * Membaca data masukan dataset_v1 (file seed) dan kunci jawaban Bab 5.4 (fixture) untuk unit test.
 */
final class DatasetV1
{
    public const CRITERIA = ['C1', 'C2', 'C3', 'C4', 'C5', 'C6'];

    public const DECISION_MAKERS = ['dokter', 'perawat', 'apoteker'];

    private static function json(string $relative): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../'.$relative), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function expected(): array
    {
        return self::json('tests/fixtures/dataset_v1_expected.json');
    }

    /** Pasangan AHP atas-diagonal dengan indeks 0-based untuk AhpService::buildMatrix. */
    public static function upperPairs(string $key): array
    {
        $raw = self::json('database/seeders/data/dataset_v1/ahp_judgments.json')[$key];

        return array_map(fn (array $p) => [
            'i' => array_search($p['i'], self::CRITERIA, true),
            'j' => array_search($p['j'], self::CRITERIA, true),
            'value' => self::saaty($p['value']),
        ], $raw);
    }

    /** Matriks keputusan seorang DM: kode kasus => skor C1..C6. */
    public static function scores(string $dm): array
    {
        $raw = self::json('database/seeders/data/dataset_v1/assessments.json')[$dm];

        return array_map(fn (array $row) => array_values($row), $raw);
    }

    private static function saaty(string $value): float
    {
        if (str_contains($value, '/')) {
            [$num, $den] = explode('/', $value);

            return (float) $num / (float) $den;
        }

        return (float) $value;
    }
}
