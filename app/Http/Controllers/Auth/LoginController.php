<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Criterion;
use App\Models\DecisionSession;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login dengan username + password (FR-01, D16) dan pembatasan percobaan (NFR-01, T18).
 */
class LoginController extends Controller
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    public function __construct(private readonly AuditLogger $audit) {}

    public function show(): View
    {
        $session = DecisionSession::latest('id')->first();

        return view('auth.login', [
            'stats' => [
                'users' => User::count(),
                'criteria' => Criterion::ofVersion(config('gdss.criteria_version'))->count(),
                'cases' => $session?->cases()->count() ?? 0,
            ],
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Username wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            $this->audit->log('LOGIN_THROTTLED', meta: ['username' => $credentials['username'], 'ip' => $request->ip()]);

            throw ValidationException::withMessages([
                'username' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        if (! Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password']])) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            $this->audit->log('LOGIN_FAILED', meta: ['username' => $credentials['username'], 'ip' => $request->ip()]);

            // Pesan umum: tidak menyebut apakah username atau password yang salah (Bab 6.5).
            throw ValidationException::withMessages(['username' => 'Username atau password salah.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $this->audit->log('LOGIN', meta: ['ip' => $request->ip()]);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->audit->log('LOGOUT');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Anda telah keluar.');
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('username')).'|'.$request->ip());
    }
}
