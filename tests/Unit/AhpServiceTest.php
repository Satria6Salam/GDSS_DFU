<?php

namespace Tests\Unit;

use App\Services\AhpService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\DatasetV1;

/** UT-01 – UT-04 (Bab 12.3), dibandingkan dengan kunci jawaban Bab 5.4.1. */
class AhpServiceTest extends TestCase
{
    private AhpService $ahp;

    private array $expected;

    private float $tolerance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ahp = new AhpService;
        $this->expected = DatasetV1::expected();
        $this->tolerance = $this->expected['tolerance'];
    }

    private function matrix(string $key): array
    {
        return $this->ahp->buildMatrix(DatasetV1::upperPairs($key), 6);
    }

    private function assertAnalysisMatches(array $analysis, array $expected, string $label): void
    {
        foreach (DatasetV1::CRITERIA as $index => $code) {
            $this->assertEqualsWithDelta($expected['weights'][$code], $analysis['weights'][$index], $this->tolerance, "{$label} bobot {$code}");
        }
        foreach (['lambda_max', 'ci', 'cr'] as $field) {
            $this->assertEqualsWithDelta($expected[$field], $analysis[$field], $this->tolerance, "{$label} {$field}");
        }
    }

    #[Test]
    public function ut01_matriks_resiprokal_dengan_diagonal_satu(): void
    {
        $matrix = $this->matrix('dokter');

        for ($i = 0; $i < 6; $i++) {
            $this->assertSame(1.0, $matrix[$i][$i]);
            for ($j = 0; $j < 6; $j++) {
                $this->assertEqualsWithDelta(1.0, $matrix[$i][$j] * $matrix[$j][$i], 1e-12);
            }
        }
        $this->assertSame(3.0, $matrix[0][2]);      // C1 vs C3 = 3
        $this->assertSame(1 / 3, $matrix[2][0]);
    }

    #[Test]
    public function ut02_bobot_dan_cr_tiga_dm_sesuai_kunci(): void
    {
        foreach (DatasetV1::DECISION_MAKERS as $dm) {
            $analysis = $this->ahp->analyze($this->matrix($dm));

            $this->assertAnalysisMatches($analysis, $this->expected['ahp'][$dm], $dm);
            $this->assertEqualsWithDelta(1.0, array_sum($analysis['weights']), 1e-12);
            $this->assertTrue($analysis['consistent'], "{$dm} harus konsisten");
        }

        // Nilai yang tercetak di dokumen (4 desimal).
        $this->assertEqualsWithDelta(0.0031, $this->ahp->analyze($this->matrix('dokter'))['cr'], 5e-5);
        $this->assertEqualsWithDelta(0.0063, $this->ahp->analyze($this->matrix('perawat'))['cr'], 5e-5);
        $this->assertEqualsWithDelta(0.0067, $this->ahp->analyze($this->matrix('apoteker'))['cr'], 5e-5);
    }

    #[Test]
    public function ut03_matriks_uji_tidak_konsisten(): void
    {
        $analysis = $this->ahp->analyze($this->matrix('_uji_tidak_konsisten_apoteker'));

        $this->assertAnalysisMatches($analysis, $this->expected['ahp']['uji_tidak_konsisten'], 'uji');
        $this->assertEqualsWithDelta(0.3509, $analysis['cr'], 5e-5);
        $this->assertFalse($analysis['consistent']);
    }

    #[Test]
    public function ut04_agregasi_aij_resiprokal_dan_sesuai_kunci(): void
    {
        $group = $this->ahp->aggregate(array_map(fn ($dm) => $this->matrix($dm), DatasetV1::DECISION_MAKERS));

        foreach (DatasetV1::CRITERIA as $i => $ci) {
            foreach (DatasetV1::CRITERIA as $j => $cj) {
                $this->assertEqualsWithDelta($this->expected['ahp_group_matrix'][$ci][$cj], $group[$i][$j], $this->tolerance);
                $this->assertEqualsWithDelta(1.0, $group[$i][$j] * $group[$j][$i], 1e-12);
            }
        }

        $analysis = $this->ahp->analyze($group);
        $this->assertAnalysisMatches($analysis, $this->expected['ahp']['kelompok'], 'kelompok');
        $this->assertEqualsWithDelta(0.0011, $analysis['cr'], 5e-5);
    }

    #[Test]
    public function menolak_nilai_di_luar_skala_saaty(): void
    {
        $pairs = DatasetV1::upperPairs('dokter');
        $pairs[0]['value'] = 10;

        $this->expectException(InvalidArgumentException::class);
        $this->ahp->buildMatrix($pairs, 6);
    }

    #[Test]
    public function menolak_jumlah_pasangan_yang_kurang(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->ahp->buildMatrix(array_slice(DatasetV1::upperPairs('dokter'), 0, 14), 6);
    }

    #[Test]
    public function matriks_semua_satu_bobot_merata_dan_cr_nol(): void
    {
        $analysis = $this->ahp->analyze(array_fill(0, 6, array_fill(0, 6, 1.0)));

        foreach ($analysis['weights'] as $weight) {
            $this->assertEqualsWithDelta(1 / 6, $weight, 1e-12);
        }
        $this->assertEqualsWithDelta(0.0, $analysis['cr'], 1e-12);
    }
}
