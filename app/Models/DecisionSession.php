<?php

namespace App\Models;

use App\Enums\ResultScope;
use App\Enums\SessionStatus;
use App\Enums\VoteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu siklus keputusan (D09). Perubahan status hanya lewat SessionWorkflowService (Bab 7.5).
 */
class DecisionSession extends Model
{
    protected $fillable = [
        'title',
        'status',
        'dataset_version',
        'created_by',
        'assessment_opened_at',
        'voting_started_at',
        'finalized_at',
        'finalized_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SessionStatus::class,
            'assessment_opened_at' => 'datetime',
            'voting_started_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function isStatus(SessionStatus $status): bool
    {
        return $this->status === $status;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(PatientCase::class, 'session_id')->orderBy('kode_kasus');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'session_id');
    }

    public function ahpResults(): HasMany
    {
        return $this->hasMany(AhpResult::class, 'session_id');
    }

    public function topsisResults(): HasMany
    {
        return $this->hasMany(TopsisResult::class, 'session_id');
    }

    public function groupTopsisResults(): HasMany
    {
        return $this->topsisResults()->where('scope', ResultScope::Kelompok);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class, 'session_id');
    }

    public function activeVotes(): HasMany
    {
        return $this->votes()->where('status', VoteStatus::Terkirim);
    }

    public function consensusResults(): HasMany
    {
        return $this->hasMany(ConsensusResult::class, 'session_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'session_id');
    }
}
