<?php

namespace App\Models;

use App\Enums\ResultScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hasil TOPSIS satu kasus, individual atau kelompok (D03). */
class TopsisResult extends Model
{
    protected $fillable = ['session_id', 'scope', 'user_id', 'case_id', 'd_plus', 'd_minus', 'v', 'rank'];

    protected function casts(): array
    {
        return [
            'scope' => ResultScope::class,
            'd_plus' => 'float',
            'd_minus' => 'float',
            'v' => 'float',
            'rank' => 'integer',
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

    public function patientCase(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }
}
