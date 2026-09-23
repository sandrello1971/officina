<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Motore generazione corsi da KB — selezione fonti confermata dal formatore
// nella tappa "Seleziona le fonti" (subset di DocumentRag/Material da usare
// per la generazione). Null = nessun filtro, usa tutta la KB del corso
// (retrocompatibile col comando CLI di test, che non passa da questa tappa).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_generation_runs', function (Blueprint $table) {
            $table->json('selected_sources')->nullable()->after('outline');
        });
    }

    public function down(): void
    {
        Schema::table('course_generation_runs', function (Blueprint $table) {
            $table->dropColumn('selected_sources');
        });
    }
};
