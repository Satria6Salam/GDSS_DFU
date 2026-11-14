<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Catatan kesimpulan dan rencana tindak lanjut yang ditulis ketua saat finalisasi (opsional). */
    public function up(): void
    {
        Schema::table('consensus_results', function (Blueprint $table) {
            $table->text('conclusion_note')->nullable()->after('deliberation_note');
        });
    }

    public function down(): void
    {
        Schema::table('consensus_results', function (Blueprint $table) {
            $table->dropColumn('conclusion_note');
        });
    }
};
