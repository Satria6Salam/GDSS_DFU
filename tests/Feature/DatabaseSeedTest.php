<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Enums\Role;
use App\Enums\SessionStatus;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Criterion;
use App\Models\DecisionSession;
use App\Models\PatientCase;
use App\Models\User;
use Database\Seeders\DemoAssessmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Memastikan migration dan seeder Bab 9 menghasilkan data dataset_v1 yang benar. */
class DatabaseSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gdss.demo_passwords' => ['dokter' => 'uji-dokter', 'perawat' => 'uji-perawat', 'apoteker' => 'uji-apoteker']]);
        $this->seed();
    }

    #[Test]
    public function tiga_akun_dengan_satu_ketua(): void
    {
        $this->assertSame(3, User::count());
        $this->assertSame(['dokter'], User::where('is_leader', true)->pluck('username')->all());
        $this->assertSame(Role::Apoteker, User::where('username', 'apoteker')->first()->role);
        $this->assertTrue(Hash::check('uji-perawat', User::where('username', 'perawat')->first()->password));
    }

    #[Test]
    public function enam_kriteria_dengan_rubrik_lima_tingkat(): void
    {
        $criteria = Criterion::ofVersion('v1')->get();

        $this->assertSame(['C1', 'C2', 'C3', 'C4', 'C5', 'C6'], $criteria->pluck('code')->all());
        $criteria->each(fn (Criterion $c) => $this->assertCount(5, $c->rubric));
        $this->assertStringContainsString('ABI', $criteria[1]->rubricFor(1));
    }

    #[Test]
    public function sesi_demo_berisi_delapan_kasus_dan_red_flag_p07(): void
    {
        $session = DecisionSession::firstOrFail();

        $this->assertSame(SessionStatus::Persiapan, $session->status);
        $this->assertSame(8, $session->cases()->count());

        $flagged = PatientCase::has('redFlags')->with('redFlags')->get();
        $this->assertSame(['P07'], $flagged->pluck('kode_kasus')->all());
        $this->assertSame(['RF1', 'RF5'], $flagged->first()->redFlags->pluck('code')->map->value->all());

        $p04 = $session->cases()->where('kode_kasus', 'P04')->first();
        $this->assertSame(0.72, $p04->abi);
        $this->assertSame(1, AuditLog::where('action', 'SESSION_CREATE')->count());
    }

    #[Test]
    public function seeder_draf_penilaian_mengisi_15_pasang_dan_48_skor_per_dm(): void
    {
        $this->seed(DemoAssessmentSeeder::class);

        $session = DecisionSession::firstOrFail();
        $this->assertSame(SessionStatus::Penilaian, $session->status);
        $this->assertSame(3, Assessment::where('status', AssessmentStatus::Draf)->count());

        $dokter = Assessment::whereRelation('user', 'username', 'dokter')->firstOrFail();
        $this->assertSame(15, $dokter->judgments()->count());
        $this->assertSame(48, $dokter->scores()->count());

        // Matriks Dokter Bab 4.7.1: C1 vs C3 = 3, C3 vs C6 = 1/2.
        $value = fn (string $i, string $j) => $dokter->judgments()
            ->whereHas('criterionI', fn ($q) => $q->where('code', $i))
            ->whereHas('criterionJ', fn ($q) => $q->where('code', $j))
            ->value('value');
        $this->assertEqualsWithDelta(3.0, $value('C1', 'C3'), 1e-9);
        $this->assertEqualsWithDelta(0.5, $value('C3', 'C6'), 1e-9);
    }

    #[Test]
    public function audit_log_tidak_dapat_diubah(): void
    {
        $this->expectException(LogicException::class);
        AuditLog::firstOrFail()->update(['note' => 'diubah']);
    }
}
