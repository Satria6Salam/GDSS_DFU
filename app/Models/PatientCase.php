<?php

namespace App\Models;

use App\Enums\WoundProgress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kasus pasien dummy (Bab 4.2). Diberi nama PatientCase karena "case" adalah kata kunci PHP.
 */
class PatientCase extends Model
{
    protected $table = 'patient_cases';

    /** Atribut klinis yang boleh diisi; nama mengikuti Bab 4.2 dan file seed. */
    public const CLINICAL_ATTRIBUTES = [
        'kode_kasus', 'usia', 'jenis_kelamin', 'tipe_dm', 'lama_dm', 'lokasi_luka',
        'tanda_inflamasi', 'eritema', 'suhu', 'nadi', 'laju_napas', 'leukosit',
        'abi', 'nadi_kaki', 'nyeri_istirahat', 'gangren',
        'luas_luka', 'kedalaman', 'monofilamen', 'deformitas',
        'hba1c', 'gds', 'egfr', 'tekanan_darah', 'progres_luka',
        'alergi_obat', 'obat_rutin',
    ];

    protected $fillable = ['session_id', ...self::CLINICAL_ATTRIBUTES];

    protected function casts(): array
    {
        return [
            'tanda_inflamasi' => 'boolean',
            'nyeri_istirahat' => 'boolean',
            'gangren' => 'boolean',
            'eritema' => 'float',
            'suhu' => 'float',
            'leukosit' => 'float',
            'abi' => 'float',
            'luas_luka' => 'float',
            'hba1c' => 'float',
            'progres_luka' => WoundProgress::class,
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(DecisionSession::class, 'session_id');
    }

    public function redFlags(): HasMany
    {
        return $this->hasMany(CaseRedFlag::class, 'case_id')->orderBy('code');
    }

    public function hasRedFlag(): bool
    {
        return $this->redFlags->isNotEmpty();
    }
}
