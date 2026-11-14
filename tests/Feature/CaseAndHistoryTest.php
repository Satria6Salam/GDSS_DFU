<?php

namespace Tests\Feature;

use App\Enums\SessionStatus;
use App\Models\DecisionSession;
use App\Models\PatientCase;
use App\Models\User;
use App\Services\SessionWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Halaman kasus (H04/H05), riwayat (H03), dan audit log (H12): T15, T16, Bab 6.2–6.3. */
class CaseAndHistoryTest extends TestCase
{
    use RefreshDatabase;

    private DecisionSession $demo;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gdss.demo_passwords' => ['dokter' => 'a', 'perawat' => 'b', 'apoteker' => 'c']]);
        $this->seed();
        $this->demo = DecisionSession::firstOrFail();
    }

    private function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    /** Data kasus valid (salinan P01 dataset) dengan kode baru. */
    private function payload(array $override = []): array
    {
        $p01 = PatientCase::where('kode_kasus', 'P01')->firstOrFail();
        $data = Arr::only($p01->toArray(), PatientCase::CLINICAL_ATTRIBUTES);
        $data['tanda_inflamasi'] = $data['tanda_inflamasi'] ? '1' : '0';
        $data['nyeri_istirahat'] = $data['nyeri_istirahat'] ? '1' : '0';
        $data['gangren'] = $data['gangren'] ? '1' : '0';

        return array_merge($data, ['kode_kasus' => 'P09'], $override);
    }

    private function codes(PatientCase $case): array
    {
        return $case->fresh()->redFlags->map(fn ($f) => $f->code->value)->all();
    }

    #[Test]
    public function anggota_melihat_kasus_hanya_sejak_penilaian(): void
    {
        $p07 = PatientCase::where('kode_kasus', 'P07')->firstOrFail();

        $this->actingAs($this->user('perawat'))->get(route('cases.index', $this->demo))->assertOk()->assertSee('Kasus belum dibuka');
        $this->get(route('cases.show', [$this->demo, $p07]))->assertForbidden();

        $this->actingAs($this->user('dokter'))->get(route('cases.index', $this->demo))->assertOk()
            ->assertSee('Tambah Kasus')->assertSee('P07')->assertSee('RF1');

        app(SessionWorkflowService::class)->openAssessment($this->demo, $this->user('dokter'));
        $this->actingAs($this->user('perawat'))->get(route('cases.show', [$this->demo, $p07]))->assertOk()
            ->assertSee('Eskalasi Segera')->assertSee('Penisilin')->assertDontSee('Tambah Red Flag Manual');
    }

    #[Test]
    public function t15_validasi_rentang_data_kasus(): void
    {
        $this->actingAs($this->user('dokter'))
            ->post(route('cases.store', $this->demo), $this->payload(['abi' => '2,5', 'usia' => 15]))
            ->assertSessionHasErrors(['abi' => 'ABI harus antara 0.10 dan 1.80.', 'usia' => 'usia harus antara 18 dan 100.']);

        $this->post(route('cases.store', $this->demo), $this->payload(['kode_kasus' => 'P01']))
            ->assertSessionHasErrors(['kode_kasus' => 'Kode kasus sudah dipakai di sesi ini.']);
        $this->assertSame(8, $this->demo->cases()->count());
    }

    #[Test]
    public function tambah_dan_ubah_kasus_mengevaluasi_red_flag_otomatis(): void
    {
        $this->actingAs($this->user('dokter'))
            ->post(route('cases.store', $this->demo), $this->payload(['gds' => 450, 'abi' => '0,72']))
            ->assertSessionHasNoErrors();

        $case = PatientCase::where('kode_kasus', 'P09')->firstOrFail();
        $this->assertSame(0.72, $case->abi);
        $this->assertSame(['RF4'], $this->codes($case));

        $this->put(route('cases.update', [$this->demo, $case]), $this->payload(['gds' => 180, 'progres_luka' => 'MEMBURUK_CEPAT']))
            ->assertSessionHasNoErrors();
        $this->assertSame(['RF5'], $this->codes($case));
        $this->assertDatabaseHas('audit_logs', ['action' => 'CASE_UPDATE']);
    }

    #[Test]
    public function kasus_terkunci_setelah_penilaian_dibuka(): void
    {
        app(SessionWorkflowService::class)->openAssessment($this->demo, $this->user('dokter'));
        $p01 = PatientCase::where('kode_kasus', 'P01')->firstOrFail();

        $this->actingAs($this->user('dokter'))->get(route('cases.index', $this->demo))
            ->assertDontSee('Tambah Kasus')->assertSee('Kasus terkunci');
        $this->put(route('cases.update', [$this->demo, $p01]), $this->payload(['kode_kasus' => 'P01', 'usia' => 60]))
            ->assertSessionHas('error', 'Kasus terkunci sejak tahap penilaian dimulai.');
        $this->delete(route('cases.destroy', [$this->demo, $p01]))->assertSessionHas('error');
        $this->assertSame(8, $this->demo->cases()->count());
    }

    #[Test]
    public function hapus_kasus_saat_persiapan(): void
    {
        $p08 = PatientCase::where('kode_kasus', 'P08')->firstOrFail();
        $this->actingAs($this->user('dokter'))->delete(route('cases.destroy', [$this->demo, $p08]))
            ->assertRedirect(route('cases.index', $this->demo));
        $this->assertSame(7, $this->demo->cases()->count());
    }

    #[Test]
    public function impor_dataset_ke_sesi_baru_yang_kosong(): void
    {
        $this->demo->update(['status' => SessionStatus::Final]);
        $this->actingAs($this->user('dokter'))->post(route('sessions.store'), ['title' => 'Sesi Desember'])
            ->assertSessionHasNoErrors();
        $session = DecisionSession::where('title', 'Sesi Desember')->firstOrFail();

        $this->get(route('cases.index', $session))->assertSee('Belum ada kasus')->assertSee('Impor dataset_v1');
        $this->post(route('cases.import', $session))->assertSessionHas('success');

        $this->assertSame(8, $session->cases()->count());
        $this->assertSame(['RF1', 'RF5'], $this->codes($session->cases()->where('kode_kasus', 'P07')->firstOrFail()));
        $this->post(route('cases.import', $session))->assertSessionHas('error', fn ($m) => str_contains($m, 'belum berisi kasus'));
    }

    #[Test]
    public function t16_red_flag_manual_wajib_catatan(): void
    {
        $p05 = PatientCase::where('kode_kasus', 'P05')->firstOrFail();

        $this->actingAs($this->user('dokter'))
            ->post(route('cases.red-flags.store', [$this->demo, $p05]), ['code' => 'RF3'])
            ->assertSessionHasErrors(['note' => 'Catatan red flag manual wajib diisi.']);
        $this->assertSame([], $this->codes($p05));

        $this->post(route('cases.red-flags.store', [$this->demo, $p05]), ['code' => 'RF3', 'note' => 'Krepitasi pada palpasi'])
            ->assertSessionHas('success');
        $this->assertSame(['RF3'], $this->codes($p05));
        $this->assertDatabaseHas('audit_logs', ['action' => 'RED_FLAG_MANUAL', 'note' => 'Krepitasi pada palpasi']);

        $this->get(route('dashboard'))->assertSee('P05')->assertSee('RF3');
        // Red flag manual bertahan saat kasus disimpan ulang (evaluasi otomatis tidak menghapusnya).
        $this->put(route('cases.update', [$this->demo, $p05]), $this->payload(['kode_kasus' => 'P05']));
        $this->assertSame(['RF3'], $this->codes($p05));
    }

    #[Test]
    public function anggota_tidak_dapat_mengelola_kasus_atau_red_flag(): void
    {
        $p01 = PatientCase::where('kode_kasus', 'P01')->firstOrFail();
        $this->actingAs($this->user('apoteker'));

        $this->get(route('cases.create', $this->demo))->assertForbidden();
        $this->post(route('cases.store', $this->demo), $this->payload())->assertForbidden();
        $this->post(route('cases.red-flags.store', [$this->demo, $p01]), ['code' => 'RF3', 'note' => 'x'])->assertForbidden();
    }

    #[Test]
    public function kasus_dari_sesi_lain_tidak_dapat_dibuka(): void
    {
        $this->demo->update(['status' => SessionStatus::Final]);
        $other = app(SessionWorkflowService::class)->createSession($this->user('dokter'), 'Sesi lain');
        $p01 = PatientCase::where('kode_kasus', 'P01')->firstOrFail();

        $this->actingAs($this->user('dokter'))->get(route('cases.show', [$other, $p01]))->assertNotFound();
    }

    #[Test]
    public function riwayat_sesi_dan_audit_log(): void
    {
        $this->actingAs($this->user('perawat'))->get(route('sessions.index'))->assertOk()
            ->assertSee('Demo dataset_v1')->assertDontSee('Buat sesi baru');
        $this->get(route('audit.index', $this->demo))->assertForbidden();

        $this->actingAs($this->user('dokter'))->get(route('sessions.index'))->assertOk()
            ->assertSee('Sesi baru dapat dibuat setelah sesi yang berjalan difinalisasi');
        $this->get(route('audit.index', $this->demo))->assertOk()->assertSee('Sesi dibuat');
        $this->get(route('audit.index', [$this->demo, 'action' => 'LOGIN']))->assertOk()->assertSee('Belum ada catatan');
    }
}
