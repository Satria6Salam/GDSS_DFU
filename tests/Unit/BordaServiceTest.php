<?php

namespace Tests\Unit;

use App\Enums\TieBreakRule;
use App\Services\BordaService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\DatasetV1;

/** UT-08 – UT-11 (Bab 12.3), dibandingkan dengan kunci jawaban Bab 5.4.3 dan ilustrasi Bab 5.3. */
class BordaServiceTest extends TestCase
{
    private BordaService $borda;

    protected function setUp(): void
    {
        parent::setUp();
        $this->borda = new BordaService;
    }

    private function byId(array $result): array
    {
        return array_column($result['items'], null, 'id');
    }

    #[Test]
    public function ut08_dataset_v1_poin_dan_urutan_sesuai_kunci(): void
    {
        $expected = DatasetV1::expected();
        $votes = array_values($expected['votes']);
        $groupV = array_map(fn ($row) => $row['v'], $expected['topsis']['kelompok']);

        $result = $this->borda->aggregate($votes, $groupV);

        $this->assertSame($expected['borda']['final_order'], array_column($result['items'], 'id'));
        foreach ($this->byId($result) as $id => $item) {
            $this->assertSame($expected['borda']['points'][$id], $item['points'], "poin {$id}");
            $this->assertNull($item['tie_break_rule'], "{$id} tidak seri");
        }
        $this->assertSame(21, $result['items'][0]['points']);
        $this->assertSame(84, array_sum(array_column($result['items'], 'points')));
        $this->assertFalse($result['needs_deliberation']);
    }

    #[Test]
    public function ut09_ilustrasi_seri_diselesaikan_v_kelompok(): void
    {
        // Bab 5.3: suara melingkar, ketiga kasus 3 poin.
        $votes = [['A', 'B', 'C'], ['B', 'C', 'A'], ['C', 'A', 'B']];
        $result = $this->borda->aggregate($votes, ['A' => 0.6120, 'B' => 0.5874, 'C' => 0.4410]);

        $this->assertSame(['A', 'B', 'C'], array_column($result['items'], 'id'));
        $this->assertSame([3, 3, 3], array_column($result['items'], 'points'));
        foreach ($result['items'] as $item) {
            $this->assertSame(TieBreakRule::VKelompok, $item['tie_break_rule']);
        }
        $this->assertFalse($result['needs_deliberation']);
    }

    #[Test]
    public function ut10_seri_poin_dan_v_diselesaikan_jumlah_peringkat_pertama(): void
    {
        // A: posisi 1,1,4 = 6 poin (dua kali peringkat 1); B: posisi 2,2,2 = 6 poin (nol kali).
        $votes = [['A', 'B', 'C', 'D'], ['A', 'B', 'D', 'C'], ['C', 'B', 'D', 'A']];
        $result = $this->borda->aggregate($votes, ['A' => 0.5, 'B' => 0.5, 'C' => 0.4, 'D' => 0.3]);
        $items = $this->byId($result);

        $this->assertSame(['A', 'B', 'C', 'D'], array_column($result['items'], 'id'));
        $this->assertSame([6, 6, 4, 2], array_column($result['items'], 'points'));
        $this->assertSame(TieBreakRule::JumlahPeringkat1, $items['A']['tie_break_rule']);
        $this->assertSame(TieBreakRule::JumlahPeringkat1, $items['B']['tie_break_rule']);
        $this->assertNull($items['C']['tie_break_rule']);
        $this->assertFalse($result['needs_deliberation']);
    }

    #[Test]
    public function ut11_seri_di_semua_tingkat_perlu_musyawarah(): void
    {
        $votes = [['A', 'B', 'C'], ['B', 'C', 'A'], ['C', 'A', 'B']];
        $result = $this->borda->aggregate($votes, ['A' => 0.5, 'B' => 0.5, 'C' => 0.5]);

        $this->assertTrue($result['needs_deliberation']);
        foreach ($result['items'] as $item) {
            $this->assertSame(TieBreakRule::Musyawarah, $item['tie_break_rule']);
        }
    }

    #[Test]
    public function menolak_suara_tidak_lengkap_atau_ganda(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->borda->aggregate([['A', 'B', 'C'], ['A', 'A', 'B']], ['A' => 0.3, 'B' => 0.2, 'C' => 0.1]);
    }
}
