<?php

namespace App\Services;

use App\Enums\AssessmentStatus;
use App\Enums\ConsensusStatus;
use App\Enums\RedFlagSource;
use App\Enums\SessionStatus;
use App\Enums\TieBreakRule;
use App\Enums\VoteStatus;
use App\Exceptions\WorkflowException;
use App\Models\Assessment;
use App\Models\ConsensusResult;
use App\Models\DecisionSession;
use App\Models\User;
use App\Models\Vote;
use App\Notifications\SessionNotification;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Satu-satunya tempat perubahan status (Bab 7.5). Setiap transisi berjalan dalam transaksi dengan
 * penguncian baris sesi (Bab 9.4), memeriksa syarat, menyimpan hasil perhitungan, menulis audit log,
 * dan mengirim notifikasi dashboard.
 */
class SessionWorkflowService
{
    public function __construct(
        private readonly DecisionCalculator $calculator,
        private readonly BordaService $borda,
        private readonly RedFlagService $redFlags,
        private readonly AuditLogger $audit,
    ) {}

    // ------------------------------------------------------------------ S0
    public function createSession(User $leader, string $title): DecisionSession
    {
        $this->ensureLeader($leader);

        return DB::transaction(function () use ($leader, $title) {
            if (DecisionSession::where('status', '!=', SessionStatus::Final)->lockForUpdate()->exists()) {
                throw new WorkflowException('Masih ada sesi yang belum final. Selesaikan sesi tersebut sebelum membuat sesi baru.');
            }

            $session = DecisionSession::create([
                'title' => $title,
                'status' => SessionStatus::Persiapan,
                'created_by' => $leader->id,
            ]);
            $this->audit->log('SESSION_CREATE', $session, null, SessionStatus::Persiapan->value, user: $leader);

            return $session;
        });
    }

    // ------------------------------------------------------------------ S1
    public function openAssessment(DecisionSession $session, User $leader): void
    {
        $this->ensureLeader($leader);

        $this->transition($session, function (DecisionSession $s) use ($leader) {
            $this->ensureStatus($s, SessionStatus::Persiapan, 'Penilaian hanya dapat dibuka dari tahap Persiapan.');

            if ($s->cases()->count() < 2) {
                throw new WorkflowException('Minimal 2 kasus diperlukan sebelum penilaian dibuka.');
            }
            if ($this->calculator->criteria()->count() !== 6) {
                throw new WorkflowException('Kriteria C1–C6 belum lengkap.');
            }

            $this->refreshRedFlags($s);
            foreach (User::all() as $user) {
                Assessment::firstOrCreate(['session_id' => $s->id, 'user_id' => $user->id], ['status' => AssessmentStatus::Belum]);
            }

            $s->update(['status' => SessionStatus::Penilaian, 'assessment_opened_at' => now()]);
            $this->audit->log('SESSION_OPEN_ASSESSMENT', $s, SessionStatus::Persiapan->value, SessionStatus::Penilaian->value, user: $leader);
            $this->notify($s, User::where('is_leader', false)->get(), "Penilaian sesi “{$s->title}” telah dibuka.", 'S1');
        });
    }

    // ------------------------------------------------------------------ A1/A2
    /**
     * Simpan draf perbandingan berpasangan. Boleh sebagian; CR dihitung bila 15 pasang lengkap.
     *
     * @param  array<int, array{criterion_i_id: int, criterion_j_id: int, value: float}>  $pairs
     */
    public function saveJudgments(Assessment $assessment, User $user, array $pairs): void
    {
        $this->ensureOwner($assessment, $user);

        $this->transition($assessment->session, function (DecisionSession $s) use ($assessment, $user, $pairs) {
            $this->ensureEditable($s, $assessment);

            foreach ($pairs as $pair) {
                if (! AhpService::isSaatyValue($pair['value'])) {
                    throw new WorkflowException('Nilai perbandingan harus berada pada skala Saaty 1/9 … 9.');
                }
                $assessment->judgments()->updateOrCreate(
                    ['criterion_i_id' => $pair['criterion_i_id'], 'criterion_j_id' => $pair['criterion_j_id']],
                    ['value' => $pair['value']],
                );
            }

            $cr = $assessment->judgments()->count() === Assessment::PAIR_COUNT
                ? $this->calculator->analyzeAhp($assessment)['cr']
                : null;
            $this->markDraft($s, $assessment, $user, ['type' => 'ahp', 'pairs' => count($pairs)], $cr);
        });
    }

