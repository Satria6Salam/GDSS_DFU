<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentStatus;
use App\Enums\ConsensusStatus;
use App\Enums\SessionStatus;
use App\Enums\VoteStatus;
use App\Models\DecisionSession;
use App\Models\User;
use Illuminate\View\View;

/**
 * H09 Panel Ketua (Bab 10.3): status anggota, aksi transisi, dan ringkasan hasil.
 * Sebelum FINAL ketua hanya melihat status dan total poin, bukan isi penilaian atau suara anggota (D13, Bab 6.3).
 */
class LeaderPanelController extends Controller
{
    public function show(DecisionSession $session): View
    {
        $assessments = $session->assessments()->get()->keyBy('user_id');
        $votes = $session->votes()->where('status', VoteStatus::Terkirim)->get()->keyBy('user_id');

        $members = User::orderByDesc('is_leader')->orderBy('id')->get()->map(fn (User $user) => [
            'user' => $user,
            'assessment' => $assessments->get($user->id),
            'status' => $assessments->get($user->id)?->status ?? AssessmentStatus::Belum,
            'vote' => $votes->get($user->id),
        ]);

        $consensus = $session->consensusResults()->where('status', '!=', ConsensusStatus::Dibatalkan)
            ->latest('id')->with('items.patientCase.redFlags')->first();

        return view('control.show', [
            'session' => $session,
            'members' => $members,
            'waiting' => $members->filter(fn ($m) => $m['status'] !== AssessmentStatus::Final && $m['status'] !== AssessmentStatus::Terkunci),
            'caseCount' => $session->cases()->count(),
            'redFlagCases' => $session->cases()->has('redFlags')->with('redFlags')->get(),
            'consensus' => in_array($session->status, [SessionStatus::MenungguFinalisasi, SessionStatus::Final], true) ? $consensus : null,
            'logs' => $session->auditLogs()->with('user')->latest('id')->limit(8)->get(),
        ]);
    }
}
