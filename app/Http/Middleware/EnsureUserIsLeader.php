<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Membatasi route khusus ketua tim (is_leader) — Bab 6.4. Penolakan dicatat di audit log. */
class EnsureUserIsLeader
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isLeader()) {
            $this->audit->log('ACCESS_DENIED', meta: ['path' => $request->path(), 'reason' => 'bukan ketua tim']);
            abort(403, 'Anda tidak memiliki akses ke fitur ini.');
        }

        return $next($request);
    }
}
