<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Kasus pasien dummy (Bab 4.2) dan red flag per kasus (Bab 3.5). */
    public function up(): void
    {
        Schema::create('patient_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('decision_sessions')->cascadeOnDelete();
            $table->string('kode_kasus', 5);

            // Identitas dummy
            $table->unsignedSmallInteger('usia');
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->enum('tipe_dm', ['DM tipe 1', 'DM tipe 2']);
            $table->unsignedSmallInteger('lama_dm');
            $table->string('lokasi_luka', 100);

            // C1 infeksi
            $table->boolean('tanda_inflamasi');
            $table->decimal('eritema', 5, 1);
            $table->decimal('suhu', 5, 1);
            $table->unsignedSmallInteger('nadi');
            $table->unsignedSmallInteger('laju_napas');
            $table->decimal('leukosit', 5, 1);

            // C2 perfusi
            $table->decimal('abi', 3, 2);
            $table->enum('nadi_kaki', ['Teraba', 'Teraba lemah', 'Tidak teraba']);
            $table->boolean('nyeri_istirahat');
            $table->boolean('gangren');

            // C3 luka
            $table->decimal('luas_luka', 5, 1);
            $table->enum('kedalaman', ['Superfisial', 'Subkutan', 'Tendon', 'Tulang']);

            // C4 neuropati
            $table->unsignedTinyInteger('monofilamen');
            $table->string('deformitas', 50);

            // C5 sistemik
            $table->decimal('hba1c', 5, 1);
            $table->unsignedSmallInteger('gds');
            $table->unsignedSmallInteger('egfr');
            $table->string('tekanan_darah', 50);

            // C6 progres
            $table->enum('progres_luka', ['MEMBAIK', 'STABIL', 'TIDAK_MEMBAIK', 'MEMBURUK_1_MINGGU', 'MEMBURUK_CEPAT']);

            // Info farmasi
            $table->string('alergi_obat', 200);
            $table->string('obat_rutin', 200);

            $table->timestamps();
            $table->unique(['session_id', 'kode_kasus']);
        });

        Schema::create('case_red_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('patient_cases')->cascadeOnDelete();
            $table->enum('code', ['RF1', 'RF2', 'RF3', 'RF4', 'RF5']);
            $table->enum('source', ['OTOMATIS', 'MANUAL']);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['case_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_red_flags');
        Schema::dropIfExists('patient_cases');
    }
};
