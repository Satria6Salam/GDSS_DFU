<?php

namespace App\Models;

use App\Enums\CriterionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Kriteria C1–C6 dengan rubrik skor 1–5 (Bab 3). */
class Criterion extends Model
{
    protected $table = 'criteria';

    protected $fillable = [
        'code',
        'name',
        'definition',
        'type',
        'rubric',
        'data_reference',
        'source',
        'sort_order',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'type' => CriterionType::class,
            'rubric' => 'array',
        ];
    }

    /** Kriteria versi rubrik tertentu, berurutan C1 → C6. */
    public function scopeOfVersion(Builder $query, string $version = 'v1'): Builder
    {
        return $query->where('version', $version)->orderBy('sort_order');
    }

    public function rubricFor(int $score): ?string
    {
        return $this->rubric[(string) $score] ?? null;
    }
}
