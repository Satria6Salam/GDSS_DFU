<?php

namespace App\Http\Controllers;

use App\Services\SessionWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Pembuatan sesi keputusan baru (S0). Daftar sesi/riwayat (H03) menyusul. */
class SessionController extends Controller
{
    public function __construct(private readonly SessionWorkflowService $workflow) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:150']], [
            'title.required' => 'Judul sesi wajib diisi.',
        ]);

        $session = $this->workflow->createSession($request->user(), $data['title']);

        return redirect()->route('control.show', $session)->with('success', 'Sesi baru dibuat. Tambahkan kasus sebelum membuka penilaian.');
    }
}
