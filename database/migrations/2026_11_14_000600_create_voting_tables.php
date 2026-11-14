<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Suara DM (Bab 7.4) dan hasil Borda (Bab 5.3, D05, D15). */
    public function up(): void
    {
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('decision_sessions')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->json('ranking'); // array case_id berurutan dari peringkat 1
            $table->enum('status', ['TERKIRIM', 'DIBATALKAN'])->default('TERKIRIM');
            $table->timestamp('submitted_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            // "Satu suara TERKIRIM per DM per sesi" dijaga di transaksi (Bab 9.4).
            $table->index(['session_id', 'user_id', 'status']);
        });

        Schema::create('consensus_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('decision_sessions')->restrictOnDelete();
            $table->enum('status', ['MENUNGGU_FINALISASI', 'PERLU_MUSYAWARAH', 'FINAL', 'DIBATALKAN']);
            $table->text('deliberation_note')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('consensus_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consensus_id')->constrained('consensus_results')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('patient_cases')->restrictOnDelete();
            $table->unsignedTinyInteger('borda_points');
            $table->double('group_v');
            $table->unsignedTinyInteger('final_rank');
            $table->enum('tie_break_rule', ['V_KELOMPOK', 'JUMLAH_PERINGKAT_1', 'MUSYAWARAH'])->nullable();
            $table->timestamps();

            $table->unique(['consensus_id', 'case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consensus_items');
        Schema::dropIfExists('consensus_results');
        Schema::dropIfExists('votes');
    }
};