    /**
     * Simpan draf skor satu kasus (nilai 1–5 per kriteria). Boleh sebagian.
     *
     * @param  array<int, int>  $scores  criterion_id => skor
     */
    public function saveScores(Assessment $assessment, User $user, int $caseId, array $scores): void
    {
        $this->ensureOwner($assessment, $user);

        $this->transition($assessment->session, function (DecisionSession $s) use ($assessment, $user, $caseId, $scores) {
            $this->ensureEditable($s, $assessment);

            if (! $s->cases()->whereKey($caseId)->exists()) {
                throw new WorkflowException('Kasus tidak termasuk dalam sesi ini.');
            }
            foreach ($scores as $criterionId => $score) {
                if ($score < 1 || $score > 5) {
                    throw new WorkflowException('Skor harus bilangan bulat 1–5.');
                }
                $assessment->scores()->updateOrCreate(
                    ['case_id' => $caseId, 'criterion_id' => $criterionId],
                    ['score' => $score],
                );
            }

            $this->markDraft($s, $assessment, $user, ['type' => 'scores', 'case_id' => $caseId], $assessment->cr);
        });
    }

    private function ensureEditable(DecisionSession $s, Assessment $assessment): void
    {
        $assessment->refresh();
        if (! $s->isStatus(SessionStatus::Penilaian) || $assessment->status === AssessmentStatus::Terkunci) {
            throw new WorkflowException('Penilaian terkunci karena voting sudah dimulai atau sesi belum pada tahap Penilaian. Hubungi ketua untuk membuka ulang sesi.');
        }
        if ($assessment->status === AssessmentStatus::Final) {
            throw new WorkflowException('Penilaian sudah dikirim final. Tarik kembali terlebih dahulu untuk mengubahnya.');
        }
    }

    private function markDraft(DecisionSession $s, Assessment $assessment, User $user, array $meta, ?float $cr): void
    {
        $from = $assessment->status;
        $assessment->update(['status' => AssessmentStatus::Draf, 'cr' => $cr]);
        $assessment->touch();
        $this->audit->log('ASSESSMENT_SAVE', $s, $from->value, AssessmentStatus::Draf->value, meta: $meta, user: $user);
    }

    // ------------------------------------------------------------------ A3
    public function submitAssessment(Assessment $assessment, User $user): void
    {
        $this->ensureOwner($assessment, $user);

        $this->transition($assessment->session, function (DecisionSession $s) use ($assessment, $user) {
            $this->ensureStatus($s, SessionStatus::Penilaian, 'Penilaian hanya dapat dikirim pada tahap Penilaian.');
            $assessment->refresh();

            if ($assessment->status !== AssessmentStatus::Draf) {
                throw new WorkflowException('Hanya penilaian berstatus draf yang dapat dikirim final.');
            }

            $missing = $this->missingInputs($assessment);
            if ($missing > 0) {
                throw new WorkflowException("Masih ada {$missing} isian kosong.");
            }

            $analysis = $this->calculator->analyzeAhp($assessment);
            if (! $analysis['consistent']) {
                $cr = number_format($analysis['cr'], 4, ',', '.');
                throw new WorkflowException("CR = {$cr} melebihi 0,10. Tinjau kembali perbandingan berpasangan.");
            }

            $this->calculator->storeIndividual($assessment);
            $assessment->update(['status' => AssessmentStatus::Final, 'cr' => $analysis['cr'], 'submitted_at' => now()]);
            $this->audit->log('ASSESSMENT_SUBMIT', $s, AssessmentStatus::Draf->value, AssessmentStatus::Final->value,
                meta: ['cr' => $analysis['cr']], user: $user);

            $finalCount = $s->assessments()->where('status', AssessmentStatus::Final)->count();
            $total = User::count();
            $leaders = User::where('is_leader', true)->get();
            if (! $user->isLeader()) {
                $this->notify($s, $leaders, "{$user->name} telah mengirim penilaian final ({$finalCount}/{$total}).", 'A3');
            }
            if ($finalCount === $total) {
                $this->notify($s, $leaders, 'Semua penilaian lengkap. Voting dapat dimulai.', 'ALL_FINAL');
            }
        });
    }

