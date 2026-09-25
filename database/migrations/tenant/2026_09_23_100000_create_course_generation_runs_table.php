<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Motore generazione corsi da KB — un run per lancio del motore su un corso.
// `brief` (target/livello, durata, obiettivi, tono, vincoli) è l'input del
// formatore che guida sia l'outline sia, a valle, i contenuti dei moduli:
// injectato in ogni prompt della run per coerenza di tono/livello.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_generation_runs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();

            $table->string('status', 12)->default('running'); // running|completed|failed
            $table->string('phase', 12)->default('outline');  // outline|content|done

            $table->json('brief')->nullable();
            $table->json('outline')->nullable(); // proposta corrente (array di {title,summary})

            $table->foreignUuid('triggered_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['course_id', 'status']);
        });

        DB::statement("ALTER TABLE course_generation_runs ADD CONSTRAINT course_generation_runs_status_check
            CHECK (status IN ('running', 'completed', 'failed'))");
        DB::statement("ALTER TABLE course_generation_runs ADD CONSTRAINT course_generation_runs_phase_check
            CHECK (phase IN ('outline', 'content', 'done'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('course_generation_runs');
    }
};
