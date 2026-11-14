<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentStatus;
use App\Enums\ResultScope;
use App\Enums\SessionStatus;
use App\Models\AhpResult;
use App\Models\Assessment;
use App\Models\DecisionSession;
use App\Models\PatientCase;
use App\Models\TopsisResult;
use App\Services\DecisionCalculator;
use App\Services\SessionWorkflowService;
use App\Support\SaatyChoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Penilaian milik pengguna yang sedang login (H06, H07, H08). Tidak ada id penilaian di URL,
 * sehingga DM tidak dapat membuka penilaian anggota lain (D13, T17).
 */
class AssessmentController extends Controller
{
    public function __construct(
        private readonly SessionWorkflowService $workflow,
        private readonly DecisionCalculator $calculator,
    ) {}

    private function assessmentFor(Request $request, DecisionSession $session): ?Assessment
    {
        return Assessment::with('session')->where('session_id', $session->id)->where('user_id', $request->user()->id)->first();
    }

    /** Data bersama ketiga tab: status, kelengkapan, dan apakah form dapat diubah. */
    private function common(DecisionSession $session, ?Assessment $assessment): array
    {
        $criteria = $this->calculator->criteria();

        return [
            'session' => $session,
            'assessment' => $assessment,
            'criteria' => $criteria,
            'editable' => $assessment && $session->isStatus(SessionStatus::Penilaian) && $assessment->isEditable(),
            'pairsFilled' => $assessment?->judgments()->count() ?? 0,
            'scoresFilled' => $assessment?->scores()->count() ?? 0,
            'scoresTarget' => $session->cases()->count() * $criteria->count(),
            'redFlagCases' => $session->cases()->has('redFlags')->with('redFlags')->get(),
        ];
    }

    // ------------------------------------------------------------- H06 AHP
    public function editAhp(Request $request, DecisionSession $session): View
    {
        $assessment = $this->assessmentFor($request, $session);
        $criteria = $this->calculator->criteria();

        $current = $assessment?->judgments()->get()
            ->mapWithKeys(fn ($j) => ["{$j->criterion_i_id}_{$j->criterion_j_id}" => SaatyChoice::fromValue($j->value)])
            ->all() ?? [];

        $pairs = [];
        foreach ($criteria as $i => $left) {
            foreach ($criteria->slice($i + 1) as $right) {
                $pairs[] = ['left' => $left, 'right' => $right, 'key' => "{$left->id}_{$right->id}"];
            }
        }

        return view('assessment.ahp', [
            ...$this->common($session, $assessment),
            'pairs' => $pairs,
            'current' => $current,
            'choices' => SaatyChoice::options(),
        ]);
    }

    public function updateAhp(Request $request, DecisionSession $session): RedirectResponse
    {
        $assessment = $this->assessmentFor($request, $session) ?? abort(404);
        $data = $request->validate([
            'pairs' => ['array'],
            'pairs.*' => ['nullable', Rule::in(SaatyChoice::options())],
        ], ['pairs.*.in' => 'Pilihan skala Saaty tidak valid.']);

        $criteriaIds = $this->calculator->criteria()->pluck('id')->all();
        $pairs = [];
        foreach (array_filter($data['pairs'] ?? []) as $key => $choice) {
            [$i, $j] = array_map('intval', explode('_', (string) $key) + [1 => 0]);
            $posI = array_search($i, $criteriaIds, true);
            $posJ = array_search($j, $criteriaIds, true);
            if ($posI === false || $posJ === false || $posI >= $posJ) {
                return back()->with('error', 'Pasangan kriteria tidak valid.');
            }
            $pairs[] = ['criterion_i_id' => $i, 'criterion_j_id' => $j, 'value' => SaatyChoice::toValue($choice)];
        }

        $this->workflow->saveJudgments($assessment, $request->user(), $pairs);

        return $request->input('next') === 'scores'
            ? redirect()->route('assessment.scores', $session)->with('success', 'Perbandingan kriteria disimpan sebagai draf.')
            : back()->with('success', 'Penilaian disimpan sebagai draf.');
    }

