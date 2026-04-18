<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Student;
use App\Services\VideoAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VideoController extends Controller
{
    public function __construct(private VideoAIService $videoAI) {}

    private function checkVideoAccess(string $videoId): bool
    {
        $student = Student::findOrFail(session('student_id'));

        $module = Module::where('video_ai_id', $videoId)->first();
        if (!$module) return false;

        return $student->courses()
            ->wherePivot('is_active', true)
            ->where('courses.id', $module->course_id)
            ->exists();
    }

    public function stream(Request $request, string $videoId)
    {
        if (!$this->checkVideoAccess($videoId)) abort(403);

        $url = config('services.videoai.url') . "/api/videos/{$videoId}/stream";
        $headers = [];
        if ($request->header('Range')) {
            $headers['Range'] = $request->header('Range');
        }

        $response = Http::withHeaders($headers)
            ->timeout(0)
            ->get($url);

        return response($response->body(), $response->status())
            ->withHeaders([
                'Content-Type' => $response->header('Content-Type') ?? 'video/mp4',
                'Content-Length' => $response->header('Content-Length') ?? '',
                'Content-Range' => $response->header('Content-Range') ?? '',
                'Accept-Ranges' => 'bytes',
            ]);
    }

    public function thumbnail(string $videoId)
    {
        if (!$this->checkVideoAccess($videoId)) abort(403);

        $url = config('services.videoai.url') . "/api/videos/{$videoId}/thumbnail";
        $response = Http::timeout(10)->get($url);

        return response($response->body(), $response->status())
            ->header('Content-Type', 'image/jpeg');
    }

    public function chat(Request $request, string $videoId)
    {
        if (!$this->checkVideoAccess($videoId)) abort(403);

        $request->validate([
            'question' => 'required|string|max:1000',
            'history' => 'nullable|array',
        ]);

        try {
            $result = $this->videoAI->chat(
                $videoId,
                $request->question,
                $request->history ?? []
            );
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function transcript(string $videoId)
    {
        if (!$this->checkVideoAccess($videoId)) abort(403);
        return response()->json($this->videoAI->getTranscript($videoId));
    }

    public function status(string $videoId)
    {
        if (!$this->checkVideoAccess($videoId)) abort(403);
        return response()->json($this->videoAI->getStatus($videoId));
    }
}
