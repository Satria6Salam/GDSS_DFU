<?php

namespace App\Models;

use App\Enums\ResultScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Hasil AHP: individual (user_id terisi) atau kelompok AIJ (D02). */
class AhpResult extends Model
{
    protected $fillable = ['session_id', 'scope', 'user_id', 'lambda_max', 'ci', 'cr'];

    protected function casts(): array
    {
        return [
            'scope' => ResultScope::class,
            'lambda_max' => 'float',
            'ci' => 'float',
            'cr' => 'float',
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

    public function weights(): HasMany
    {
        return $this->hasMany(AhpWeight::class);
    }
}
