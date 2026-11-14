<?php

namespace App\Models;

use App\Enums\AssessmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Penilaian satu DM pada satu sesi: 15 perbandingan AHP + 48 skor (Bab 7.3). */
class Assessment extends Model
{
    public const PAIR_COUNT = 15;

    protected $fillable = ['session_id', 'user_id', 'status', 'cr', 'submitted_at'];

    protected function casts(): array
    {
        return [
            'status' => AssessmentStatus::class,
            'cr' => 'float',
            'submitted_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(DecisionSession::class, 'session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function judgments(): HasMany
    {
        return $this->hasMany(AhpJudgment::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [AssessmentStatus::Belum, AssessmentStatus::Draf], true);
    }
}
