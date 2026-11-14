<?php

namespace App\Http\Controllers;

use App\Enums\ResultScope;
use App\Enums\SessionStatus;
use App\Enums\VoteStatus;
use App\Models\DecisionSession;
use App\Models\TopsisResult;
use App\Services\SessionWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** H10 Voting (D04, V1): DM mengonfirmasi peringkat TOPSIS miliknya sebagai suara Borda. */
class VoteController extends Controller
{
    public function __construct(private readonly SessionWorkflowService $workflow) {}

    public function show(Request $request, DecisionSession $session): View
    {
        $user = $request->user();

        return view('vote.show', [
            'session' => $session,
            'vote' => $session->votes()->where('user_id', $user->id)->where('status', VoteStatus::Terkirim)->first(),
            'ranking' => TopsisResult::with('patientCase.redFlags')->where('session_id', $session->id)
                ->where('scope', ResultScope::Individual)->where('user_id', $user->id)->orderBy('rank')->get(),
            'caseCount' => $session->cases()->count(),
            'assessment' => $session->assessments()->where('user_id', $user->id)->first(),
            'redFlagCases' => $session->cases()->has('redFlags')->with('redFlags')->get(),
            'isOpen' => $session->isStatus(SessionStatus::Voting),
        ]);
    }

    public function store(Request $request, DecisionSession $session): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']], [
            'confirm.accepted' => 'Centang konfirmasi bahwa Anda telah meninjau urutan sebelum mengirim suara.',
        ]);

        $this->workflow->submitVote($session, $request->user());
        $session->refresh();

        return back()->with('success', $session->isStatus(SessionStatus::MenungguFinalisasi)
            ? 'Suara Anda tercatat. Semua suara sudah masuk; hasil menunggu finalisasi ketua.'
            : 'Suara Anda tercatat.');
    }
}
