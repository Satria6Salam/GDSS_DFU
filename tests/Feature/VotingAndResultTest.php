<?php

namespace Tests\Feature;

use App\Enums\SessionStatus;
use App\Models\Assessment;
use App\Models\DecisionSession;
use App\Models\User;
use App\Services\SessionWorkflowService;
use Database\Seeders\DemoAssessmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DatasetV1;
use Tests\TestCase;

/** Halaman Voting (H10) dan Hasil Konsensus (H11): T08, T10, T12, T14 dan visibilitas D13. */
class VotingAndResultTest extends TestCase
{
    use RefreshDatabase;

    private DecisionSession $demo;

    private SessionWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gdss.demo_passwords' => ['dokter' => 'a', 'perawat' => 'b', 'apoteker' => 'c']]);
        $this->seed();
        $this->seed(DemoAssessmentSeeder::class);
        $this->demo = DecisionSession::firstOrFail();
        $this->workflow = app(SessionWorkflowService::class);

        foreach (DatasetV1::DECISION_MAKERS as $dm) {
            $user = $this->user($dm);
            $this->workflow->submitAssessment(Assessment::where('user_id', $user->id)->firstOrFail(), $user);
        }
        $this->workflow->startVoting($this->demo, $this->user('dokter'));
    }

    private function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    private function vote(string $dm): void
    {
        $this->actingAs($this->user($dm))->post(route('vote.store', $this->demo), ['confirm' => '1'])->assertSessionHas('success');
    }

    #[Test]
    public function halaman_voting_menampilkan_peringkat_milik_sendiri(): void
    {
        $this->actingAs($this->user('perawat'))->get(route('vote.show', $this->demo))->assertOk()
            ->assertSee('Peringkat TOPSIS Anda')
            ->assertSee('0,8871')            // V P06 milik perawat (Bab 5.4.2)
            ->assertDontSee('0,8212')        // V P06 milik dokter tidak tampil
            ->assertSee('RF1 · RF5')
            ->assertSee('Kirim Suara');
    }

    #[Test]
    public function suara_wajib_dikonfirmasi(): void
    {
        $this->actingAs($this->user('perawat'))->post(route('vote.store', $this->demo))
            ->assertSessionHasErrors('confirm');
        $this->assertSame(0, $this->demo->votes()->count());
    }

    #[Test]
    public function t14_suara_kedua_ditolak_lewat_halaman(): void
    {
        $this->vote('perawat');
        $this->get(route('vote.show', $this->demo))->assertSee('Suara Terkirim');

        $this->post(route('vote.store', $this->demo), ['confirm' => '1'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'sudah tercatat'));
        $this->assertSame(1, $this->demo->votes()->count());
    }

    #[Test]
    public function d13_sebelum_final_anggota_tidak_melihat_hasil_ketua_hanya_total(): void
    {
        foreach (DatasetV1::DECISION_MAKERS as $dm) {
            $this->vote($dm);
        }
        $this->assertSame(SessionStatus::MenungguFinalisasi, $this->demo->fresh()->status);

        $this->actingAs($this->user('apoteker'))->get(route('consensus.show', $this->demo))->assertOk()
            ->assertSee('Hasil belum tersedia')->assertDontSee('Hasil Borda Count');

        $this->actingAs($this->user('dokter'))->get(route('consensus.show', $this->demo))->assertOk()
            ->assertSee('Pratinjau ketua')->assertSee('Hasil Borda Count')
            ->assertDontSee('Perawat Luka</th>', false)          // kolom suara per DM belum dibuka
            ->assertDontSee('Rincian Bobot Kriteria');
    }

    #[Test]
    public function t08_t10_t12_setelah_final_semua_anggota_melihat_hasil_sama(): void
    {
        foreach (DatasetV1::DECISION_MAKERS as $dm) {
            $this->vote($dm);
        }
        $this->workflow->finalize($this->demo, $this->user('dokter'));
        $this->get(route('dashboard')); // habiskan pesan flash dari pengiriman suara terakhir

        $pages = [];
        foreach (DatasetV1::DECISION_MAKERS as $dm) {
            $response = $this->actingAs($this->user($dm))->get(route('consensus.show', $this->demo))->assertOk();
            $response->assertSeeInOrder(DatasetV1::expected()['borda']['final_order'])
                ->assertSee('Rincian Bobot Kriteria')->assertSee('Kelompok (AIJ)')
                ->assertSee('Eskalasi Segera: P07 (RF1, RF5)')
                ->assertSee('Difinalisasi oleh Dokter Demo');
            // Baris P04: dokter posisi 2, perawat 2, apoteker 4 (Bab 5.4.3).
            $response->assertSeeInOrder(['P04', '2 (6)', '2 (6)', '4 (4)', '16']);
            // Bandingkan isi <main> saja: navbar dan token CSRF memang berbeda per pengguna.
            preg_match('/<main>(.*)<\/main>/s', $response->getContent(), $main);
            $pages[] = $main[1];
        }
        $this->assertCount(1, array_unique($pages), 'Ketiga anggota melihat konten hasil yang sama.');

        $this->get(route('vote.show', $this->demo))->assertSee('Suara Terkirim');
        $this->get(route('dashboard'))->assertSee('Hasil konsensus');
    }

    #[Test]
    public function voting_belum_dibuka_pada_tahap_lain(): void
    {
        $this->workflow->reopen($this->demo, $this->user('dokter'), 'Uji');

        $this->actingAs($this->user('perawat'))->get(route('vote.show', $this->demo))->assertSee('Voting belum dibuka');
        $this->post(route('vote.store', $this->demo), ['confirm' => '1'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'belum dibuka'));
    }
}
