<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Satu siklus keputusan (D09; status Bab 7.2). */
    public function up(): void
    {
        Schema::create('decision_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->enum('status', ['PERSIAPAN', 'PENILAIAN', 'VOTING', 'MENUNGGU_FINALISASI', 'FINAL'])
                ->default('PERSIAPAN')->index();
            $table->string('dataset_version', 20)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assessment_opened_at')->nullable();
            $table->timestamp('voting_started_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_sessions');
    }
};
