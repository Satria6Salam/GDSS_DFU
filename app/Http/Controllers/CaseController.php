<?php

namespace App\Http\Controllers;

use App\Enums\RedFlagCode;
use App\Enums\SessionStatus;
use App\Enums\WoundProgress;
use App\Http\Requests\StoreCaseRequest;
use App\Models\DecisionSession;
use App\Models\PatientCase;
use App\Services\CaseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * H04 Kelola kasus dan H05 detail kasus (FR-04, FR-05, FR-06).
 * Anggota melihat kasus sejak tahap PENILAIAN; ketua dapat mengubah kasus hanya saat PERSIAPAN.
 */
class CaseController extends Controller
{
    public function __construct(private readonly CaseManager $cases) {}

    private function hiddenFromMember(Request $request, DecisionSession $session): bool
    {
        return ! $request->user()->isLeader() && $session->isStatus(SessionStatus::Persiapan);
    }

    public function index(Request $request, DecisionSession $session): View
    {
        return view('cases.index', [
            'session' => $session,
            'hidden' => $this->hiddenFromMember($request, $session),
            'cases' => $session->cases()->with('redFlags')->get(),
            'editable' => $request->user()->isLeader() && $session->isStatus(SessionStatus::Persiapan),
        ]);
    }

    public function show(Request $request, DecisionSession $session, PatientCase $case): View
    {
        abort_if($this->hiddenFromMember($request, $session), 403, 'Data kasus dapat dilihat setelah ketua membuka tahap penilaian.');

        return view('cases.show', [
            'session' => $session,
            'case' => $case->load('redFlags.creator'),
            'editable' => $request->user()->isLeader() && $session->isStatus(SessionStatus::Persiapan),
            'canFlag' => $request->user()->isLeader() && ! $session->isStatus(SessionStatus::Final),
            'flagCodes' => RedFlagCode::cases(),
        ]);
    }

    public function create(DecisionSession $session): View
    {
        $next = 'P'.str_pad((string) ($session->cases()->count() + 1), 2, '0', STR_PAD_LEFT);

        return view('cases.form', ['session' => $session, 'case' => new PatientCase(['kode_kasus' => $next]), 'progressOptions' => WoundProgress::cases()]);
    }

    public function store(StoreCaseRequest $request, DecisionSession $session): RedirectResponse
    {
        $case = $this->cases->create($session, $request->user(), $request->validated());

        return redirect()->route('cases.show', [$session, $case])->with('success', "Kasus {$case->kode_kasus} ditambahkan.");
    }

    public function edit(DecisionSession $session, PatientCase $case): View
    {
        return view('cases.form', ['session' => $session, 'case' => $case, 'progressOptions' => WoundProgress::cases()]);
    }

    public function update(StoreCaseRequest $request, DecisionSession $session, PatientCase $case): RedirectResponse
    {
        $this->cases->update($case, $request->user(), $request->validated());

        return redirect()->route('cases.show', [$session, $case])->with('success', "Kasus {$case->kode_kasus} diperbarui.");
    }

    public function destroy(Request $request, DecisionSession $session, PatientCase $case): RedirectResponse
    {
        $this->cases->delete($case, $request->user());

        return redirect()->route('cases.index', $session)->with('success', "Kasus {$case->kode_kasus} dihapus.");
    }

    public function import(Request $request, DecisionSession $session): RedirectResponse
    {
        $count = $this->cases->importDataset($session, $request->user());

        return back()->with('success', "{$count} kasus dataset ".config('gdss.dataset_version').' diimpor; red flag dievaluasi otomatis.');
    }
}