    // ------------------------------------------------------------------ A4
    public function withdrawAssessment(Assessment $assessment, User $user): void
    {
        $this->ensureOwner($assessment, $user);

        $this->transition($assessment->session, function (DecisionSession $s) use ($assessment, $user) {
            $this->ensureStatus($s, SessionStatus::Penilaian, 'Penilaian hanya dapat ditarik kembali sebelum voting dimulai.');
            $assessment->refresh();

            if ($assessment->status !== AssessmentStatus::Final) {
                throw new WorkflowException('Hanya penilaian final yang dapat ditarik kembali.');
            }

            $this->calculator->clearIndividual($assessment);
            $assessment->update(['status' => AssessmentStatus::Draf, 'submitted_at' => null]);
            $this->audit->log('ASSESSMENT_WITHDRAW', $s, AssessmentStatus::Final->value, AssessmentStatus::Draf->value, user: $user);
        });
    }

    // ------------------------------------------------------------------ S2 (+A5)
    public function startVoting(DecisionSession $session, User $leader): void
    {
        $this->ensureLeader($leader);

        $this->transition($session, function (DecisionSession $s) use ($leader) {
            $this->ensureStatus($s, SessionStatus::Penilaian, 'Voting hanya dapat dimulai dari tahap Penilaian.');

            $assessments = $s->assessments()->with(['user', 'session'])->get();
            $waiting = User::all()->reject(fn (User $u) => $assessments->firstWhere('user_id', $u->id)?->status === AssessmentStatus::Final);
            if ($waiting->isNotEmpty()) {
                throw new WorkflowException('Voting belum dapat dimulai: masih ada penilaian yang belum final ('.$waiting->pluck('name')->implode(', ').').');
            }

            // Hasil kelompok dahulu, lalu peringkat individual dihitung ulang dengan pemecah seri V kelompok (Bab 5.5).
            $group = $this->calculator->storeGroup($s, $assessments);
            $tiePriority = array_map(fn ($row) => $row['rank'], $group);
            foreach ($assessments as $assessment) {
                $this->calculator->storeIndividual($assessment, $tiePriority);
            }

            $s->assessments()->update(['status' => AssessmentStatus::Terkunci]);
            $s->update(['status' => SessionStatus::Voting, 'voting_started_at' => now()]);
            $this->audit->log('VOTING_START', $s, SessionStatus::Penilaian->value, SessionStatus::Voting->value, user: $leader);
            $this->notify($s, User::all(), 'Voting dibuka. Tinjau peringkat Anda dan kirim suara.', 'S2');
        });
    }

    // ------------------------------------------------------------------ V1 (+S3)
    public function submitVote(DecisionSession $session, User $user): Vote
    {
        return $this->transition($session, function (DecisionSession $s) use ($user) {
            $this->ensureStatus($s, SessionStatus::Voting, 'Voting belum dibuka atau sudah ditutup.');

            if ($s->votes()->where('user_id', $user->id)->where('status', VoteStatus::Terkirim)->exists()) {
                throw new WorkflowException('Suara Anda sudah tercatat dan tidak dapat diubah.');
            }

            $ranking = $this->calculator->individualRanking($s, $user->id);
            if ($ranking === []) {
                throw new WorkflowException('Peringkat TOPSIS Anda belum tersedia.');
            }

            $vote = $s->votes()->create([
                'user_id' => $user->id,
                'ranking' => $ranking,
                'status' => VoteStatus::Terkirim,
                'submitted_at' => now(),
            ]);
            $this->audit->log('VOTE_SUBMIT', $s, null, VoteStatus::Terkirim->value, user: $user);

            if ($s->votes()->where('status', VoteStatus::Terkirim)->count() === User::count()) {
                $this->closeVoting($s);
            }

            return $vote;
        });
    }

