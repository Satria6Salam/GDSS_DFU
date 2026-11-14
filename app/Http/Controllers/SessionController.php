<?php

namespace App\Http\Controllers;

use App\Enums\ConsensusStatus;
use App\Enums\SessionStatus;
use App\Models\DecisionSession;
use App\Services\SessionWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** H03 Daftar sesi & riwayat keputusan (FR-03, FR-18) dan pembuatan sesi baru (S0). */
class SessionController extends Controller
{
    public function __construct(private readonly SessionWorkflowService $workflow) {}

    public function index(Request $request): View
    {
        $sessions = DecisionSession::with(['creator', 'finalizer'])
            ->withCount('cases')
            ->with(['consensusResults' => fn ($q) => $q->where('status', ConsensusStatus::Final)->with('items.patientCase')])
            ->latest('id')->get();

        return view('sessions.index', [
            'sessions' => $sessions,
            'canCreate' => $request->user()->isLeader()
                && ! DecisionSession::where('status', '!=', SessionStatus::Final)->exists(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:150']], [
            'title.required' => 'Judul sesi wajib diisi.',
        ]);

        $session = $this->workflow->createSession($request->user(), $data['title']);

        return redirect()->route('cases.index', $session)->with('success', 'Sesi baru dibuat. Tambahkan kasus atau impor dataset sebelum membuka penilaian.');
    }
}
