<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Edizioni di un corso: ogni ciclo di erogazione (es. "Ottobre 2026 — Azienda X")
 * ha le sue giornate, il suo gruppo di discenti e il suo registro presenze.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_editions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('modality')->default('in_person');
            $table->foreignUuid('instructor_id')->nullable()->constrained('students')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('course_id');
        });

        DB::statement("ALTER TABLE course_editions ADD CONSTRAINT course_editions_modality_check CHECK (modality IN ('in_person','live_online','blended'))");

        // Discenti dell'edizione. Un discente sta in UNA sola edizione per corso.
        Schema::create('course_edition_students', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('course_edition_id')->constrained('course_editions')->cascadeOnDelete();
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'student_id']);
            $table->unique(['course_edition_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_edition_students');
        Schema::dropIfExists('course_editions');
    }
};