    /** S3: hitung Borda + pemecah seri saat suara terakhir masuk. */
    private function closeVoting(DecisionSession $s): void
    {
        $votes = $s->votes()->where('status', VoteStatus::Terkirim)->orderBy('id')->get();
        $result = $this->borda->aggregate($votes->pluck('ranking')->all(), $this->calculator->groupV($s));

        $consensus = $s->consensusResults()->create([
            'status' => $result['needs_deliberation'] ? ConsensusStatus::PerluMusyawarah : ConsensusStatus::MenungguFinalisasi,
        ]);
        foreach ($result['items'] as $item) {
            $consensus->items()->create([
                'case_id' => $item['id'],
                'borda_points' => $item['points'],
                'group_v' => $item['group_v'],
                'final_rank' => $item['rank'],
                'tie_break_rule' => $item['tie_break_rule'],
            ]);
        }

        $s->update(['status' => SessionStatus::MenungguFinalisasi]);
        $this->audit->log('VOTING_CLOSE', $s, SessionStatus::Voting->value, SessionStatus::MenungguFinalisasi->value,
            meta: ['needs_deliberation' => $result['needs_deliberation'], 'order' => array_column($result['items'], 'id')], system: true);
        $this->notify($s, User::where('is_leader', true)->get(),
            $result['needs_deliberation'] ? 'Semua suara masuk. Ada seri yang perlu ditetapkan melalui musyawarah.' : 'Semua suara masuk. Hasil menunggu finalisasi.', 'S3');
    }

    // ------------------------------------------------------------------ Musyawarah (seri tingkat 3)
    /** @param  list<int>  $order  urutan case_id lengkap yang ditetapkan ketua */
    public function deliberate(DecisionSession $session, User $leader, array $order, string $note): void
    {
        $this->ensureLeader($leader);

        $this->transition($session, function (DecisionSession $s) use ($leader, $order, $note) {
            $this->ensureStatus($s, SessionStatus::MenungguFinalisasi, 'Musyawarah hanya dapat dilakukan saat menunggu finalisasi.');
            if (trim($note) === '') {
                throw new WorkflowException('Alasan musyawarah wajib diisi.');
            }

            $consensus = $this->activeConsensus($s);
            if ($consensus->status !== ConsensusStatus::PerluMusyawarah) {
                throw new WorkflowException('Tidak ada seri yang perlu ditetapkan.');
            }

            $items = $consensus->items()->get()->keyBy('case_id');
            if (count($order) !== $items->count() || array_diff($items->keys()->all(), $order) !== []) {
                throw new WorkflowException('Urutan harus memuat seluruh kasus tepat satu kali.');
            }
            foreach (array_values($order) as $index => $caseId) {
                $item = $items[$caseId];
                // Hanya kasus berlabel MUSYAWARAH yang boleh berpindah, dan hanya di antara sesamanya.
                if ($item->tie_break_rule !== TieBreakRule::Musyawarah && $item->final_rank !== $index + 1) {
                    throw new WorkflowException('Hanya urutan kasus yang seri yang dapat diubah.');
                }
                if ($item->tie_break_rule === TieBreakRule::Musyawarah
                    && $items->firstWhere('final_rank', $index + 1)?->tie_break_rule !== TieBreakRule::Musyawarah) {
                    throw new WorkflowException('Kasus seri hanya dapat bertukar posisi dengan kasus seri lainnya.');
                }
            }
            foreach (array_values($order) as $index => $caseId) {
                $items[$caseId]->update(['final_rank' => $index + 1]);
            }

            $consensus->update(['status' => ConsensusStatus::MenungguFinalisasi, 'deliberation_note' => $note]);
            $this->audit->log('DELIBERATION', $s, note: $note, meta: ['order' => array_values($order)], user: $leader);
        });
    }

    // ------------------------------------------------------------------ S4
    public function finalize(DecisionSession $session, User $leader, ?string $conclusionNote = null): void
    {
        $this->ensureLeader($leader);

        $this->transition($session, function (DecisionSession $s) use ($leader, $conclusionNote) {
            $this->ensureStatus($s, SessionStatus::MenungguFinalisasi, 'Hasil hanya dapat difinalisasi setelah semua suara masuk.');

            $consensus = $this->activeConsensus($s);
            if ($consensus->status === ConsensusStatus::PerluMusyawarah) {
                throw new WorkflowException('Tetapkan urutan untuk kasus yang seri terlebih dahulu.');
            }

            $consensus->update([
                'status' => ConsensusStatus::Final,
                'finalized_at' => now(),
                'finalized_by' => $leader->id,
                // Rencana penanganan nyata ditulis manusia, bukan dihasilkan sistem (Laporan 1.6).
                'conclusion_note' => filled($conclusionNote) ? trim($conclusionNote) : null,
            ]);
            $s->update(['status' => SessionStatus::Final, 'finalized_at' => now(), 'finalized_by' => $leader->id]);
            $this->audit->log('SESSION_FINALIZE', $s, SessionStatus::MenungguFinalisasi->value, SessionStatus::Final->value, user: $leader);
            $this->notify($s, User::all(), "Hasil konsensus sesi “{$s->title}” telah dipublikasikan.", 'S4');
        });
    }

