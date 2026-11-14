<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** T01, T02, T18 dan NFR-01 (Bab 12.4). */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gdss.demo_passwords' => ['dokter' => 'uji-dokter', 'perawat' => 'uji-perawat', 'apoteker' => 'uji-apoteker']]);
        $this->seed();
        RateLimiter::clear('dokter|127.0.0.1');
    }

    #[Test]
    public function tamu_diarahkan_ke_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/login')->assertOk()->assertSee('Masuk ke akun Anda')->assertSee('DATA SIMULASI');
    }

    #[Test]
    public function t01_login_dengan_kredensial_benar(): void
    {
        $response = $this->post('/login', ['username' => 'dokter', 'password' => 'uji-dokter']);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs(User::where('username', 'dokter')->first());
        $this->assertDatabaseHas('audit_logs', ['action' => 'LOGIN']);

        $this->get('/dashboard')->assertOk()
            ->assertSee('Selamat datang, Dokter Demo')
            ->assertSee('Status Penilaian Anggota')
            ->assertSee('Eskalasi Segera')
            ->assertSee('Panel Ketua');
    }

    #[Test]
    public function login_salah_memberi_pesan_umum(): void
    {
        $this->from('/login')->post('/login', ['username' => 'dokter', 'password' => 'salah'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['username' => 'Username atau password salah.']);

        $this->from('/login')->post('/login', ['username' => 'tidakada', 'password' => 'salah'])
            ->assertSessionHasErrors(['username' => 'Username atau password salah.']);

        $this->assertGuest();
        $this->assertSame(2, AuditLog::where('action', 'LOGIN_FAILED')->count());
    }

    #[Test]
    public function t18_percobaan_keenam_dalam_satu_menit_diblokir(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', ['username' => 'dokter', 'password' => 'salah'])
                ->assertSessionHasErrors(['username' => 'Username atau password salah.']);
        }

        $response = $this->post('/login', ['username' => 'dokter', 'password' => 'uji-dokter']);

        $response->assertSessionHasErrors('username');
        $this->assertStringContainsString('Terlalu banyak percobaan', session('errors')->first('username'));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'LOGIN_THROTTLED']);
    }

    #[Test]
    public function anggota_tidak_melihat_menu_ketua(): void
    {
        $this->actingAs(User::where('username', 'perawat')->first())
            ->get('/dashboard')->assertOk()
            ->assertSee('Penilaian Saya')
            ->assertDontSee('Panel Ketua')
            ->assertDontSee('Status Penilaian Anggota');
    }

    #[Test]
    public function t02_route_ketua_menolak_anggota(): void
    {
        Route::middleware(['web', 'auth', 'leader'])->get('/_uji-ketua', fn () => 'ok');

        $this->actingAs(User::where('username', 'apoteker')->first())
            ->get('/_uji-ketua')->assertForbidden()->assertSee('Akses ditolak');
        $this->assertDatabaseHas('audit_logs', ['action' => 'ACCESS_DENIED']);

        $this->actingAs(User::where('username', 'dokter')->first())
            ->get('/_uji-ketua')->assertOk()->assertSee('ok');
    }

    #[Test]
    public function logout_mengakhiri_sesi(): void
    {
        $this->actingAs(User::where('username', 'dokter')->first())
            ->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'LOGOUT']);
    }

    #[Test]
    public function pengguna_login_yang_membuka_login_diarahkan_ke_dashboard(): void
    {
        $this->actingAs(User::where('username', 'dokter')->first())
            ->get('/login')->assertRedirect(route('dashboard'));
    }
}
