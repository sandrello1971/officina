<?php

namespace App\Services\CourseGeneration;

use App\Models\Course;

/**
 * Motore generazione corsi da KB — aggrega in un unico testo i materiali
 * caricati a livello di corso (Course::courseLevelMaterials), la KB su cui
 * lavorano sia CourseOutlineGenerationService sia ModuleManualGenerationService.
 */
class CourseSourceAggregator
{
    private const MAX_CHARS = 40000;

    public function aggregate(Course $course): string
    {
        $text = $course->courseLevelMaterials()
            ->get(['title', 'content_html'])
            ->map(fn ($m) => '## ' . $m->title . "\n" . trim(strip_tags((string) $m->content_html)))
            ->filter(fn ($chunk) => trim(explode("\n", $chunk, 2)[1] ?? '') !== '')
            ->implode("\n\n---\n\n");

        if (mb_strlen($text) > self::MAX_CHARS) {
            $text = mb_substr($text, 0, self::MAX_CHARS) . "\n[...contenuto troncato per limiti di token]";
        }

        return $text;
    }
}
