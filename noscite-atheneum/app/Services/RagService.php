<?php

namespace App\Services;

use App\Models\Course;
use App\Models\DocumentRag;
use App\Models\Module;
use Illuminate\Support\Facades\Log;

class RagService
{
    public function indexDocument(
        string $text,
        string $title,
        ?string $courseId,
        ?string $moduleId,
        ?string $filePath,
        bool $isInstructorOnly = false
    ): void {
        $chunks = $this->chunkText($text, 1000, 200);

        foreach ($chunks as $index => $chunk) {
            DocumentRag::create([
                'title' => $title,
                'content' => $chunk,
                'course_id' => $courseId,
                'module_id' => $moduleId,
                'file_path' => $filePath,
                'chunk_index' => $index,
                'is_instructor_only' => $isInstructorOnly,
                'metadata' => [
                    'chunks_total' => count($chunks),
                    'source_title' => $title,
                ],
            ]);
        }
    }

    public function search(string $query, ?string $courseId = null, int $limit = 5)
    {
        return $this->searchInCourses(
            $query,
            $courseId ? [$courseId] : null,
            $limit,
            true,   // includePlatform
            false   // includeInstructorOnly — esclude di default
        );
    }

    public function searchInCourses(
        string $query,
        ?array $courseIds = null,
        int $limit = 5,
        bool $includePlatform = true,
        bool $includeInstructorOnly = false
    ) {
        $terms = array_filter(array_map('trim', preg_split('/\s+/', $query)), fn($t) => mb_strlen($t) >= 3);
        if (empty($terms)) $terms = [$query];

        $q = DocumentRag::query();

        if (!$includeInstructorOnly) {
            $q->where('is_instructor_only', false);
        }

        if (is_array($courseIds)) {
            $q->where(function ($w) use ($courseIds, $includePlatform) {
                if (!empty($courseIds)) $w->whereIn('course_id', $courseIds);
                if ($includePlatform) $w->orWhereNull('course_id');
            });
        }

        $q->where(function ($w) use ($terms) {
            foreach ($terms as $term) {
                $w->orWhere('content', 'ILIKE', '%' . $term . '%')
                  ->orWhere('title', 'ILIKE', '%' . $term . '%');
            }
        });

        return $q->limit($limit)->get();
    }

    /**
     * Ricerca scoping-aware: i documenti instructor-only sono ammessi
     * SOLO per i corsi insegnati ($instructorScopedCourseIds), mai
     * globalmente. I documenti studente (non instructor-only) sono
     * limitati ai $courseIds navigabili, più i doc di piattaforma
     * (course_id IS NULL) — decisione §8.3: inclusi sempre.
     */
    public function searchScoped(
        string $query,
        array $courseIds,
        array $instructorScopedCourseIds = [],
        int $limit = 5
    ) {
        $terms = array_filter(
            array_map('trim', preg_split('/\s+/', $query)),
            fn($t) => mb_strlen($t) >= 3
        );
        if (empty($terms)) $terms = [$query];

        $q = DocumentRag::query();

        $q->where(function ($w) use ($courseIds, $instructorScopedCourseIds) {
            // Documenti studente: non instructor-only, scoped ai corsi
            // navigabili. Inclusi i doc di piattaforma (course_id NULL).
            $w->where(function ($s) use ($courseIds) {
                $s->where('is_instructor_only', false)
                  ->where(function ($c) use ($courseIds) {
                      if (!empty($courseIds)) {
                          $c->whereIn('course_id', $courseIds);
                      }
                      $c->orWhereNull('course_id');
                  });
            });
            // Documenti instructor-only: SOLO per i corsi insegnati.
            if (!empty($instructorScopedCourseIds)) {
                $w->orWhere(function ($i) use ($instructorScopedCourseIds) {
                    $i->where('is_instructor_only', true)
                      ->whereIn('course_id', $instructorScopedCourseIds);
                });
            }
        });

        $q->where(function ($w) use ($terms) {
            foreach ($terms as $term) {
                $w->orWhere('content', 'ILIKE', '%' . $term . '%')
                  ->orWhere('title', 'ILIKE', '%' . $term . '%');
            }
        });

        return $q->limit($limit)->get();
    }

