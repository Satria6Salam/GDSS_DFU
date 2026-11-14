<?php

namespace App\Services;

use App\Enums\RedFlagCode;
use App\Enums\RedFlagSource;
use App\Enums\SessionStatus;
use App\Exceptions\WorkflowException;
use App\Models\CaseRedFlag;
use App\Models\DecisionSession;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Pengelolaan kasus (FR-04, FR-06). Kasus hanya dapat diubah pada tahap PERSIAPAN (Bab 4.3);
 * red flag otomatis dievaluasi ulang setiap kali kasus disimpan, red flag manual dapat ditambah sebelum FINAL.
 */
class CaseManager
{
    public function __construct(
        private readonly RedFlagService $redFlags,
        private readonly AuditLogger $audit,
    ) {}

    public function create(DecisionSession $session, User $leader, array $attributes): PatientCase
    {
        $this->ensureEditable($session, $leader);

        return DB::transaction(function () use ($session, $leader, $attributes) {
            $case = $session->cases()->create(Arr::only($attributes, PatientCase::CLINICAL_ATTRIBUTES));
            $this->syncAutomaticFlags($case);
            $this->audit->log('CASE_CREATE', $session, meta: ['kode_kasus' => $case->kode_kasus], user: $leader);

            return $case;
        });
    }

    public function update(PatientCase $case, User $leader, array $attributes): void
    {
        $this->ensureEditable($case->session, $leader);

        DB::transaction(function () use ($case, $leader, $attributes) {
            $case->update(Arr::only($attributes, PatientCase::CLINICAL_ATTRIBUTES));
            $this->syncAutomaticFlags($case);
            $this->audit->log('CASE_UPDATE', $case->session, meta: ['kode_kasus' => $case->kode_kasus], user: $leader);
        });
    }

    public function delete(PatientCase $case, User $leader): void
    {
        $this->ensureEditable($case->session, $leader);

        DB::transaction(function () use ($case, $leader) {
            $this->audit->log('CASE_DELETE', $case->session, meta: ['kode_kasus' => $case->kode_kasus], user: $leader);
            $case->delete();
        });
    }

    /** Impor kasus dataset (Bab 4.9) ke sesi PERSIAPAN yang belum berisi kasus. */
    public function importDataset(DecisionSession $session, User $leader, ?string $version = null): int
    {
        $this->ensureEditable($session, $leader);
        $version ??= config('gdss.dataset_version');

        if ($session->cases()->exists()) {
            throw new WorkflowException('Impor hanya dapat dilakukan pada sesi yang belum berisi kasus.');
        }

        $path = database_path("seeders/data/{$version}/cases.json");
        if (! is_file($path)) {
            throw new WorkflowException("Dataset {$version} tidak ditemukan.");
        }
        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return DB::transaction(function () use ($session, $leader, $data, $version) {
            foreach ($data['cases'] as $row) {
                $case = $session->cases()->create(Arr::only($row, PatientCase::CLINICAL_ATTRIBUTES));
                $this->syncAutomaticFlags($case);
            }
            $session->update(['dataset_version' => $version]);
            $this->audit->log('DATASET_IMPORT', $session, meta: ['dataset_version' => $version, 'cases' => count($data['cases'])], user: $leader);

            return count($data['cases']);
        });
    }

    /** Red flag manual oleh ketua; catatan wajib (Bab 3.5, T16). */
    public function addManualFlag(PatientCase $case, User $leader, RedFlagCode $code, string $note): CaseRedFlag
    {
        if (! $leader->isLeader()) {
            throw new WorkflowException('Aksi ini hanya dapat dilakukan oleh ketua tim.');
        }
        if ($case->session->isStatus(SessionStatus::Final)) {
            throw new WorkflowException('Red flag tidak dapat ditambahkan setelah sesi final.');
        }
        if (trim($note) === '') {
            throw new WorkflowException('Catatan red flag manual wajib diisi.');
        }
        if ($case->redFlags()->where('code', $code)->exists()) {
            throw new WorkflowException("Kasus {$case->kode_kasus} sudah memiliki {$code->value}.");
        }

        return DB::transaction(function () use ($case, $leader, $code, $note) {
            $flag = $case->redFlags()->create([
                'code' => $code,
                'source' => RedFlagSource::Manual,
                'note' => $note,
                'created_by' => $leader->id,
            ]);
            $this->audit->log('RED_FLAG_MANUAL', $case->session, note: $note,
                meta: ['kode_kasus' => $case->kode_kasus, 'code' => $code->value], user: $leader);

            return $flag;
        });
    }

    /** Evaluasi ulang red flag otomatis; red flag manual tidak disentuh. */
    public function syncAutomaticFlags(PatientCase $case): void
    {
        $case->refresh();
        $codes = $this->redFlags->evaluate($case->toArray());
        $case->redFlags()->where('source', RedFlagSource::Otomatis)
            ->whereNotIn('code', array_map(fn ($c) => $c->value, $codes))->delete();

        foreach ($codes as $code) {
            $case->redFlags()->firstOrCreate(['code' => $code], ['source' => RedFlagSource::Otomatis]);
        }
    }

    private function ensureEditable(DecisionSession $session, User $leader): void
    {
        if (! $leader->isLeader()) {
            throw new WorkflowException('Aksi ini hanya dapat dilakukan oleh ketua tim.');
        }
        if (! $session->isStatus(SessionStatus::Persiapan)) {
            throw new WorkflowException('Kasus terkunci sejak tahap penilaian dimulai.');
        }
    }
}
