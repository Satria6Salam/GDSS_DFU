<?php

namespace Database\Seeders;

use App\Enums\RedFlagSource;
use App\Enums\SessionStatus;
use App\Models\AuditLog;
use App\Models\DecisionSession;
use App\Models\PatientCase;
use App\Models\User;
use App\Services\RedFlagService;
use Database\Seeders\Concerns\LoadsDataset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/** Satu sesi PERSIAPAN berisi 8 kasus dataset_v1; red flag dievaluasi otomatis (Bab 9.5). */
class DemoSessionSeeder extends Seeder
{
    use LoadsDataset;

    public const TITLE = 'Demo dataset_v1';

    public function run(RedFlagService $redFlags): void
    {
        $data = $this->dataset('cases.json');
        $leader = User::where('is_leader', true)->firstOrFail();

        DB::transaction(function () use ($data, $leader, $redFlags) {
            $session = DecisionSession::create([
                'title' => self::TITLE,
                'status' => SessionStatus::Persiapan,
                'dataset_version' => $data['version'],
                'created_by' => $leader->id,
            ]);

            foreach ($data['cases'] as $row) {
                // Kolom berawalan "_" adalah kunci jawaban untuk pengujian, bukan atribut kasus.
                $attributes = Arr::only($row, PatientCase::CLINICAL_ATTRIBUTES);
                $case = $session->cases()->create($attributes);

                foreach ($redFlags->evaluate($attributes) as $code) {
                    $case->redFlags()->create(['code' => $code, 'source' => RedFlagSource::Otomatis]);
                }
            }

            AuditLog::create([
                'user_id' => $leader->id,
                'session_id' => $session->id,
                'action' => 'SESSION_CREATE',
                'to_status' => SessionStatus::Persiapan->value,
                'meta' => ['dataset_version' => $data['version'], 'cases' => count($data['cases']), 'source' => 'seeder'],
            ]);
        });
    }
}
