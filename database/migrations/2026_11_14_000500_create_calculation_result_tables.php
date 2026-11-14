<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hasil perhitungan AHP dan TOPSIS, individual (user_id terisi) dan kelompok (scope KELOMPOK).
     * Boleh dihitung ulang: baris lama dihapus dan diganti dalam satu transaksi (Bab 9.3).
     */
    public function up(): void
    {
        Schema::create('ahp_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('decision_sessions')->cascadeOnDelete();
            $table->enum('scope', ['INDIVIDUAL', 'KELOMPOK']);
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->double('lambda_max');
            $table->double('ci');
            $table->double('cr');
            $table->timestamps();

            $table->index(['session_id', 'scope', 'user_id']);
        });

        Schema::create('ahp_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ahp_result_id')->constrained('ahp_results')->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('criteria')->restrictOnDelete();
            $table->double('weight');
            $table->timestamps();

            $table->unique(['ahp_result_id', 'criterion_id']);
        });

        Schema::create('topsis_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('decision_sessions')->cascadeOnDelete();
            $table->enum('scope', ['INDIVIDUAL', 'KELOMPOK']);
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('case_id')->constrained('patient_cases')->cascadeOnDelete();
            $table->double('d_plus');
            $table->double('d_minus');
            $table->double('v');
            $table->unsignedTinyInteger('rank');
            $table->timestamps();

            $table->index(['session_id', 'scope', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topsis_results');
        Schema::dropIfExists('ahp_weights');
        Schema::dropIfExists('ahp_results');
    }
};
