<?php

namespace App\Services\CourseGeneration;

use App\Models\Course;
use App\Models\DocumentRag;
use Illuminate\Support\Collection;

/**
 * Motore generazione corsi da KB — aggrega in un unico testo la KB del corso:
 * i documenti caricati via "Documenti AI" (DocumentRag, la vera libreria
 * multi-formato dell'app) e i materiali caricati via "Materiali Formatore"
 * (Course::courseLevelMaterials) — entrambi sono modi legittimi e già in uso
 * per allegare fonti a un corso, unificarli evita che "ho caricato ma l'AI
 * non lo vede" dipenda da quale via ha usato il formatore.
 */
class CourseSourceAggregator
{
    private const MAX_CHARS = 40000;

    /** Overlap di RagService::chunkText() — va tolto per non duplicare testo in fase di ricomposizione. */
    private const CHUNK_OVERLAP = 200;

    /**
     * @param  ?array<int, array{source_type:string, id?:string, title?:string}>  $selectedSources
     *         Selezione confermata dal formatore nella tappa "Seleziona le fonti"
     *         (course_generation_runs.selected_sources). Null = nessun filtro,
     *         usa TUTTA la KB del corso (comportamento del comando CLI di test).
     */
    public function aggregate(Course $course, ?array $selectedSources = null): string
    {
        $selectedTitles = null;
        $selectedMaterialIds = null;
        if ($selectedSources !== null) {
            $selected = collect($selectedSources);
            $selectedTitles = $selected->where('source_type', 'document_rag')->pluck('title')->all();
            $selectedMaterialIds = $selected->where('source_type', 'material')->pluck('id')->all();
        }

        $text = collect([
            $this->aggregateDocumentRag($course, $selectedTitles),
            $this->aggregateMaterials($course, $selectedMaterialIds),
        ])->filter(fn ($t) => trim($t) !== '')->implode("\n\n---\n\n");

        if (mb_strlen($text) > self::MAX_CHARS) {
            $text = mb_substr($text, 0, self::MAX_CHARS) . "\n[...contenuto troncato per limiti di token]";
        }

        return $text;
    }

    /** @param ?list<string> $titles null=tutti, []=nessuno */
    private function aggregateDocumentRag(Course $course, ?array $titles): string
    {
        if ($titles !== null && empty($titles)) {
            return '';
        }

        $query = DocumentRag::where('course_id', $course->id);
        if ($titles !== null) {
            $query->whereIn('title', $titles);
        }

        return $query->orderBy('title')->orderBy('chunk_index')
            ->get(['title', 'chunk_index', 'content'])
            ->groupBy('title')
            ->map(fn (Collection $chunks, string $title) => '## ' . $title . "\n" . $this->reassembleChunks($chunks))
            ->implode("\n\n---\n\n");
    }

    /** @param ?list<string> $materialIds null=tutti, []=nessuno */
    private function aggregateMaterials(Course $course, ?array $materialIds): string
    {
        if ($materialIds !== null && empty($materialIds)) {
            return '';
        }

        $query = $course->courseLevelMaterials();
        if ($materialIds !== null) {
            $query->whereIn('id', $materialIds);
        }

        return $query->get(['title', 'content_html'])
            ->map(fn ($m) => '## ' . $m->title . "\n" . trim(strip_tags((string) $m->content_html)))
            ->filter(fn ($chunk) => trim(explode("\n", $chunk, 2)[1] ?? '') !== '')
            ->implode("\n\n---\n\n");
    }

    /** Ricompone i chunk sovrapposti (200 char) in testo continuo: primo intero, successivi troncati dell'overlap. */
    private function reassembleChunks(Collection $chunks): string
    {
        $sorted = $chunks->sortBy('chunk_index')->values();
        $out = '';
        foreach ($sorted as $i => $chunk) {
            $out .= $i === 0 ? $chunk->content : mb_substr((string) $chunk->content, self::CHUNK_OVERLAP);
        }

        return trim($out);
    }
}
