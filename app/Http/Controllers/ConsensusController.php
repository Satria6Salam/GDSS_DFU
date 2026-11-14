<?php

namespace App\Http\Controllers;

use App\Enums\ConsensusStatus;
use App\Enums\ResultScope;
use App\Enums\SessionStatus;
use App\Enums\VoteStatus;
use App\Models\AhpResult;
use App\Models\DecisionSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * H11 Hasil Konsensus (FR-17). Visibilitas mengikuti Bab 6.3:
 * - FINAL: semua DM melihat urutan, poin, rincian suara, dan bobot tiap DM;
 * - MENUNGGU FINALISASI: hanya ketua yang melihat total poin dan urutan;
 * - tahap lain: belum ada hasil.
 */
class ConsensusController extends Controller
{
    public function show(Request $request, DecisionSession $session): View
    {
        $user = $request->user();
        $isFinal = $session->isStatus(SessionStatus::Final);
        $canSeeTotals = $isFinal || ($user->isLeader() && $session->isStatus(SessionStatus::MenungguFinalisasi));

        $consensus = $canSeeTotals
            ? $session->consensusResults()->where('status', '!=', ConsensusStatus::Dibatalkan)->latest('id')
                ->with(['items.patientCase.redFlags', 'finalizer'])->first()
            : null;

        $members = User::orderByDesc('is_leader')->orderBy('id')->get();
        $positions = [];   // user_id => [case_id => posisi]
        $weights = collect();
        if ($isFinal) {
            foreach ($session->votes()->where('status', VoteStatus::Terkirim)->get() as $vote) {
                $positions[$vote->user_id] = array_flip(array_map('intval', $vote->ranking));
            }
            $weights = AhpResult::with('weights.criterion', 'user')->where('session_id', $session->id)->get()
                ->sortBy(fn ($r) => $r->scope === ResultScope::Kelompok ? 99 : $members->search(fn ($m) => $m->id === $r->user_id));
        }

        return view('consensus.show', [
            'session' => $session,
            'consensus' => $consensus,
            'isFinal' => $isFinal,
            'members' => $members,
            'positions' => $positions,
            'caseCount' => $session->cases()->count(),
            'weights' => $weights,
            'redFlagCases' => $session->cases()->has('redFlags')->with('redFlags')->get(),
        ]);
    }
}
