<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AhpWeight extends Model
{
    protected $fillable = ['ahp_result_id', 'criterion_id', 'weight'];

    protected function casts(): array
    {
        return ['weight' => 'float'];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(AhpResult::class, 'ahp_result_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }
}
