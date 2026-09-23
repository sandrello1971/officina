<?php

namespace App\Console\Commands;

use App\Jobs\GenerateCourseOutlineJob;
use App\Models\Course;
use App\Models\CourseGenerationRun;
use App\Models\DocumentRag;
use Illuminate\Console\Command;

// Motore generazione corsi da KB — trigger da CLI (uso: test/debug). Il percorso
// primario resta la UI (CourseGenerationController), che raccoglie il brief dal
// formatore; qui il brief è passato via opzioni o resta vuoto.
class GenerateCourseFromKb extends Command
{
    protected $signature = 'officina:generate-course
        {course : ID del corso}
        {--target= : Pubblico/target}
        {--level= : Livello}
        {--duration-hours= : Durata indicativa in ore}
        {--modules-count= : Numero di moduli desiderato}
        {--objectives= : Obiettivi di apprendimento}
        {--tone= : Tono/registro}
        {--language= : Lingua}
        {--constraints= : Vincoli espliciti}';

    protected $description = 'Avvia il motore di generazione corso da knowledge base (Fase 1: proposta struttura)';

    public function handle(): int
    {
        $course = Course::find($this->argument('course'));
        if (!$course) {
            $this->error('Corso non trovato.');
            return self::FAILURE;
        }

        $hasSource = DocumentRag::where('course_id', $course->id)->exists() || $course->courseLevelMaterials()->exists();
        if (!$hasSource) {
            $this->error('Nessun materiale caricato sul corso: carica prima i documenti della knowledge base.');
            return self::FAILURE;
        }

        $brief = array_filter([
            'target' => $this->option('target'),
            'level' => $this->option('level'),
            'duration_hours' => $this->option('duration-hours'),
            'modules_count' => $this->option('modules-count'),
            'objectives' => $this->option('objectives'),
            'tone' => $this->option('tone'),
            'language' => $this->option('language'),
            'constraints' => $this->option('constraints'),
        ], fn ($v) => $v !== null && $v !== '');

        $run = CourseGenerationRun::create([
            'course_id' => $course->id,
            'status' => 'running',
            'phase' => 'outline',
            'brief' => $brief,
            'started_at' => now(),
        ]);

        GenerateCourseOutlineJob::dispatchSync($run->id);

        $run->refresh();
        if ($run->status === 'failed') {
            $this->error('Generazione outline fallita: ' . $run->error);
            return self::FAILURE;
        }

        $this->info("Outline proposto per «{$course->name}» (run {$run->id}):");
        foreach ($run->outline as $m) {
            $this->line("- {$m['title']}");
        }
        $this->line('Revisione: /admin/course-generation/' . $run->id);

        return self::SUCCESS;
    }
}
