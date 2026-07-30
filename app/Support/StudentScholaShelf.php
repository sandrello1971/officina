<?php

namespace App\Support;

use App\Models\Lesson;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Scaffale Schola dello studente: le LEZIONI pubblicate sulle sue classi,
 * raggruppate per classe.
 *
 * Serve a mostrare il materiale del docente anche in "I miei corsi": i due
 * mondi hanno modelli distinti (Course di catalogo, iscrizione via
 * `course_student` — Lesson di classe, visibilità via `lesson_publications`)
 * e nessun legame in tabella, quindi la pagina li affianca senza fonderli.
 *
 * I filtri replicano ESATTAMENTE i gate di StudentLessonController::show
 * (iscrizione ATTIVA, lezione pubblicata su QUELLA classe, generazione pronta
 * e corpo non vuoto): nessuna card può portare a un 403/404.
 */
class StudentScholaShelf
{
    /**
     * Gruppi ['class' => SchoolClass, 'lessons' => Collection<Lesson>], classi
     * in ordine alfabetico, lezioni per argomento→posizione come nel feed
     * classe. Le classi senza lezioni fruibili non producono un gruppo.
     */
    public function lessonsByClass(Student $student): Collection
    {
        $classes = $student->schoolClasses()
            ->wherePivot('status', 'active')
            ->with('subject')
            ->orderBy('name')
            ->get();

        if ($classes->isEmpty()) {
            return collect();
        }

        $classIds = $classes->pluck('id')->all();

        $lessons = Lesson::query()
            ->whereHas('publications', fn ($q) => $q->whereIn('school_class_id', $classIds))
            ->where('generation_status', 'ready')
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->with([
                'topic',
                'teacher:id,name',
                'publications' => fn ($q) => $q->whereIn('school_class_id', $classIds),
            ])
            ->get();

        return $classes
            ->map(fn ($class) => [
                'class' => $class,
                'lessons' => $lessons
                    ->filter(fn ($l) => $l->publications->contains('school_class_id', $class->id))
                    ->sortBy(fn ($l) => [$l->topic?->position ?? 0, $l->position])
                    ->values(),
            ])
            ->filter(fn ($group) => $group['lessons']->isNotEmpty())
            ->values();
    }
}
