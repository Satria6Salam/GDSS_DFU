<?php

namespace App\Http\Controllers;

use App\Enums\RedFlagCode;
use App\Models\DecisionSession;
use App\Models\PatientCase;
use App\Services\CaseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Red flag manual oleh ketua (Bab 3.5, FR-06, T16). */
class RedFlagController extends Controller
{
    public function __construct(private readonly CaseManager $cases) {}

    public function store(Request $request, DecisionSession $session, PatientCase $case): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', Rule::enum(RedFlagCode::class)],
            'note' => ['required', 'string', 'max:1000'],
        ], [
            'code.required' => 'Pilih kode red flag.',
            'note.required' => 'Catatan red flag manual wajib diisi.',
        ]);

        $flag = $this->cases->addManualFlag($case, $request->user(), RedFlagCode::from($data['code']), $data['note']);

        return back()->with('success', "Red flag {$flag->code->value} ditambahkan pada {$case->kode_kasus}.");
    }
}
