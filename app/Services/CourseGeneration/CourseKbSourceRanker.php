<?php

namespace App\Services\CourseGeneration;

use App\Models\Course;
use App\Models\DocumentRag;

/**
 * Motore generazione corsi da KB — dato un argomento (topic), ordina i
 * documenti già presenti sul corso per pertinenza, così il formatore non
 * deve scegliere "alla cieca" tra magari decine di upload eterogenei.
 *
 * Euristica volutamente semplice (overlap di parole chiave, niente AI né
 * retrieval vettoriale): il mondo Corsi ha `rag_vector_enabled_corsi`
 * disattivato di default, e qui serve solo una TRIAGE per l'umano, non una
 * risposta esatta — la conferma finale resta sempre del formatore (HITL).
 */
class CourseKbSourceRanker
{
    private const STOPWORDS = ['di', 'e', 'il', 'la', 'per', 'con', 'un', 'una', 'gli', 'le', 'del', 'della', 'dei', 'delle', 'a', 'in', 'su', 'da'];

    /**
     * @return array<int, array{source_type:string, id:string, title:string, snippet:string, score:int, suggested:bool}>
     */
    public function rank(Course $course, string $topic): array
    {
        $keywords = $this->keywords($topic);
        $items = [];

        $ragGroups = DocumentRag::where('course_id', $course->id)
            ->orderBy('title')->orderBy('chunk_index')
            ->get(['id', 'title', 'content'])
            ->groupBy('title');

        foreach ($ragGroups as $title => $chunks) {
            $text = $chunks->pluck('content')->implode(' ');
            $items[] = [
                'source_type' => 'document_rag',
                'id' => (string) $chunks->first()->id, // id del primo chunk: sufficiente per riferire il gruppo (stesso title)
                'title' => (string) $title,
                'snippet' => mb_substr(trim($text), 0, 200),
                'score' => $this->score($text, $keywords),
            ];
        }

        foreach ($course->courseLevelMaterials()->get(['id', 'title', 'content_html']) as $material) {
            $text = strip_tags((string) $material->content_html);
            $items[] = [
                'source_type' => 'material',
                'id' => (string) $material->id,
                'title' => (string) $material->title,
                'snippet' => mb_substr(trim($text), 0, 200),
                'score' => $this->score($text, $keywords),
            ];
        }

        usort($items, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn ($i) => $i + ['suggested' => $i['score'] > 0], $items);
    }

    /** @return list<string> */
    private function keywords(string $topic): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($topic)) ?: [];

        return array_values(array_filter($words, fn ($w) => mb_strlen($w) >= 3 && !in_array($w, self::STOPWORDS, true)));
    }

    /** @param list<string> $keywords */
    private function score(string $text, array $keywords): int
    {
        if (empty($keywords) || trim($text) === '') {
            return 0;
        }

        $haystack = mb_strtolower($text);
        $score = 0;
        foreach ($keywords as $kw) {
            $score += substr_count($haystack, $kw);
        }

        return $score;
    }
}
