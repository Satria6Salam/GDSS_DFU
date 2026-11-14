<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\DecisionSession;
use App\Models\User;
use App\Services\SessionWorkflowService;
use App\Support\SaatyChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DatasetV1;
use Tests\TestCase;

/** Form penilaian H06–H08 lewat HTTP (A1–A4, T05, T06, T11, T17). */
class AssessmentFormTest extends TestCase
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

    private function openAssessment(): void
    {
        app(SessionWorkflowService::class)->openAssessment($this->demo, $this->user('dokter'));
    }

    /** Pilihan form AHP dari data Bab 4.7.1: "idI_idJ" => "L3" / "R2" / "1". */
    private function ahpPayload(string $key): array
    {
        $ids = Criterion::ofVersion('v1')->pluck('id')->all();
        $payload = [];
        foreach (DatasetV1::upperPairs($key) as $pair) {
            $payload["{$ids[$pair['i']]}_{$ids[$pair['j']]}"] = SaatyChoice::fromValue($pair['value']);
        }

        return $payload;
    }

    private function fillAll(string $dm): void
    {
        $this->actingAs($this->user($dm))
            ->put(route('assessment.ahp.update', $this->demo), ['pairs' => $this->ahpPayload($dm)])
            ->assertSessionHasNoErrors();

        $criteria = Criterion::ofVersion('v1')->pluck('id', 'code');
        $cases = $this->demo->cases()->pluck('id', 'kode_kasus');
        foreach (DatasetV1::scores($dm) as $code => $row) {
            $scores = [];
            foreach (DatasetV1::CRITERIA as $index => $criterion) {
                $scores[$criteria[$criterion]] = $row[$index];
            }
            $this->put(route('assessment.scores.update', $this->demo), ['case_id' => $cases[$code], 'scores' => $scores])
                ->assertSessionHasNoErrors();
        }
    }

    private function assessment(string $dm): Assessment
    {
        return Assessment::where('user_id', $this->user($dm)->id)->firstOrFail();
    }

    #[Test]
    public function sebelum_penilaian_dibuka_form_memberi_tahu(): void
    {
        $this->actingAs($this->user('perawat'))
            ->get(route('assessment.ahp', $this->demo))->assertOk()->assertSee('Penilaian belum dibuka');
    }

    #[Test]
    public function form_ahp_menampilkan_15_pasang_dan_menyimpan_draf(): void
    {
        $this->openAssessment();
        $response = $this->actingAs($this->user('perawat'))->get(route('assessment.ahp', $this->demo))->assertOk();
        $this->assertSame(15, substr_count($response->getContent(), 'class="pair-row"'));

        $this->put(route('assessment.ahp.update', $this->demo), ['pairs' => $this->ahpPayload('perawat')])
            ->assertSessionHas('success');

        $assessment = $this->assessment('perawat');
        $this->assertSame(AssessmentStatus::Draf, $assessment->status);
        $this->assertSame(15, $assessment->judgments()->count());
        $this->assertEqualsWithDelta(0.0063, $assessment->cr, 5e-5);

        // Pilihan tersimpan kembali terpilih saat form dibuka lagi.
        $this->get(route('assessment.ahp', $this->demo))->assertSee('value="R3"', false);
    }

    #[Test]
    public function draf_sebagian_diperbolehkan_tanpa_cr(): void
    {
        $this->openAssessment();
        $partial = array_slice($this->ahpPayload('dokter'), 0, 4, true);

        $this->actingAs($this->user('dokter'))->put(route('assessment.ahp.update', $this->demo), ['pairs' => $partial]);

        $this->assertSame(4, $this->assessment('dokter')->judgments()->count());
        $this->assertNull($this->assessment('dokter')->cr);
    }

    #[Test]
    public function skor_di_luar_1_sampai_5_ditolak(): void
    {
        $this->openAssessment();
        $criterion = Criterion::first();

        $this->actingAs($this->user('apoteker'))
            ->put(route('assessment.scores.update', $this->demo), ['case_id' => $this->demo->cases()->first()->id, 'scores' => [$criterion->id => 6]])
            ->assertSessionHasErrors('scores.'.$criterion->id);
        $this->assertSame(0, $this->assessment('apoteker')->scores()->count());
    }

    #[Test]
    public function halaman_skor_menampilkan_data_klinis_dan_rubrik(): void
    {
        $this->openAssessment();
        $this->actingAs($this->user('perawat'))
            ->get(route('assessment.scores', [$this->demo, 'case' => 'P07']))->assertOk()
            ->assertSee('Kasus P07')->assertSee('Penisilin')->assertSee('RF1 Infeksi berat')
            ->assertSee('Infeksi berat: infeksi lokal disertai', false);
    }

    #[Test]
    public function t05_isi_lengkap_lalu_kirim_final_menampilkan_hasil(): void
    {
        $this->openAssessment();
        $this->fillAll('dokter');

        $this->get(route('assessment.result', $this->demo))->assertSee('15 / 15')->assertSee('48 / 48')->assertSee('0,0031');
        $this->post(route('assessment.submit', $this->demo))->assertRedirect(route('assessment.result', $this->demo));

        $this->assertSame(AssessmentStatus::Final, $this->assessment('dokter')->status);
        $this->get(route('assessment.result', $this->demo))->assertOk()
            ->assertSee('Peringkat TOPSIS Saya')->assertSee('0,8212')->assertSee('0,2932')->assertSee('Tarik Kembali');

        // Form AHP kini mode baca.
        $this->get(route('assessment.ahp', $this->demo))->assertSee('mode baca');
        $this->put(route('assessment.ahp.update', $this->demo), ['pairs' => $this->ahpPayload('dokter')])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Tarik kembali'));
    }

    #[Test]
    public function t06_kirim_final_dengan_cr_tinggi_ditolak(): void
    {
        $this->openAssessment();
        $this->fillAll('apoteker');
        $this->put(route('assessment.ahp.update', $this->demo), ['pairs' => $this->ahpPayload('_uji_tidak_konsisten_apoteker')]);

        $this->get(route('assessment.result', $this->demo))->assertSee('0,3509')->assertSee('CR melebihi 0,10');
        $this->post(route('assessment.submit', $this->demo))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'CR = 0,3509'));
        $this->assertSame(AssessmentStatus::Draf, $this->assessment('apoteker')->status);
    }

    #[Test]
    public function t11_skor_tidak_dapat_diubah_setelah_voting_dimulai(): void
    {
        $this->openAssessment();
        foreach (DatasetV1::DECISION_MAKERS as $dm) {
            $this->fillAll($dm);
            $this->post(route('assessment.submit', $this->demo));
        }
        $this->actingAs($this->user('dokter'))->post(route('sessions.start-voting', $this->demo))->assertSessionHas('success');

        $criterion = Criterion::first();
        $this->actingAs($this->user('perawat'))
            ->put(route('assessment.scores.update', $this->demo), ['case_id' => $this->demo->cases()->first()->id, 'scores' => [$criterion->id => 5]])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Penilaian terkunci'));
        $this->get(route('assessment.result', $this->demo))->assertSee('terkunci karena voting sudah dimulai');
    }

    #[Test]
    public function t17_hasil_individual_hanya_milik_sendiri(): void
    {
        $this->openAssessment();
        $this->fillAll('dokter');
        $this->post(route('assessment.submit', $this->demo));

        // Perawat membuka halaman hasil: hanya melihat penilaiannya sendiri (masih kosong), bukan milik dokter.
        $this->actingAs($this->user('perawat'))->get(route('assessment.result', $this->demo))->assertOk()
            ->assertDontSee('0,8212')->assertSee('tampil setelah penilaian dikirim final');
    }
}
