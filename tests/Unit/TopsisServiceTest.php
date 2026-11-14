<?php

namespace Tests\Unit;

use App\Enums\CriterionType;
use App\Services\AhpService;
use App\Services\TopsisService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\DatasetV1;

/** UT-05 – UT-07 (Bab 12.3), dibandingkan dengan kunci jawaban Bab 5.4.2. */
class TopsisServiceTest extends TestCase
{
    private TopsisService $topsis;

    private AhpService $ahp;

    private array $expected;

    protected function setUp(): void
    {
        parent::setUp();
        $this->topsis = new TopsisService;
        $this->ahp = new AhpService;
        $this->expected = DatasetV1::expected();
    }

    private function weights(string $dm): array
    {
        return $this->ahp->weights($this->ahp->buildMatrix(DatasetV1::upperPairs($dm), 6));
    }

    private function assertMatchesExpected(array $result, string $scope): void
    {
        $tolerance = $this->expected['tolerance'];
        foreach ($this->expected['topsis'][$scope] as $case => $row) {
            $this->assertEqualsWithDelta($row['d_plus'], $result[$case]['d_plus'], $tolerance, "{$scope} {$case} D+");
            $this->assertEqualsWithDelta($row['d_minus'], $result[$case]['d_minus'], $tolerance, "{$scope} {$case} D-");
            $this->assertEqualsWithDelta($row['v'], $result[$case]['v'], $tolerance, "{$scope} {$case} V");
            $this->assertSame($row['rank'], $result[$case]['rank'], "{$scope} {$case} peringkat");
        }
    }

    #[Test]
    public function ut05_topsis_tiga_dm_sesuai_kunci(): void
    {
        foreach (DatasetV1::DECISION_MAKERS as $dm) {
            $result = $this->topsis->rank(DatasetV1::scores($dm), $this->weights($dm));

            $this->assertMatchesExpected($result, $dm);
            $this->assertSame($this->expected['votes'][$dm], array_keys($result), "urutan {$dm}");
        }
    }

    #[Test]
    public function ut05_topsis_kelompok_sesuai_kunci(): void
    {
        $matrix = $this->topsis->averageMatrices(array_map(fn ($dm) => DatasetV1::scores($dm), DatasetV1::DECISION_MAKERS));
        $weights = $this->ahp->weights($this->ahp->aggregate(
            array_map(fn ($dm) => $this->ahp->buildMatrix(DatasetV1::upperPairs($dm), 6), DatasetV1::DECISION_MAKERS)
        ));

        $this->assertMatchesExpected($this->topsis->rank($matrix, $weights), 'kelompok');
    }

    #[Test]
    public function ut06_semua_kasus_identik_v_setengah(): void
    {
        $matrix = ['A' => [3, 3, 3], 'B' => [3, 3, 3], 'C' => [3, 3, 3]];
        $result = $this->topsis->rank($matrix, [0.5, 0.3, 0.2]);

        foreach ($result as $row) {
            $this->assertSame(0.5, $row['v']);
        }
        $this->assertSame(['A', 'B', 'C'], array_keys($result));
    }

    #[Test]
    public function ut07_kriteria_cost_membalik_arah(): void
    {
        $matrix = ['A' => [1], 'B' => [5]];

        $benefit = $this->topsis->rank($matrix, [1.0]);
        $cost = $this->topsis->rank($matrix, [1.0], [CriterionType::Cost]);

        $this->assertSame(['B', 'A'], array_keys($benefit));
        $this->assertSame(['A', 'B'], array_keys($cost));
        $this->assertSame(1.0, $cost['A']['v']);
    }

    #[Test]
    public function v_sama_memakai_prioritas_pemecah_seri(): void
    {
        $matrix = ['P01' => [2, 4], 'P02' => [4, 2]];

        $default = $this->topsis->rank($matrix, [0.5, 0.5]);
        $prioritised = $this->topsis->rank($matrix, [0.5, 0.5], tiePriority: ['P02' => 1, 'P01' => 2]);

        $this->assertSame(['P01', 'P02'], array_keys($default));
        $this->assertSame(['P02', 'P01'], array_keys($prioritised));
    }
}
