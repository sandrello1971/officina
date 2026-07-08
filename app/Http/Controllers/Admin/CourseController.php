<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseTag;
use App\Services\VideoAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        // Filtro + ricerca combinabili in AND, tutti opzionali via query string.
        $categoryId = $request->query('category');
        $tagIds = array_filter((array) $request->query('tags', []));
        $q = trim((string) $request->query('q', ''));

        $courses = Course::withCount('modules')
            ->with(['category', 'tags'])
            ->when($categoryId, fn ($query) => $query->where('course_category_id', $categoryId))
            ->when(!empty($tagIds), function ($query) use ($tagIds) {
                // AND: il corso deve avere TUTTI i tag selezionati.
                foreach ($tagIds as $tagId) {
                    $query->whereHas('tags', fn ($t) => $t->where('course_tags.id', $tagId));
                }
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'ilike', "%{$q}%")
                        ->orWhere('short_description', 'ilike', "%{$q}%");
                });
            })
            ->orderBy('sort_order')
            ->get();

        $categories = CourseCategory::ordered()->get();
        $tags = CourseTag::orderBy('name')->get();

        return view('admin.courses.index', compact('courses', 'categories', 'tags', 'categoryId', 'tagIds', 'q'));
    }

    public function create()
    {
        $categories = CourseCategory::ordered()->get();
        $tags = CourseTag::orderBy('name')->get();

        return view('admin.courses.create', compact('categories', 'tags'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:courses,slug',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:20',
            'duration_hours' => 'nullable|integer',
            'certification_name' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer',
            'course_category_id' => 'nullable|uuid|exists:course_categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'uuid|exists:course_tags,id',
            'video_file' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm|max:2048000',
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');
        unset($data['video_file'], $data['tags']);

        $course = Course::create($data);
        $course->tags()->sync($request->input('tags', []));

        if ($request->hasFile('video_file')) {
            $this->handleVideoUpload($course, $request->file('video_file'));
        }

        return redirect("/admin/courses/{$course->id}/edit")
            ->with('success', 'Corso creato. Aggiungi i moduli.');
    }

    public function show(string $id)
    {
        $course = Course::with('modules')->findOrFail($id);
        return view('admin.courses.show', compact('course'));
    }

    public function edit(string $id)
    {
        $course = Course::findOrFail($id);

        // F-c — se il corso ha aggiornamenti applicati dall'agente, ricaricare il manuale
        // rigenererebbe il sorgente dal docx invalidandoli: la view mostra un avviso + conferma.
        $applyHistory = \App\Models\CourseChangelog::where('course_id', $course->id)
            ->where('kind', 'apply')
            ->where('content_source', 'instructor')
            ->orderByDesc('created_at')
            ->get();
        $sourceOverwrite = [
            'hasHistory' => $applyHistory->isNotEmpty(),
            'count' => $applyHistory->count(),
            'last' => optional($applyHistory->first())->summary,
        ];

        $categories = CourseCategory::ordered()->get();
        $tags = CourseTag::orderBy('name')->get();
        $selectedTagIds = $course->tags()->pluck('course_tags.id')->all();

        return view('admin.courses.edit', compact('course', 'sourceOverwrite', 'categories', 'tags', 'selectedTagIds'));
    }

    public function update(Request $request, string $id)
    {
        $course = Course::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:courses,slug,' . $course->id,
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:20',
            'duration_hours' => 'nullable|integer',
            'certification_name' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer',
            'course_category_id' => 'nullable|uuid|exists:course_categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'uuid|exists:course_tags,id',
            'video_file' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm|max:2048000',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        unset($data['video_file'], $data['tags']);

        $course->update($data);
        $course->tags()->sync($request->input('tags', []));

        if ($request->hasFile('video_file')) {
            $error = $this->handleVideoUpload($course, $request->file('video_file'));
            if ($error) return back()->with('error', $error);
        }

        return redirect()->route('admin.courses.index')->with('success', 'Corso aggiornato.');
    }

    public function destroy(string $id)
    {
        $course = Course::with('modules')->findOrFail($id);

        $videoAI = app(VideoAIService::class);
        $videoIds = array_filter(array_merge(
            [$course->video_ai_id],
            $course->modules->pluck('video_ai_id')->toArray(),
        ));

        foreach ($videoIds as $vid) {
            try {
                $videoAI->deleteVideo($vid);
            } catch (\Exception $e) {
                Log::warning("VideoAI delete failed during course destroy ({$vid}): " . $e->getMessage());
            }
        }

        $course->delete();
        return redirect()->route('admin.courses.index')->with('success', 'Corso eliminato.');
    }

    public function generateQuiz(Request $request, Course $course)
    {
        $content = $course->modules()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('content')
            ->filter()
            ->join("\n\n");

        if (empty(trim($content))) {
            return back()->with('error', 'Nessun contenuto nei moduli. Aggiungi prima il testo dei moduli.');
        }

        // Dimensione pool (quante domande generare) + quante estrarne per tentativo.
        $numQuestions = (int) $request->input('num_questions', 10);
        $perAttempt = (int) $request->input('questions_per_attempt', 0) ?: null;

        if ($perAttempt !== null && $perAttempt > $numQuestions) {
            return back()->with('error',
                "Le domande da estrarre per tentativo ({$perAttempt}) non possono superare la dimensione del pool ({$numQuestions}).");
        }

        $generator = app(\App\Services\QuizGeneratorService::class);
        $quiz = $generator->generateFromContent($course, $content, $numQuestions, $perAttempt);

        if (!$quiz) {
            return back()->with('error', 'Errore nella generazione del quiz. Riprova.');
        }

        $pool = $quiz->questions()->count();
        $msg = $quiz->questions_per_attempt
            ? "Pool di {$pool} domande generato; ogni tentativo ne estrae {$quiz->questions_per_attempt}."
            : "Quiz generato con {$pool} domande!";

        return redirect("/admin/quizzes/{$quiz->id}/questions")->with('success', $msg);
    }

    private function handleVideoUpload(Course $course, $file): ?string
    {
        $videoAI = app(VideoAIService::class);

        if ($course->video_ai_id) {
            try {
                $videoAI->deleteVideo($course->video_ai_id);
            } catch (\Exception $e) {
                Log::warning('VideoAI delete (course) old video failed: ' . $e->getMessage());
            }
        }

        try {
            $result = $videoAI->ingestVideo(
                $file->getPathname(),
                $file->getClientOriginalName()
            );

            $course->update([
                'video_ai_id' => $result['video_id'],
                'video_filename' => $file->getClientOriginalName(),
                'video_status' => $result['status'] ?? 'processing',
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('VideoAI upload (course) error: ' . $e->getMessage());
            return 'Upload video fallito: ' . $e->getMessage();
        }
    }
}
