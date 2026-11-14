<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\DecisionSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** H02 Dashboard (Bab 10.3–10.4): ringkasan sesi aktif sesuai peran. */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        // Hanya satu sesi yang belum FINAL pada satu waktu (S0); bila tidak ada, tampilkan sesi terakhir.
        $session = DecisionSession::latest('id')->first();

        if (! $session) {
            return view('dashboard', ['session' => null, 'user' => $user]);
        }

        $assessments = $session->assessments()->get()->keyBy('user_id');
        $myAssessment = $assessments->get($user->id);

        // Status anggota hanya untuk ketua; isi penilaian tidak ditampilkan (D13, Bab 6.3).
        $members = User::orderByDesc('is_leader')->orderBy('id')->get()->map(fn (User $member) => [
            'user' => $member,
            'status' => $assessments->get($member->id)?->status ?? AssessmentStatus::Belum,
            'assessment' => $assessments->get($member->id),
        ]);

        $finalCount = $members->filter(fn ($m) => in_array($m['status'], [AssessmentStatus::Final, AssessmentStatus::Terkunci], true))->count();
        $redFlagCases = $session->cases()->has('redFlags')->with('redFlags')->get();

        return view('dashboard', [
            'user' => $user,
            'session' => $session,
            'members' => $members,
            'finalCount' => $finalCount,
            'waiting' => $members->filter(fn ($m) => $m['status'] !== AssessmentStatus::Final && $m['status'] !== AssessmentStatus::Terkunci)
                ->map(fn ($m) => $m['user']->name)->values(),
            'myAssessment' => $myAssessment,
            'myProgress' => $this->progress($myAssessment, $session),
            'redFlagCases' => $redFlagCases,
            'caseCount' => $session->cases()->count(),
            'criteriaCount' => Criterion::ofVersion(config('gdss.criteria_version'))->count(),
            'notifications' => $user->notifications()->latest()->limit(5)->get(),
        ]);
    }

    /** Kelengkapan isian penilaian pengguna: pasang AHP dan skor kasus. */
    private function progress(?Assessment $assessment, DecisionSession $session): array
    {
        $scoreTarget = $session->cases()->count() * Criterion::ofVersion(config('gdss.criteria_version'))->count();

        return [
            'pairs' => $assessment?->judgments()->count() ?? 0,
            'pairs_target' => Assessment::PAIR_COUNT,
            'scores' => $assessment?->scores()->count() ?? 0,
            'scores_target' => $scoreTarget,
        ];
    }
}
