<?php

namespace Database\Seeders;

use App\Enums\AssessmentStatus;
use App\Enums\SessionStatus;
use App\Models\AuditLog;
use App\Models\Criterion;
use App\Models\DecisionSession;
use App\Models\User;
use Database\Seeders\Concerns\LoadsDataset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Opsional (SEED_DEMO_ASSESSMENTS=true): membuka sesi demo ke tahap PENILAIAN dan mengisi
 * draf penilaian tiga DM dari data Bab 4.7, untuk demo cepat (Bab 9.5).
 */
class DemoAssessmentSeeder extends Seeder
{
    use LoadsDataset;

    public function run(): void
    {
        $judgments = $this->dataset('ahp_judgments.json');
        $scores = $this->dataset('assessments.json');

        $session = DecisionSession::where('title', DemoSessionSeeder::TITLE)->firstOrFail();
        $criteria = Criterion::ofVersion(config('gdss.criteria_version'))->get()->keyBy('code');
        $cases = $session->cases()->get()->keyBy('kode_kasus');

        DB::transaction(function () use ($session, $criteria, $cases, $judgments, $scores) {
            $session->update(['status' => SessionStatus::Penilaian, 'assessment_opened_at' => now()]);

            foreach (User::all() as $user) {
                $assessment = $session->assessments()->create([
                    'user_id' => $user->id,
                    'status' => AssessmentStatus::Draf,
                ]);

                foreach ($judgments[$user->username] as $pair) {
                    $assessment->judgments()->create([
                        'criterion_i_id' => $criteria[$pair['i']]->id,
                        'criterion_j_id' => $criteria[$pair['j']]->id,
                        'value' => $this->saaty($pair['value']),
                    ]);
                }

                foreach ($scores[$user->username] as $caseCode => $row) {
                    foreach ($row as $criterionCode => $score) {
                        $assessment->scores()->create([
                            'case_id' => $cases[$caseCode]->id,
                            'criterion_id' => $criteria[$criterionCode]->id,
                            'score' => $score,
                        ]);
                    }
                }
            }

            AuditLog::create([
                'user_id' => $session->created_by,
                'session_id' => $session->id,
                'action' => 'SESSION_OPEN_ASSESSMENT',
                'from_status' => SessionStatus::Persiapan->value,
                'to_status' => SessionStatus::Penilaian->value,
                'meta' => ['source' => 'seeder', 'demo_assessments' => true],
            ]);
        });
    }
}