    // ------------------------------------------------------------- H07 skor
    public function editScores(Request $request, DecisionSession $session): View
    {
        $assessment = $this->assessmentFor($request, $session);
        $cases = $session->cases()->with('redFlags')->get();
        $case = $cases->firstWhere('kode_kasus', $request->query('case')) ?? $cases->first();

        $scores = $assessment?->scores()->get() ?? collect();
        $criteriaCount = $this->calculator->criteria()->count();

        return view('assessment.scores', [
            ...$this->common($session, $assessment),
            'cases' => $cases,
            'case' => $case,
            'current' => $scores->where('case_id', $case?->id)->pluck('score', 'criterion_id')->all(),
            'completeCases' => $scores->groupBy('case_id')->filter(fn ($rows) => $rows->count() === $criteriaCount)->keys()->all(),
            'nextCase' => $case ? $cases->skipUntil(fn (PatientCase $c) => $c->is($case))->skip(1)->first() : null,
        ]);
    }

    public function updateScores(Request $request, DecisionSession $session): RedirectResponse
    {
        $assessment = $this->assessmentFor($request, $session) ?? abort(404);
        $criteriaIds = $this->calculator->criteria()->pluck('id')->all();
        $data = $request->validate([
            'case_id' => ['required', 'integer'],
            'scores' => ['array'],
            'scores.*' => ['nullable', 'integer', 'between:1,5'],
        ], ['scores.*.between' => 'Skor harus bilangan bulat 1–5.', 'scores.*.integer' => 'Skor harus bilangan bulat 1–5.']);

        $scores = array_filter($data['scores'] ?? [], fn ($v) => $v !== null);
        if (array_diff(array_map('intval', array_keys($scores)), $criteriaIds) !== []) {
            return back()->with('error', 'Kriteria tidak valid.');
        }

        $this->workflow->saveScores($assessment, $request->user(), (int) $data['case_id'], array_map('intval', $scores));

        $next = $request->input('next');

        return $next
            ? redirect()->route('assessment.scores', [$session, 'case' => $next])->with('success', 'Skor disimpan sebagai draf.')
            : back()->with('success', 'Skor disimpan sebagai draf.');
    }

    // ------------------------------------------------------------- H08 ringkasan & hasil
    public function result(Request $request, DecisionSession $session): View
    {
        $assessment = $this->assessmentFor($request, $session);
        $user = $request->user();
        $common = $this->common($session, $assessment);

        $ahp = AhpResult::with('weights.criterion')->where('session_id', $session->id)
            ->where('scope', ResultScope::Individual)->where('user_id', $user->id)->first();
        $ranking = TopsisResult::with('patientCase.redFlags')->where('session_id', $session->id)
            ->where('scope', ResultScope::Individual)->where('user_id', $user->id)->orderBy('rank')->get();

        // CR terkini untuk ringkasan (dihitung ulang bila 15 pasang sudah lengkap).
        $liveCr = $assessment && $common['pairsFilled'] === Assessment::PAIR_COUNT
            ? $this->calculator->analyzeAhp($assessment)['cr']
            : null;

        return view('assessment.result', [
            ...$common,
            'missing' => $assessment ? $this->workflow->missingInputs($assessment) : null,
            'liveCr' => $liveCr,
            'ahp' => $ahp,
            'ranking' => $ranking,
            'canWithdraw' => $assessment?->status === AssessmentStatus::Final && $session->isStatus(SessionStatus::Penilaian),
        ]);
    }

    public function submit(Request $request, DecisionSession $session): RedirectResponse
    {
        $assessment = $this->assessmentFor($request, $session) ?? abort(404);
        $this->workflow->submitAssessment($assessment, $request->user());

        return redirect()->route('assessment.result', $session)
            ->with('success', 'Penilaian final terkirim. Hasil Anda dapat dilihat di bawah.');
    }

    public function withdraw(Request $request, DecisionSession $session): RedirectResponse
    {
        $assessment = $this->assessmentFor($request, $session) ?? abort(404);
        $this->workflow->withdrawAssessment($assessment, $request->user());

        return back()->with('info', 'Penilaian dikembalikan ke draf. Kirim ulang sebelum voting dimulai.');
    }
}