    // ------------------------------------------------------------------ S5 (+A6, V2)
    public function reopen(DecisionSession $session, User $leader, string $reason): void
    {
        $this->ensureLeader($leader);

        $this->transition($session, function (DecisionSession $s) use ($leader, $reason) {
            if (! in_array($s->status, [SessionStatus::Voting, SessionStatus::MenungguFinalisasi], true)) {
                throw new WorkflowException('Sesi hanya dapat dibuka ulang saat Voting atau Menunggu Finalisasi.');
            }
            if (trim($reason) === '') {
                throw new WorkflowException('Alasan membuka ulang sesi wajib diisi.');
            }

            $from = $s->status->value;
            $s->votes()->where('status', VoteStatus::Terkirim)->update(['status' => VoteStatus::Dibatalkan, 'cancelled_at' => now()]);
            $s->consensusResults()->where('status', '!=', ConsensusStatus::Final)->update(['status' => ConsensusStatus::Dibatalkan]);
            $this->calculator->clearGroup($s);
            $s->assessments()->where('status', AssessmentStatus::Terkunci)->update(['status' => AssessmentStatus::Final]);
            $s->update(['status' => SessionStatus::Penilaian, 'voting_started_at' => null]);

            $this->audit->log('SESSION_REOPEN', $s, $from, SessionStatus::Penilaian->value, note: $reason, user: $leader);
            $this->notify($s, User::all(), "Sesi dibuka ulang oleh ketua. Alasan: {$reason}", 'S5');
        });
    }

    // ------------------------------------------------------------------ bantuan
    /** Jumlah isian yang belum terisi: 15 pasang AHP + (kasus × kriteria) skor. */
    public function missingInputs(Assessment $assessment): int
    {
        $criteria = $this->calculator->criteria()->count();
        $pairs = $criteria * ($criteria - 1) / 2;
        $scores = $assessment->session->cases()->count() * $criteria;

        return max(0, $pairs - $assessment->judgments()->count()) + max(0, $scores - $assessment->scores()->count());
    }

    public function activeConsensus(DecisionSession $session): ConsensusResult
    {
        return $session->consensusResults()->where('status', '!=', ConsensusStatus::Dibatalkan)->latest('id')->first()
            ?? throw new WorkflowException('Hasil konsensus belum tersedia.');
    }

    /** Red flag otomatis dievaluasi ulang; red flag manual dibiarkan (Bab 3.5). */
    private function refreshRedFlags(DecisionSession $s): void
    {
        foreach ($s->cases()->get() as $case) {
            $case->redFlags()->where('source', RedFlagSource::Otomatis)->delete();
            foreach ($this->redFlags->evaluate($case->toArray()) as $code) {
                $case->redFlags()->firstOrCreate(['code' => $code], ['source' => RedFlagSource::Otomatis]);
            }
        }
    }

    /** Jalankan transisi dalam transaksi dengan baris sesi terkunci (Bab 9.4). */
    private function transition(DecisionSession $session, Closure $callback): mixed
    {
        return DB::transaction(function () use ($session, $callback) {
            $locked = DecisionSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $result = $callback($locked);
            $session->setRawAttributes($locked->getAttributes(), true);

            return $result;
        });
    }

    private function ensureStatus(DecisionSession $session, SessionStatus $expected, string $message): void
    {
        if ($session->status !== $expected) {
            throw new WorkflowException($message);
        }
    }

    private function ensureLeader(User $user): void
    {
        if (! $user->isLeader()) {
            throw new WorkflowException('Aksi ini hanya dapat dilakukan oleh ketua tim.');
        }
    }

    private function ensureOwner(Assessment $assessment, User $user): void
    {
        if ($assessment->user_id !== $user->id) {
            throw new WorkflowException('Anda hanya dapat mengubah penilaian milik sendiri.');
        }
    }

    private function notify(DecisionSession $session, $users, string $message, string $event): void
    {
        Notification::send($users, new SessionNotification($session, $message, $event));
    }
}
