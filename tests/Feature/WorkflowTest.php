<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Enums\ConsensusStatus;
use App\Enums\ResultScope;
use App\Enums\SessionStatus;
use App\Enums\TieBreakRule;
use App\Enums\VoteStatus;
use App\Exceptions\WorkflowException;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Criterion;
use App\Models\DecisionSession;
use App\Models\TopsisResult;
use App\Models\User;
use App\Services\SessionWorkflowService;
use Database\Seeders\DemoAssessmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DatasetV1;
use Tests\TestCase;

/** Alur status Bab 7.5 dan skenario T03–T14 (Bab 12.4) pada tingkat service dan HTTP. */
class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    private SessionWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gdss.demo_passwords' => ['dokter' => 'a', 'perawat' => 'b', 'apoteker' => 'c']]);
        $this->seed();
        $this->workflow = app(SessionWorkflowService::class);
    }

    private function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    private function demoSession(): DecisionSession
    {
        return DecisionSession::firstOrFail();
    }

    private function assessment(string $username): Assessment
    {
        return Assessment::where('user_id', $this->user($username)->id)->firstOrFail();
    }

    /** Sesi PENILAIAN dengan draf data Bab 4.7 untuk ketiga DM. */
    private function withDrafts(): void
    {
        $this->seed(DemoAssessmentSeeder::class);
    }

    private function submitAll(): void
    {
        foreach (DatasetV1::DECISION_MAKERS as $dm) {
            $this->workflow->submitAssessment($this->assessment($dm), $this->user($dm));
        }
    }

    private function voteAll(): void
    {
        foreach (DatasetV1::DECISION_MAKERS as $dm) {
            $this->workflow->submitVote($this->demoSession(), $this->user($dm));
        }
    }

    private function expectWorkflowError(string $fragment, callable $action): void
    {
        try {
            $action();
            $this->fail('WorkflowException diharapkan.');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString($fragment, $e->getMessage());
        }
    }

    #[Test]
    public function s1_buka_penilaian_lewat_panel_ketua(): void
    {
        $this->actingAs($this->user('dokter'))
            ->get(route('control.show', $this->demoSession()))->assertOk()->assertSee('Buka Penilaian');

        $this->post(route('sessions.open-assessment', $this->demoSession()))->assertSessionHas('success');

        $this->assertSame(SessionStatus::Penilaian, $this->demoSession()->status);
        $this->assertSame(3, Assessment::where('status', AssessmentStatus::Belum)->count());
        $this->assertSame(1, $this->user('perawat')->notifications()->count());
        $this->assertSame(0, $this->user('dokter')->notifications()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'SESSION_OPEN_ASSESSMENT', 'to_status' => 'PENILAIAN']);
    }

    #[Test]
    public function anggota_tidak_dapat_membuka_panel_atau_aksi_ketua(): void
    {
        $this->actingAs($this->user('perawat'))->get(route('control.show', $this->demoSession()))->assertForbidden();
        $this->post(route('sessions.open-assessment', $this->demoSession()))->assertForbidden();
        $this->expectWorkflowError('ketua tim', fn () => $this->workflow->openAssessment($this->demoSession(), $this->user('perawat')));
    }

    #[Test]
    public function t03_voting_ditolak_bila_satu_anggota_belum_final(): void
    {
        $this->withDrafts();
        $this->workflow->submitAssessment($this->assessment('dokter'), $this->user('dokter'));
        $this->workflow->submitAssessment($this->assessment('perawat'), $this->user('perawat'));

        $this->actingAs($this->user('dokter'))->get(route('control.show', $this->demoSession()))
            ->assertSee('Menunggu: Apoteker Klinis Demo');

        $this->from(route('control.show', $this->demoSession()))
            ->post(route('sessions.start-voting', $this->demoSession()))
            ->assertRedirect(route('control.show', $this->demoSession()))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'masih ada penilaian yang belum final'));
        $this->assertSame(SessionStatus::Penilaian, $this->demoSession()->status);
    }

    #[Test]
    public function t04_t07_voting_dimulai_dan_topsis_sesuai_kunci(): void
    {
        $this->withDrafts();
        $this->submitAll();
        $this->workflow->startVoting($this->demoSession(), $this->user('dokter'));

        $this->assertSame(SessionStatus::Voting, $this->demoSession()->status);
        $this->assertSame(3, Assessment::where('status', AssessmentStatus::Terkunci)->count());

        $expected = DatasetV1::expected();
        foreach ([...DatasetV1::DECISION_MAKERS, 'kelompok'] as $scope) {
            $query = TopsisResult::with('patientCase');
            $scope === 'kelompok'
                ? $query->where('scope', ResultScope::Kelompok)
                : $query->where('scope', ResultScope::Individual)->where('user_id', $this->user($scope)->id);

            $rows = $query->get()->keyBy(fn ($r) => $r->patientCase->kode_kasus);
            $this->assertCount(8, $rows, $scope);
            foreach ($expected['topsis'][$scope] as $code => $row) {
                $this->assertEqualsWithDelta($row['v'], $rows[$code]->v, $expected['tolerance'], "{$scope} {$code}");
                $this->assertSame($row['rank'], $rows[$code]->rank, "{$scope} {$code} peringkat");
            }
        }
        $this->assertEqualsWithDelta($expected['ahp']['dokter']['cr'], $this->assessment('dokter')->cr, 1e-9);
    }

    #[Test]
    public function t06_cr_lebih_dari_010_ditolak(): void
    {
        $this->withDrafts();
        $assessment = $this->assessment('apoteker');
        $criteria = Criterion::ofVersion('v1')->pluck('id', 'code');
        foreach (DatasetV1::upperPairs('_uji_tidak_konsisten_apoteker') as $pair) {
            $assessment->judgments()
                ->where('criterion_i_id', $criteria[DatasetV1::CRITERIA[$pair['i']]])
                ->where('criterion_j_id', $criteria[DatasetV1::CRITERIA[$pair['j']]])
                ->update(['value' => $pair['value']]);
        }

        $this->expectWorkflowError('CR = 0,3509 melebihi 0,10', fn () => $this->workflow->submitAssessment($assessment, $this->user('apoteker')));
        $this->assertSame(AssessmentStatus::Draf, $assessment->fresh()->status);
    }

    #[Test]
    public function kirim_final_ditolak_bila_isian_belum_lengkap(): void
    {
        $this->withDrafts();
        $assessment = $this->assessment('perawat');
        $assessment->scores()->first()->delete();

        $this->expectWorkflowError('Masih ada 1 isian kosong', fn () => $this->workflow->submitAssessment($assessment, $this->user('perawat')));
    }

    #[Test]
    public function t08_t10_alur_lengkap_sampai_final_sesuai_kunci_borda(): void
    {
        $this->withDrafts();
        $this->submitAll();
        $this->workflow->startVoting($this->demoSession(), $this->user('dokter'));
        $this->voteAll();

        $session = $this->demoSession();
        $this->assertSame(SessionStatus::MenungguFinalisasi, $session->status);

        $consensus = $session->consensusResults()->with('items.patientCase')->firstOrFail();
        $expected = DatasetV1::expected()['borda'];
        $this->assertSame(ConsensusStatus::MenungguFinalisasi, $consensus->status);
        $this->assertSame($expected['final_order'], $consensus->items->map(fn ($i) => $i->patientCase->kode_kasus)->all());
        foreach ($consensus->items as $item) {
            $this->assertSame($expected['points'][$item->patientCase->kode_kasus], $item->borda_points);
        }
        $this->assertNull(AuditLog::where('action', 'VOTING_CLOSE')->firstOrFail()->user_id);

        $this->actingAs($this->user('dokter'))->post(route('sessions.finalize', $session))->assertSessionHas('success');

        $this->assertSame(SessionStatus::Final, $this->demoSession()->status);
        $this->assertSame(ConsensusStatus::Final, $consensus->fresh()->status);
        $this->assertSame(3, User::all()->filter(fn ($u) => $u->notifications()->where('data->event', 'S4')->exists())->count());
    }

    #[Test]
    public function t11_buka_ulang_membatalkan_suara_dan_membuka_revisi(): void
    {
        $this->withDrafts();
        $this->submitAll();
        $this->workflow->startVoting($this->demoSession(), $this->user('dokter'));
        $this->workflow->submitVote($this->demoSession(), $this->user('perawat'));

        $this->expectWorkflowError('Alasan', fn () => $this->workflow->reopen($this->demoSession(), $this->user('dokter'), '  '));

        $this->actingAs($this->user('dokter'))
            ->post(route('sessions.reopen', $this->demoSession()), ['reason' => 'Skor P05 perlu dikoreksi'])
            ->assertSessionHas('success');

        $this->assertSame(SessionStatus::Penilaian, $this->demoSession()->status);
        $this->assertSame(VoteStatus::Dibatalkan, $this->demoSession()->votes()->first()->status);
        $this->assertSame(3, Assessment::where('status', AssessmentStatus::Final)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'SESSION_REOPEN', 'note' => 'Skor P05 perlu dikoreksi']);

        // Revisi: tarik kembali lalu kirim ulang tetap bisa.
        $this->workflow->withdrawAssessment($this->assessment('perawat'), $this->user('perawat'));
        $this->assertSame(AssessmentStatus::Draf, $this->assessment('perawat')->status);
    }

    #[Test]
    public function t13_tarik_kembali_hanya_sebelum_voting(): void
    {
        $this->withDrafts();
        $this->workflow->submitAssessment($this->assessment('perawat'), $this->user('perawat'));
        $this->assertSame(8, TopsisResult::where('user_id', $this->user('perawat')->id)->count());

        $this->workflow->withdrawAssessment($this->assessment('perawat'), $this->user('perawat'));
        $this->assertSame(AssessmentStatus::Draf, $this->assessment('perawat')->status);
        $this->assertSame(0, TopsisResult::where('user_id', $this->user('perawat')->id)->count());

        $this->submitAll();
        $this->workflow->startVoting($this->demoSession(), $this->user('dokter'));
        $this->expectWorkflowError('sebelum voting', fn () => $this->workflow->withdrawAssessment($this->assessment('perawat'), $this->user('perawat')));
    }

    #[Test]
    public function t14_suara_ganda_ditolak(): void
    {
        $this->withDrafts();
        $this->submitAll();
        $this->workflow->startVoting($this->demoSession(), $this->user('dokter'));
        $this->workflow->submitVote($this->demoSession(), $this->user('perawat'));

        $this->expectWorkflowError('sudah tercatat', fn () => $this->workflow->submitVote($this->demoSession(), $this->user('perawat')));
        $this->assertSame(1, $this->demoSession()->votes()->where('status', VoteStatus::Terkirim)->count());
    }

    #[Test]
    public function musyawarah_menukar_hanya_kasus_seri_lalu_finalisasi(): void
    {
        $this->withDrafts();
        $this->submitAll();
        $this->workflow->startVoting($this->demoSession(), $this->user('dokter'));
        $this->voteAll();

        // Simulasikan seri tingkat 3 pada dua kasus terakhir (P02 posisi 7, P01 posisi 8).
        $consensus = $this->demoSession()->consensusResults()->firstOrFail();
        $consensus->update(['status' => ConsensusStatus::PerluMusyawarah]);
        $consensus->items()->whereIn('final_rank', [7, 8])->update(['tie_break_rule' => TieBreakRule::Musyawarah]);
        $order = $consensus->items()->orderBy('final_rank')->pluck('case_id')->all();

        $this->expectWorkflowError('Tetapkan urutan', fn () => $this->workflow->finalize($this->demoSession(), $this->user('dokter')));
        $this->expectWorkflowError('Hanya urutan kasus yang seri', fn () => $this->workflow->deliberate(
            $this->demoSession(), $this->user('dokter'), [$order[1], $order[0], ...array_slice($order, 2)], 'tukar salah'));

        $swapped = [...array_slice($order, 0, 6), $order[7], $order[6]];
        $this->workflow->deliberate($this->demoSession(), $this->user('dokter'), $swapped, 'Disepakati dalam rapat');

        $this->assertSame($swapped, $consensus->items()->orderBy('final_rank')->pluck('case_id')->all());
        $this->assertSame('Disepakati dalam rapat', $consensus->fresh()->deliberation_note);
        $this->workflow->finalize($this->demoSession(), $this->user('dokter'));
        $this->assertSame(SessionStatus::Final, $this->demoSession()->status);
    }

    #[Test]
    public function s0_sesi_baru_hanya_setelah_sesi_lama_final(): void
    {
        $this->expectWorkflowError('belum final', fn () => $this->workflow->createSession($this->user('dokter'), 'Sesi 2'));

        $this->demoSession()->update(['status' => SessionStatus::Final]);
        $this->actingAs($this->user('dokter'))->post(route('sessions.store'), ['title' => 'Sesi 2'])
            ->assertRedirect();
        $this->assertSame(SessionStatus::Persiapan, DecisionSession::where('title', 'Sesi 2')->firstOrFail()->status);
    }

    #[Test]
    public function panel_ketua_tampil_di_setiap_tahap(): void
    {
        $this->withDrafts();
        $this->actingAs($this->user('dokter'));
        $panel = fn () => $this->get(route('control.show', $this->demoSession()))->assertOk();

        $panel()->assertSee('Mulai Voting');
        $this->submitAll();
        $this->workflow->startVoting($this->demoSession(), $this->user('dokter'));
        $panel()->assertSee('Voting sedang berlangsung')->assertSee('Buka Ulang Sesi');
        $this->voteAll();
        $panel()->assertSee('Urutan Konsensus')->assertSee('Finalisasi');
        $this->workflow->finalize($this->demoSession(), $this->user('dokter'));
        $panel()->assertSee('Buat Sesi');
    }
}
