<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Penilaian DM: kepala penilaian, 15 perbandingan AHP, dan 48 skor kriteria (Bab 9.2). */
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('decision_sessions')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['BELUM', 'DRAF', 'FINAL', 'TERKUNCI'])->default('BELUM');
            $table->double('cr')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'user_id']);
        });

        Schema::create('ahp_judgments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('criterion_i_id')->constrained('criteria')->restrictOnDelete();
            $table->foreignId('criterion_j_id')->constrained('criteria')->restrictOnDelete();
            $table->double('value'); // a_ij, salah satu nilai skala Saaty 1/9 … 9; syarat i < j
            $table->timestamps();

            $table->unique(['assessment_id', 'criterion_i_id', 'criterion_j_id'], 'ahp_judgments_pair_unique');
        });

        Schema::create('assessment_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('patient_cases')->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('criteria')->restrictOnDelete();
            $table->unsignedTinyInteger('score'); // 1–5
            $table->timestamps();

            $table->unique(['assessment_id', 'case_id', 'criterion_id'], 'assessment_scores_cell_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_scores');
        Schema::dropIfExists('ahp_judgments');
        Schema::dropIfExists('assessments');
    }
};
