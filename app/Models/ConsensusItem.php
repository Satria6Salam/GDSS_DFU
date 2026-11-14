<?php

namespace App\Models;

use App\Enums\TieBreakRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsensusItem extends Model
{
    protected $fillable = ['consensus_id', 'case_id', 'borda_points', 'group_v', 'final_rank', 'tie_break_rule'];

    protected function casts(): array
    {
        return [
            'borda_points' => 'integer',
            'group_v' => 'float',
            'final_rank' => 'integer',
            'tie_break_rule' => TieBreakRule::class,
        ];
    }

    public function consensus(): BelongsTo
    {
        return $this->belongsTo(ConsensusResult::class, 'consensus_id');
    }

    public function patientCase(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }
}
