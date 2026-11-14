<?php

namespace App\Http\Controllers;

use App\Models\DecisionSession;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** H12 Audit log (FR-19, Bab 7.7) — hanya ketua. Log hanya dibaca, tidak dapat diubah. */
class AuditLogController extends Controller
{
    public function index(Request $request, DecisionSession $session): View
    {
        $query = $session->auditLogs()->with('user')->latest('id');
        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        return view('audit.index', [
            'session' => $session,
            'logs' => $query->paginate(25)->withQueryString(),
            'actions' => $session->auditLogs()->distinct()->orderBy('action')->pluck('action'),
            'selected' => $action,
        ]);
    }
}
