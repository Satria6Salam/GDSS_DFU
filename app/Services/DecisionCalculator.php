<?php

namespace App\Services;

use App\Enums\ResultScope;
use App\Models\AhpResult;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\DecisionSession;
use App\Models\TopsisResult;
use Illuminate\Support\Collection;

/**
 * Jembatan antara data penilaian di basis data dan service perhitungan murni (Bab 5, 11.1).
 * Menghitung lalu menyimpan hasil AHP/TOPSIS individual dan kelompok.
 */
class DecisionCalculator
{
    public function __construct(
        private readonly AhpService $ahp,
        private readonly TopsisService $topsis,
    ) {}

    /** @return Collection<int, Criterion> kriteria berurutan C1 → C6 */
    public function criteria(): Collection
    {
        return Criterion::ofVersion(config('gdss.criteria_version'))->get()->values();
    }

    /** Matriks perbandingan berpasangan milik satu penilaian. */
    public function ahpMatrix(Assessment $assessment): array
    {
        $index = $this->criteria()->pluck('id')->flip();
        $pairs = $assessment->judgments()->get()->map(fn ($j) => [
            'i' => $index[$j->criterion_i_id],
            'j' => $index[$j->criterion_j_id],
            'value' => $j->value,
        ])->all();

        return $this->ahp->buildMatrix($pairs, $index->count());
    }

    /** Analisis AHP satu penilaian (bobot + konsistensi). */
    public function analyzeAhp(Assessment $assessment): array
    {
        return $this->ahp->analyze($this->ahpMatrix($assessment));
    }

    /** Matriks keputusan: case_id (urut kode kasus) => skor per kriteria. */
    public function decisionMatrix(Assessment $assessment): array
    {
        $criteria = $this->criteria()->pluck('id')->values();
        $scores = $assessment->scores()->get()->groupBy('case_id');
        $cases = $assessment->session->cases()->pluck('id');

        $matrix = [];
        foreach ($cases as $caseId) {
            $row = $scores->get($caseId, collect())->keyBy('criterion_id');
            $matrix[$caseId] = $criteria->map(fn ($id) => (float) $row[$id]->score)->all();
        }

        return $matrix;
    }

    private function types(): array
    {
        return $this->criteria()->map->type->all();
    }

    /**
     * Hitung dan simpan AHP + TOPSIS individual (A3). Hasil lama milik DM ini diganti.
     *
     * @param  array<int, int>  $tiePriority  case_id => prioritas bila V sama (Bab 5.5)
     */
    public function storeIndividual(Assessment $assessment, array $tiePriority = []): array
    {
        $analysis = $this->analyzeAhp($assessment);
        $ranking = $this->topsis->rank($this->decisionMatrix($assessment), $analysis['weights'], $this->types(), $tiePriority);

        $this->clearIndividual($assessment);
        $this->saveResults($assessment->session, ResultScope::Individual, $assessment->user_id, $analysis, $ranking);

        return ['ahp' => $analysis, 'topsis' => $ranking];
    }

    /** Hapus hasil individual satu DM (A4 tarik kembali). */
    public function clearIndividual(Assessment $assessment): void
    {
        AhpResult::where('session_id', $assessment->session_id)->where('scope', ResultScope::Individual)
            ->where('user_id', $assessment->user_id)->delete();
        TopsisResult::where('session_id', $assessment->session_id)->where('scope', ResultScope::Individual)
            ->where('user_id', $assessment->user_id)->delete();
    }

    /**
     * Hitung dan simpan AHP kelompok (AIJ) dan TOPSIS kelompok (S2).
     *
     * @param  Collection<int, Assessment>  $assessments
     * @return array<int, array{d_plus: float, d_minus: float, v: float, rank: int}> case_id => hasil kelompok
     */
    public function storeGroup(DecisionSession $session, Collection $assessments): array
    {
        $matrices = $assessments->map(fn (Assessment $a) => $this->ahpMatrix($a))->values()->all();
        $analysis = $this->ahp->analyze($this->ahp->aggregate($matrices));

        $decision = $this->topsis->averageMatrices($assessments->map(fn (Assessment $a) => $this->decisionMatrix($a))->values()->all());
        $ranking = $this->topsis->rank($decision, $analysis['weights'], $this->types());

        $this->clearGroup($session);
        $this->saveResults($session, ResultScope::Kelompok, null, $analysis, $ranking);

        return $ranking;
    }

    public function clearGroup(DecisionSession $session): void
    {
        AhpResult::where('session_id', $session->id)->where('scope', ResultScope::Kelompok)->delete();
        TopsisResult::where('session_id', $session->id)->where('scope', ResultScope::Kelompok)->delete();
    }

    /** Urutan case_id dari peringkat TOPSIS individual seorang DM (bahan suara, D04). */
    public function individualRanking(DecisionSession $session, int $userId): array
    {
        return TopsisResult::where('session_id', $session->id)->where('scope', ResultScope::Individual)
            ->where('user_id', $userId)->orderBy('rank')->pluck('case_id')->all();
    }

    /** V TOPSIS kelompok: case_id => v. */
    public function groupV(DecisionSession $session): array
    {
        return TopsisResult::where('session_id', $session->id)->where('scope', ResultScope::Kelompok)
            ->pluck('v', 'case_id')->map(fn ($v) => (float) $v)->all();
    }

    private function saveResults(DecisionSession $session, ResultScope $scope, ?int $userId, array $analysis, array $ranking): void
    {
        $result = AhpResult::create([
            'session_id' => $session->id,
            'scope' => $scope,
            'user_id' => $userId,
            'lambda_max' => $analysis['lambda_max'],
            'ci' => $analysis['ci'],
            'cr' => $analysis['cr'],
        ]);

        foreach ($this->criteria() as $index => $criterion) {
            $result->weights()->create(['criterion_id' => $criterion->id, 'weight' => $analysis['weights'][$index]]);
        }

        foreach ($ranking as $caseId => $row) {
            TopsisResult::create([
                'session_id' => $session->id,
                'scope' => $scope,
                'user_id' => $userId,
                'case_id' => $caseId,
                ...$row,
            ]);
        }
    }
}
