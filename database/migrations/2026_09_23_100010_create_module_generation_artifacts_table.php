<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Motore generazione corsi da KB — un artefatto AI (manuale discente, manuale
// formatore o slide) per modulo, con stato di revisione. Il formatore vede i 3
// artefatti di un modulo raggruppati sotto lo stesso run, invece di 3 code separate.
//
// `content` porta il testo generato per student_manual/instructor_manual (il testo
// del manuale discente vive ANCHE in modules.content_draft: qui è lo snapshot su
// cui gira la review, promosso a content_draft/instructor_manual_sections solo al
// publish). Per slides il contenuto reale è il ModulePresentation collegato via
// artifact_id: qui si traccia solo review/publish, la generazione/status tecnico
// (generating/ready/failed) restano su module_presentations (S0, invariato).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_generation_artifacts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('run_id')->constrained('course_generation_runs')->cascadeOnDelete();
            $table->foreignUuid('module_id')->constrained('modules')->cascadeOnDelete();

            $table->string('artifact_type', 20); // student_manual|instructor_manual|slides
            $table->string('status', 14)->default('draft_ai'); // draft_ai|pending_review|approved|rejected|published

            $table->uuid('artifact_id')->nullable(); // FK debole verso module_presentations (slides)
            $table->json('content')->nullable();     // testo generato (student_manual/instructor_manual)
            $table->json('generation_meta')->nullable();

            $table->foreignUuid('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('edited_by_human')->default(false);

            $table->timestamps();

            $table->unique(['run_id', 'module_id', 'artifact_type']);
            $table->index(['module_id', 'artifact_type']);
        });

        DB::statement("ALTER TABLE module_generation_artifacts ADD CONSTRAINT module_generation_artifacts_type_check
            CHECK (artifact_type IN ('student_manual', 'instructor_manual', 'slides'))");
        DB::statement("ALTER TABLE module_generation_artifacts ADD CONSTRAINT module_generation_artifacts_status_check
            CHECK (status IN ('draft_ai', 'pending_review', 'approved', 'rejected', 'published'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('module_generation_artifacts');
    }
};
