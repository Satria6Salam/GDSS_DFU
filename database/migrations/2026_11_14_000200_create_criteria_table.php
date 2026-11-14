<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Kriteria C1–C6 dan rubriknya (Bab 3); diisi seeder, tidak diubah lewat aplikasi. */
    public function up(): void
    {
        Schema::create('criteria', function (Blueprint $table) {
            $table->id();
            $table->string('code', 5);
            $table->string('name', 100);
            $table->text('definition');
            $table->enum('type', ['benefit', 'cost'])->default('benefit');
            $table->json('rubric');
            $table->string('data_reference', 200)->nullable();
            $table->string('source', 200)->nullable();
            $table->unsignedTinyInteger('sort_order');
            $table->string('version', 20);
            $table->timestamps();

            $table->unique(['code', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criteria');
    }
};
