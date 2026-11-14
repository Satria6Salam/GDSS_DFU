<?php

namespace App\Services;

use App\Models\ConsensusResult;
use Illuminate\Support\Collection;

/**
 * Menyusun kesimpulan hasil konsensus secara berbasis aturan (tanpa teks bebas).
 *
 * Batasan (Laporan 1.6, Bab 1.6 dokumen spesifikasi): keluaran berupa PRIORITAS dan FOKUS EVALUASI,
 * bukan diagnosis, resep, atau rencana terapi. Rencana penanganan nyata ditulis ketua sebagai catatan kesimpulan.
 */
class ConclusionService
{
    /** Skor rata-rata kelompok dianggap menonjol bila ≥ 3,5 (antara "sedang" dan "berat" pada rubrik 1–5). */
    public const PROMINENT_SCORE = 3.5;

    /** Fokus evaluasi per kriteria, mengikuti ranah pedoman IWGDF/IDSA (Bab 3.1). */
    public const FOCUS = [
        'C1' => 'Penilaian infeksi: derajat infeksi dan tanda sistemik (IWGDF/IDSA).',
        'C2' => 'Penilaian perfusi/vaskular: status ABI, nadi kaki, tanda iskemia.',
        'C3' => 'Penilaian luka: luas, kedalaman, dan keterlibatan jaringan dalam.',
        'C4' => 'Penilaian neuropati dan proteksi kaki, termasuk kebutuhan pengurangan tekanan.',
        'C5' => 'Tinjauan kondisi sistemik bersama apoteker: kontrol glikemik dan fungsi ginjal.',
        'C6' => 'Pemantauan perkembangan luka dengan interval yang lebih rapat.',
    ];

    public const ACTION = [
        'red' => 'Evaluasi segera oleh dokter sesuai protokol eskalasi, tanpa menunggu antrean prioritas.',
        'tinggi' => 'Jadwalkan evaluasi klinis paling awal dan tinjau bersama tim multidisiplin.',
        'sedang' => 'Evaluasi setelah kelompok prioritas tinggi; pantau perubahan kondisi.',
        'rendah' => 'Lanjutkan perawatan dan pemantauan rutin; evaluasi ulang bila kondisi berubah.',
    ];

    /**
     * @param  Collection  $criteria  kriteria berurutan (code, name, id)
     * @param  array<int, array<int, float>>  $meanScores  case_id => [criterion_id => rata-rata skor tiga DM]
     * @param  array<int, float>  $groupWeights  criterion_id => bobot AHP kelompok (pemecah urutan alasan)
     * @param  array<int, array<int, int>>  $positions  user_id => [case_id => posisi 0-based]
     */
    public function build(ConsensusResult $consensus, Collection $criteria, array $meanScores, array $groupWeights,
        array $positions, int $voterCount): array
    {
        $items = $consensus->items->sortBy('final_rank')->values();
        $n = $items->count();
        $maxPoints = ($n - 1) * $voterCount;
        $highCut = (int) ceil($n / 3);
        $midCut = (int) ceil(2 * $n / 3);

        $cases = $items->map(function ($item) use ($criteria, $meanScores, $groupWeights, $positions, $highCut, $midCut, $maxPoints) {
            $case = $item->patientCase;
            $tier = $item->final_rank <= $highCut ? 'tinggi' : ($item->final_rank <= $midCut ? 'sedang' : 'rendah');
            $firsts = collect($positions)->filter(fn ($p) => ($p[$item->case_id] ?? null) === 0)->count();

            $reasons = $criteria->map(fn ($c) => [
                'code' => $c->code,
                'name' => $c->name,
                'mean' => $meanScores[$item->case_id][$c->id] ?? 0.0,
                'weight' => $groupWeights[$c->id] ?? 0.0,
            ])->sortBy([['mean', 'desc'], ['weight', 'desc']])->values();
            $prominent = $reasons->filter(fn ($r) => $r['mean'] >= self::PROMINENT_SCORE)->take(3)->values();

            return [
                'rank' => $item->final_rank,
                'code' => $case->kode_kasus,
                'tier' => $tier,
                'points' => $item->borda_points,
                'max_points' => $maxPoints,
                'firsts' => $firsts,
                'red_flags' => $case->redFlags->map(fn ($f) => $f->code->value.' '.mb_strtolower($f->code->label()))->all(),
                'reasons' => $prominent->all(),
                'focus' => $prominent->map(fn ($r) => self::FOCUS[$r['code']] ?? null)->filter()->values()->all(),
                'pharmacy' => $this->pharmacyNotes($case),
                'action' => $case->redFlags->isNotEmpty() ? self::ACTION['red'] : self::ACTION[$tier],
            ];
        });

        $top = $cases->first();
        $unanimousTop = $top && $top['firsts'] === $voterCount;

        return [
            'headline' => $top ? sprintf(
                '%s menjadi prioritas evaluasi pertama dengan %d dari %d poin Borda%s.',
                $top['code'], $top['points'], $top['max_points'],
                $unanimousTop ? ', dipilih sebagai peringkat 1 oleh seluruh decision maker' : sprintf(', dipilih sebagai peringkat 1 oleh %d dari %d decision maker', $top['firsts'], $voterCount)
            ) : null,
            'agreement' => $this->agreement($positions, $items->pluck('case_id')->all(), $highCut),
            'urgent' => $cases->filter(fn ($c) => $c['red_flags'] !== [])->values()->all(),
            'tiers' => [
                'tinggi' => $cases->where('tier', 'tinggi')->values()->all(),
                'sedang' => $cases->where('tier', 'sedang')->values()->all(),
                'rendah' => $cases->where('tier', 'rendah')->values()->all(),
            ],
            'cases' => $cases->all(),
        ];
    }

    /** Seberapa sepakat DM tentang kelompok prioritas tinggi (irisan tiga teratas tiap DM). */
    private function agreement(array $positions, array $caseIds, int $highCut): string
    {
        if ($positions === []) {
            return '';
        }
        $tops = array_map(fn ($p) => array_keys(array_filter($p, fn ($pos) => $pos < $highCut)), $positions);
        $shared = count(array_intersect(...array_values($tops)));

        return sprintf('%d dari %d kasus prioritas tinggi masuk %d besar pada suara semua decision maker.', $shared, $highCut, $highCut);
    }

    /** Catatan untuk tinjauan apoteker yang dibaca dari data kasus; bukan rekomendasi obat. */
    private function pharmacyNotes($case): array
    {
        $notes = [];
        if ($case->alergi_obat && mb_strtolower($case->alergi_obat) !== 'tidak ada') {
            $notes[] = "Riwayat alergi {$case->alergi_obat}: perhatikan saat dokter memilih terapi.";
        }
        if ($case->egfr < 30) {
            $notes[] = "eGFR {$case->egfr}: fungsi ginjal menurun berat, tinjau obat rutin ({$case->obat_rutin}).";
        } elseif ($case->egfr < 60) {
            $notes[] = "eGFR {$case->egfr}: fungsi ginjal menurun, pertimbangkan dalam tinjauan obat.";
        }

        return $notes;
    }
}