    public function searchForUser(
        string $query,
        array $courseIds,
        bool $isInstructor,
        int $limit = 5
    ) {
        $terms = array_filter(
            array_map('trim', preg_split('/\s+/', $query)),
            fn($t) => mb_strlen($t) >= 3
        );
        if (empty($terms)) $terms = [$query];

        $q = DocumentRag::query();

        if ($isInstructor) {
            // Instructor: no filter on course_id, no filter on is_instructor_only.
            // Coerente con auto_enroll_all_courses=true.
        } else {
            $q->where('is_instructor_only', false);
            $q->where(function ($w) use ($courseIds) {
                if (!empty($courseIds)) {
                    $w->whereIn('course_id', $courseIds);
                }
                $w->orWhereNull('course_id');
            });
        }

        $q->where(function ($w) use ($terms) {
            foreach ($terms as $term) {
                $w->orWhere('content', 'ILIKE', '%' . $term . '%')
                  ->orWhere('title', 'ILIKE', '%' . $term . '%');
            }
        });

        return $q->limit($limit)->get();
    }

    public function searchVideos(string $query, ?string $courseId = null, ?string $moduleId = null, int $limit = 3): array
    {
        try {
            // Build the video corpus: courses + modules by scope.
            $modules = collect();
            $courses = collect();

            if ($moduleId) {
                $m = Module::whereNotNull('video_ai_id')->where('id', $moduleId)->with('course')->first();
                if ($m) $modules = collect([$m]);
            } elseif ($courseId) {
                $modules = Module::whereNotNull('video_ai_id')
                    ->where('course_id', $courseId)
                    ->where('is_active', true)
                    ->with('course')
                    ->get();
                $c = Course::whereNotNull('video_ai_id')->where('id', $courseId)->first();
                if ($c) $courses = collect([$c]);
            } else {
                $modules = Module::whereNotNull('video_ai_id')->with('course')->get();
                $courses = Course::whereNotNull('video_ai_id')->get();
            }

            $videoIds = array_values(array_unique(array_merge(
                $modules->pluck('video_ai_id')->toArray(),
                $courses->pluck('video_ai_id')->toArray(),
            )));

            if (empty($videoIds)) return [];

            $videoAI = app(VideoAIService::class);
            $results = $videoAI->search($query, $videoIds);

            $formatted = [];
            $seen = [];
            foreach (array_slice($results, 0, $limit * 3) as $result) {
                $vid = $result['video_id'] ?? null;
                if (!$vid) continue;

                $module = $modules->firstWhere('video_ai_id', $vid);
                $course = $module ? $module->course : $courses->firstWhere('video_ai_id', $vid);
                $isCourseVideo = !$module && $course;

                foreach (array_slice($result['matches'] ?? [], 0, 2) as $match) {
                    $tsSeconds = isset($match['timestamp_seconds'])
                        ? (int) $match['timestamp_seconds']
                        : (isset($match['start']) ? (int) $match['start'] : 0);
                    $tsStr = $match['timestamp_str'] ?? $this->formatTimestamp($tsSeconds);

                    $dedupeKey = $vid . '|' . $tsSeconds;
                    if (isset($seen[$dedupeKey])) continue;
                    $seen[$dedupeKey] = true;

                    $courseSlug = $course?->slug;
                    $deepLink = null;
                    if ($courseSlug) {
                        $deepLink = $isCourseVideo
                            ? "/learn/course/{$courseSlug}?t={$tsSeconds}"
                            : "/learn/course/{$courseSlug}/module/{$module->id}?t={$tsSeconds}";
                    }

                    $title = $isCourseVideo
                        ? '🎬 Video corso: ' . ($course->name ?? 'Corso')
                        : '🎬 Video modulo: ' . ($module->title ?? 'Modulo');

                    $formatted[] = [
                        'content' => $match['text'] ?? '',
                        'text' => $match['text'] ?? '',
                        'title' => $title,
                        'type' => 'video',
                        'scope' => $isCourseVideo ? 'course' : 'module',
                        'timestamp' => $tsStr,
                        'timestamp_str' => $tsStr,
                        'timestamp_seconds' => $tsSeconds,
                        'video_ai_id' => $vid,
                        'module_id' => $module?->id,
                        'course_id' => $course?->id,
                        'course_slug' => $courseSlug,
                        'deep_link' => $deepLink,
                    ];
                }
            }

            return array_slice($formatted, 0, $limit);
        } catch (\Exception $e) {
            Log::error('VideoAI search error: ' . $e->getMessage());
            return [];
        }
    }

    private function formatTimestamp(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        return $h > 0
            ? sprintf('%d:%02d:%02d', $h, $m, $s)
            : sprintf('%d:%02d', $m, $s);
    }

    private function chunkText(string $text, int $chunkSize = 1000, int $overlap = 200): array
    {
        $text = trim($text);
        $length = mb_strlen($text, 'UTF-8');
        if ($length <= $chunkSize) {
            return [$text];
        }

        $chunks = [];
        $start = 0;

        while ($start < $length) {
            $chunks[] = mb_substr($text, $start, $chunkSize, 'UTF-8');
            $start += $chunkSize - $overlap;
        }

        return $chunks;
    }
}
