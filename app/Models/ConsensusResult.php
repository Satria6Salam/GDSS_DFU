<?php

namespace App\Models;

use App\Enums\ConsensusStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Hasil Borda satu sesi (D05, D15). */
class ConsensusResult extends Model
{
    protected $fillable = ['session_id', 'status', 'deliberation_note', 'finalized_at', 'finalized_by'];

    protected function casts(): array
    {
        return [
            'status' => ConsensusStatus::class,
            'finalized_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(DecisionSession::class, 'session_id');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ConsensusItem::class, 'consensus_id')->orderBy('final_rank');
    }
}
