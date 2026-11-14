<?php

namespace Tests\Unit;

use App\Enums\RedFlagCode;
use App\Services\RedFlagService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** UT-12 – UT-15 (dokumen spesifikasi Bab 12.3). */
class RedFlagServiceTest extends TestCase
{
    private RedFlagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RedFlagService;
    }

    /** Kasus netral tanpa tanda bahaya; tiap test mengubah satu atribut. */
    private function baseCase(array $override = []): array
    {
        return array_merge([
            'tanda_inflamasi' => false, 'suhu' => 37.0, 'nadi' => 80, 'laju_napas' => 18, 'leukosit' => 8.0,
            'abi' => 1.0, 'nyeri_istirahat' => false, 'gangren' => false, 'gds' => 180, 'progres_luka' => 'STABIL',
        ], $override);
    }

    private function codes(array $case): array
    {
        return array_map(fn (RedFlagCode $c) => $c->value, $this->service->evaluate($case));
    }

    #[Test]
    public function ut12_dataset_v1_hanya_p07_yang_ber_red_flag(): void
    {
        $path = __DIR__.'/../../database/seeders/data/dataset_v1/cases.json';
        $cases = json_decode(file_get_contents($path), true)['cases'];

        foreach ($cases as $case) {
            $this->assertSame($case['_red_flag_diharapkan'], $this->codes($case), "Kasus {$case['kode_kasus']}");
        }
    }

    #[Test]
    public function ut13_batas_abi_040(): void
    {
        $this->assertSame([], $this->codes($this->baseCase(['abi' => 0.40])));
        $this->assertSame(['RF2'], $this->codes($this->baseCase(['abi' => 0.39])));
    }

    #[Test]
    public function rf2_juga_dipicu_nyeri_istirahat_atau_gangren(): void
    {
        $this->assertSame(['RF2'], $this->codes($this->baseCase(['nyeri_istirahat' => true])));
        $this->assertSame(['RF2'], $this->codes($this->baseCase(['gangren' => true])));
    }

    #[Test]
    public function ut14_batas_gds_400(): void
    {
        $this->assertSame([], $this->codes($this->baseCase(['gds' => 399])));
        $this->assertSame(['RF4'], $this->codes($this->baseCase(['gds' => 400])));
    }

    #[Test]
    public function ut15_tepat_dua_kriteria_sirs_dengan_dan_tanpa_inflamasi(): void
    {
        $twoSirs = ['suhu' => 38.5, 'nadi' => 95];

        $this->assertSame(2, $this->service->sirsCount($this->baseCase($twoSirs)));
        $this->assertSame(['RF1'], $this->codes($this->baseCase([...$twoSirs, 'tanda_inflamasi' => true])));
        $this->assertSame([], $this->codes($this->baseCase([...$twoSirs, 'tanda_inflamasi' => false])));
    }

    #[Test]
    public function satu_kriteria_sirs_belum_rf1(): void
    {
        $this->assertSame([], $this->codes($this->baseCase(['tanda_inflamasi' => true, 'nadi' => 92])));
    }

    public static function sirsBoundaries(): array
    {
        return [
            'suhu 38,0 tidak' => [['suhu' => 38.0], 0],
            'suhu 38,1 ya' => [['suhu' => 38.1], 1],
            'suhu 35,9 ya' => [['suhu' => 35.9], 1],
            'nadi 90 tidak' => [['nadi' => 90], 0],
            'laju napas 21 ya' => [['laju_napas' => 21], 1],
            'leukosit 12,0 tidak' => [['leukosit' => 12.0], 0],
            'leukosit 3,9 ya' => [['leukosit' => 3.9], 1],
        ];
    }

    #[Test]
    #[DataProvider('sirsBoundaries')]
    public function batas_tiap_kriteria_sirs(array $override, int $expected): void
    {
        $this->assertSame($expected, $this->service->sirsCount($this->baseCase($override)));
    }

    #[Test]
    public function rf5_dari_progres_memburuk_cepat(): void
    {
        $this->assertSame(['RF5'], $this->codes($this->baseCase(['progres_luka' => 'MEMBURUK_CEPAT'])));
        $this->assertSame([], $this->codes($this->baseCase(['progres_luka' => 'MEMBURUK_1_MINGGU'])));
    }
}
