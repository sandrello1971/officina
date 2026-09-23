<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Motore generazione corsi da KB — nuova fase 'sources': tra il brief e la
// proposta di outline si inserisce la tappa "Seleziona le fonti" (il run
// esiste già, con brief valorizzato, ma l'outline non parte finché il
// formatore non conferma la selezione — vedi CourseGenerationController).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE course_generation_runs DROP CONSTRAINT course_generation_runs_phase_check');
        DB::statement("ALTER TABLE course_generation_runs ADD CONSTRAINT course_generation_runs_phase_check
            CHECK (phase IN ('sources', 'outline', 'content', 'done'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE course_generation_runs DROP CONSTRAINT course_generation_runs_phase_check');
        DB::statement("ALTER TABLE course_generation_runs ADD CONSTRAINT course_generation_runs_phase_check
            CHECK (phase IN ('outline', 'content', 'done'))");
    }
};
