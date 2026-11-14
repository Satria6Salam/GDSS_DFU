<?php

namespace App\Models;

use App\Enums\RedFlagCode;
use App\Enums\RedFlagSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tanda bahaya pada satu kasus (Bab 3.5). Tidak dapat dihapus oleh skor atau voting (D12). */
class CaseRedFlag extends Model
{
    protected $fillable = ['case_id', 'code', 'source', 'note', 'created_by'];

    protected function casts(): array
    {
        return [
            'code' => RedFlagCode::class,
            'source' => RedFlagSource::class,
        ];
    }

    public function patientCase(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
