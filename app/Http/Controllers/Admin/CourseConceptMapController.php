<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCourseConceptMapRequest;
use App\Http\Requests\Admin\UpdateCourseConceptMapRequest;
use App\Models\Course;
use App\Models\CourseConceptMap;
use App\Services\ConceptMapGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CourseConceptMapController extends Controller
{
    public function __construct(
        private ConceptMapGenerationService $conceptMapService,
    ) {}

    public function index(Course $course)
    {
        $maps = $course->conceptMaps;
        return view('admin.courses.concept-maps.index', compact('course', 'maps'));
    }

    public function create(Course $course)
    {
        return view('admin.courses.concept-maps.create', compact('course'));
    }

    public function store(StoreCourseConceptMapRequest $request, Course $course)
    {
        $map = $course->conceptMaps()->create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'visibility' => $request->input('visibility', CourseConceptMap::VISIBILITY_DRAFT),
            'sort_order' => $request->input('sort_order', 0),
            'data' => ['nodes' => [], 'edges' => [], 'physics' => ['enabled' => true]],
        ]);

        return redirect()
            ->route('admin.courses.concept-maps.edit', [$course, $map])
            ->with('success', 'Mappa concettuale creata. Ora puoi popolarla manualmente o con AI.');
    }

    public function edit(Course $course, CourseConceptMap $concept_map)
    {
        $this->ensureBelongsToCourse($concept_map, $course);

        $modules = $course->modules()->orderBy('sort_order')->get(['id', 'title']);
        $materials = \App\Models\Material::whereIn('module_id', $modules->pluck('id'))
            ->orderBy('sort_order')->get(['id', 'module_id', 'title', 'file_type']);

        return view('admin.courses.concept-maps.edit', [
            'course' => $course,
            'map' => $concept_map,
            'modules' => $modules,
            'materials' => $materials,
        ]);
    }

    public function update(UpdateCourseConceptMapRequest $request, Course $course, CourseConceptMap $concept_map)
    {
        $this->ensureBelongsToCourse($concept_map, $course);

        $attrs = $request->only(['title', 'description', 'visibility', 'sort_order']);
        if ($request->has('data')) {
            $attrs['data'] = $request->input('data');
        }
        $concept_map->update($attrs);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'updated_at' => $concept_map->updated_at?->toIso8601String()]);
        }

        return back()->with('success', 'Mappa concettuale aggiornata.');
    }

    public function destroy(Course $course, CourseConceptMap $concept_map)
    {
        $this->ensureBelongsToCourse($concept_map, $course);
        $concept_map->delete();

        return redirect()
            ->route('admin.courses.concept-maps.index', $course)
            ->with('success', 'Mappa concettuale eliminata.');
    }

    /**
     * Genera (o rigenera) la mappa via Claude API, salva il JSON in data,
     * aggiorna content_hash e flag ai_generated.
     */
    public function generate(Request $request, Course $course, CourseConceptMap $concept_map)
    {
        $this->ensureBelongsToCourse($concept_map, $course);

        try {
            $graph = $this->conceptMapService->generate($course);

            $concept_map->update([
                'data' => $graph,
                'ai_generated' => true,
                'ai_generated_at' => now(),
                'content_hash' => $concept_map->currentContentHash(),
            ]);

            Log::info('ConceptMap saved', [
                'concept_map_id' => $concept_map->id,
                'course_id' => $course->id,
                'nodes' => count($graph['nodes']),
                'edges' => count($graph['edges']),
                'by_admin' => session('admin_email') ?? 'unknown',
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $graph,
                    'ai_generated_at' => $concept_map->ai_generated_at?->toIso8601String(),
                ]);
            }

            return redirect()
                ->route('admin.courses.concept-maps.edit', [$course, $concept_map])
                ->with('success', 'Mappa concettuale generata con AI. Rivedila e salva.');
        } catch (Throwable $e) {
            Log::error('ConceptMap generation failed', [
                'concept_map_id' => $concept_map->id,
                'course_id' => $course->id,
                'error' => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            }

            return back()->with('error', 'Errore generazione mappa concettuale: ' . $e->getMessage());
        }
    }

    private function ensureBelongsToCourse(CourseConceptMap $map, Course $course): void
    {
        if ($map->course_id !== $course->id) {
            abort(404);
        }
    }
}
