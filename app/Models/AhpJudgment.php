<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu nilai perbandingan berpasangan a_ij di atas diagonal (i < j). */
class AhpJudgment extends Model
{
    protected $fillable = ['assessment_id', 'criterion_i_id', 'criterion_j_id', 'value'];

    protected function casts(): array
    {
        return ['value' => 'float'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function criterionI(): BelongsTo
    {
        return $this->belongsTo(Criterion::class, 'criterion_i_id');
    }

    public function criterionJ(): BelongsTo
    {
        return $this->belongsTo(Criterion::class, 'criterion_j_id');
    }
}
