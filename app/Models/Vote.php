<?php

namespace App\Models;

use App\Enums\VoteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Suara DM: urutan case_id dari peringkat TOPSIS miliknya (D04). */
class Vote extends Model
{
    protected $fillable = ['session_id', 'user_id', 'ranking', 'status', 'submitted_at', 'cancelled_at'];

    protected function casts(): array
    {
        return [
            'ranking' => 'array',
            'status' => VoteStatus::class,
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
}
