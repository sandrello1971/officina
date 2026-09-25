<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Nuovo controllo di completezza della "consegna": non verifica se un contenuto è
// aggiornato (Freshness) né se manca un ARGOMENTO (P26 Gap Scout), ma se ogni modulo ha
// la dotazione minima per essere erogato (slide, materiali, sezione nel manuale, HTML
// integro, ordinamento) e se i permessi filesystem dei materiali sono corretti. Solo
// lettura su corsi/moduli/filesystem: MAI scrittura automatica, nessuna chiamata AI.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('completeness_findings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignUuid('module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->string('check_type', 60);
            $table->string('severity', 12)->default('warning');
            $table->text('message');
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'resolved_at', 'dismissed_at']);
        });

        DB::statement("ALTER TABLE completeness_findings ADD CONSTRAINT completeness_findings_severity_check
            CHECK (severity IN ('info', 'warning'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('completeness_findings');
    }
};
