<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Skor 1–5 satu kasus pada satu kriteria (D10). */
class AssessmentScore extends Model
{
    protected $fillable = ['assessment_id', 'case_id', 'criterion_id', 'score'];

    protected function casts(): array
    {
        return ['score' => 'integer'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function patientCase(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }
}
