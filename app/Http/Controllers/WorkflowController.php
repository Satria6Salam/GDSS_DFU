<?php

namespace App\Http\Controllers;

use App\Enums\TieBreakRule;
use App\Models\DecisionSession;
use App\Services\SessionWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Aksi ketua yang memindahkan status sesi (S1, S2, S4, S5, musyawarah) — Bab 7.5, 11.3.
 * Penolakan syarat (WorkflowException) diubah menjadi pesan galat di bootstrap/app.php.
 */
class WorkflowController extends Controller
{
    public function __construct(private readonly SessionWorkflowService $workflow) {}

    public function openAssessment(Request $request, DecisionSession $session): RedirectResponse
    {
        $this->workflow->openAssessment($session, $request->user());

        return back()->with('success', 'Penilaian dibuka. Kasus dan kriteria kini terkunci.');
    }

    public function startVoting(Request $request, DecisionSession $session): RedirectResponse
    {
        $this->workflow->startVoting($session, $request->user());

        return back()->with('success', 'Voting dimulai. Semua penilaian kini terkunci.');
    }

    public function deliberate(Request $request, DecisionSession $session): RedirectResponse
    {
        $data = $request->validate([
            'positions' => ['required', 'array'],
            'positions.*' => ['required', 'integer', 'min:1'],
            'note' => ['required', 'string', 'max:1000'],
        ], [
            'note.required' => 'Alasan musyawarah wajib diisi.',
            'positions.*.required' => 'Posisi setiap kasus yang seri wajib dipilih.',
        ]);

        // Susun urutan lengkap: kasus tidak seri tetap di tempatnya, kasus seri mengikuti posisi pilihan ketua.
        $items = $this->workflow->activeConsensus($session)->items()->get();
        $order = [];
        foreach ($items as $item) {
            $rank = $item->tie_break_rule === TieBreakRule::Musyawarah
                ? (int) ($data['positions'][$item->case_id] ?? 0)
                : $item->final_rank;
            $order[$rank][] = $item->case_id;
        }
        ksort($order);
        $flat = array_merge(...array_values($order));
        if (count($order) !== $items->count()) {
            return back()->withInput()->with('error', 'Setiap kasus seri harus menempati posisi yang berbeda.');
        }

        $this->workflow->deliberate($session, $request->user(), $flat, $data['note']);

        return back()->with('success', 'Urutan kasus yang seri telah ditetapkan.');
    }

    public function finalize(Request $request, DecisionSession $session): RedirectResponse
    {
        $data = $request->validate(['conclusion_note' => ['nullable', 'string', 'max:3000']]);
        $this->workflow->finalize($session, $request->user(), $data['conclusion_note'] ?? null);

        return redirect()->route('consensus.show', $session)->with('success', 'Hasil konsensus telah dipublikasikan kepada semua anggota.');
    }

    public function reopen(Request $request, DecisionSession $session): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], [
            'reason.required' => 'Alasan membuka ulang sesi wajib diisi.',
        ]);

        $this->workflow->reopen($session, $request->user(), $data['reason']);

        return back()->with('success', 'Sesi dibuka ulang. Semua suara dibatalkan dan anggota dapat merevisi penilaian.');
    }
}
